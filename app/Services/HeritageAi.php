<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\Media;
use App\Models\Person;
use App\Support\PartialDate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Family-memory AI. Every method returns suggestions only — nothing here writes to the archive.
 * The UI always shows a review step before suggestions become family records.
 */
class HeritageAi
{
    private const SYSTEM = 'You help families preserve their history in a private family archive. '
        .'Be faithful to the source material: never invent names, dates, places or events that are not supported by it. '
        .'When something is uncertain, say so or leave it out. Write warmly but plainly; avoid clichés and embellishment. '
        .'Keep names in the language and script they were given in.';

    public static function enabled(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Interview transcript -> people, places, dates, events, a story draft and follow-up questions.
     *
     * @param  array<int, string>  $knownPeople  "id: Name (lifespan)" lines so the model can match existing people
     */
    public function extractInterview(string $transcript, ?Person $interviewee, array $knownPeople): array
    {
        $who = $interviewee ? "The person being interviewed is {$interviewee->full_name} (id {$interviewee->id})." : 'The interviewee is not linked to a profile.';
        $prompt = "{$who}\n\nPeople already in the family archive (id: name):\n".implode("\n", array_slice($knownPeople, 0, 400))
            ."\n\nInterview transcript:\n<transcript>\n{$transcript}\n</transcript>\n\n"
            .'Extract what the transcript states. For each person mentioned, set matched_person_id to the id of an existing person only when you are confident it is the same person, otherwise null. '
            .'Describe each new person\'s relationship to the interviewee in relationship_to_interviewee (e.g. "mother", "elder brother", "neighbour"). '
            .'Dates use YYYY, YYYY-MM or YYYY-MM-DD; use null when unknown, and date_qualifier "about" for approximate dates. '
            .'story_body should be a readable first-person-faithful retelling in the interview\'s language, using only what was said. '
            .'follow_up_questions should help the family capture missing details next time.';

        return $this->json($prompt, self::interviewSchema());
    }

    /** Draft a biography from the person's structured data, events and stories. */
    public function biography(Person $person): string
    {
        $person->loadMissing(['birthPlace', 'deathPlace', 'events.place', 'stories', 'names']);
        $lines = [
            'Name: '.$person->full_name,
            'Other names: '.$person->names->pluck('full_name')->join(', '),
            'Born: '.PartialDate::format($person->birth_date, $person->birth_qualifier).' '.($person->birthPlace?->label ?? ''),
            'Died: '.($person->is_living ? 'living' : PartialDate::format($person->death_date, $person->death_qualifier).' '.($person->deathPlace?->label ?? '')),
            'Occupation: '.$person->occupation,
            'Education: '.$person->education,
            'Parents: '.$person->parents()->map(fn ($p) => $p->full_name.' ('.$p->link->type.')')->join(', '),
            'Partners: '.$person->partnerships()->map(fn ($u) => ($u->partnerOf($person)?->full_name ?? 'unknown').' — '.$u->label())->join('; '),
            'Children: '.$person->children()->map(fn ($c) => $c->full_name)->join(', '),
            'Siblings: '.$person->siblings()->map(fn ($s) => $s['person']->full_name.' ('.$s['kind'].')')->join(', '),
        ];
        foreach ($person->events as $e) {
            $lines[] = 'Event: '.PartialDate::format($e->date).' — '.$e->title.($e->place ? ' at '.$e->place->label : '').'. '.$e->description;
        }
        foreach ($person->stories->take(8) as $s) {
            $lines[] = "Story \"{$s->title}\": ".mb_substr(strip_tags($s->body), 0, 2500);
        }
        if ($person->biography) {
            $lines[] = 'Existing notes: '.$person->biography;
        }

        $result = $this->json(
            "Write a biography (3–6 short paragraphs) for a family archive using only these facts:\n\n".implode("\n", $lines)
            ."\n\nDo not invent anything. If facts are sparse, write a shorter biography rather than padding it.",
            ['type' => 'object', 'properties' => ['biography' => ['type' => 'string']], 'required' => ['biography'], 'additionalProperties' => false],
        );

        return $result['biography'];
    }

    /** Describe an old photo or read a document image (certificate, letter). */
    public function analyzeMedia(Media $media, array $knownPeople): array
    {
        if (! in_array($media->mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            throw new RuntimeException('AI analysis currently supports JPEG, PNG, GIF and WebP images.');
        }
        $data = base64_encode(Storage::disk('local')->get($media->path));
        $task = $media->kind === 'document'
            ? 'This is a scanned family document. Set document_type to one of certificate, letter, record, passport, deed, newspaper or other, and extract the names, dates, places and relationships it states. Transcribe legible text into transcription.'
            : 'This is a family photograph. Describe it, estimate the era from clothing, photo process and setting, and note any visible writing.';
        $context = $media->title ? "Title given by the family: {$media->title}\n" : '';
        $context .= $media->description ? "Family's caption: {$media->description}\n" : '';
        $prompt = "{$task}\n{$context}\nPeople in the archive (id: name):\n".implode("\n", array_slice($knownPeople, 0, 300))
            ."\n\nOnly suggest matched_person_id when a caption or visible text clearly names that person; never guess identity from faces.";

        return $this->json([
            ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $media->mime, 'data' => $data]],
            ['type' => 'text', 'text' => $prompt],
        ], self::mediaSchema());
    }

    /** Suggest interview questions tailored to what the archive is missing about a person. */
    public function suggestQuestions(Person $person): array
    {
        $c = $person->completeness();
        $prompt = "Suggest 8 warm, specific interview questions to ask {$person->full_name}"
            .($person->birth_date ? ' (born '.PartialDate::format($person->birth_date).($person->birthPlace ? ' in '.$person->birthPlace->label : '').')' : '')
            .' or their relatives, to preserve their life story. The archive is missing: '.implode(', ', $c['missing'] ?: ['nothing in particular']).'.';
        $result = $this->json($prompt, ['type' => 'object', 'properties' => ['questions' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['questions'], 'additionalProperties' => false]);

        return $result['questions'];
    }

    private function json(string|array $content, array $schema): array
    {
        if (! self::enabled()) {
            throw new RuntimeException('AI features need ANTHROPIC_API_KEY in .env.');
        }
        $client = new Client(apiKey: config('services.anthropic.key'));
        try {
            $message = $client->beta->messages->create(
                maxTokens: 16000,
                model: config('services.anthropic.model'),
                system: self::SYSTEM,
                messages: [['role' => 'user', 'content' => $content]],
                outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                // Route refused requests to a fallback model chosen server-side.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (RateLimitException) {
            throw new RuntimeException('The AI service is busy. Please try again in a minute.');
        } catch (APIStatusException $e) {
            throw new RuntimeException('The AI service returned an error: '.$e->getMessage());
        } catch (APIConnectionException) {
            throw new RuntimeException('Could not reach the AI service. Check your connection.');
        }

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('The AI declined to process this content.');
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $decoded = json_decode($block->text, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
        throw new RuntimeException('The AI response could not be read'.($message->stopReason === 'max_tokens' ? ' (it was too long).' : '.'));
    }

    private static function interviewSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'story_title', 'story_body', 'people', 'places', 'events', 'follow_up_questions'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'story_title' => ['type' => 'string'],
                'story_body' => ['type' => 'string'],
                'people' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['name', 'matched_person_id', 'relationship_to_interviewee', 'gender', 'birth_date', 'death_date', 'birth_place', 'occupation', 'notes'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'matched_person_id' => ['type' => ['integer', 'null']],
                        'relationship_to_interviewee' => $nullableString,
                        'gender' => ['type' => 'string', 'enum' => ['male', 'female', 'other', 'unknown']],
                        'birth_date' => $nullableString,
                        'death_date' => $nullableString,
                        'birth_place' => $nullableString,
                        'occupation' => $nullableString,
                        'notes' => $nullableString,
                    ],
                ]],
                'places' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false, 'required' => ['name', 'country', 'context'],
                    'properties' => ['name' => ['type' => 'string'], 'country' => $nullableString, 'context' => $nullableString],
                ]],
                'events' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['title', 'type', 'date', 'date_qualifier', 'place', 'from_place', 'people', 'description'],
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['migration', 'residence', 'education', 'occupation', 'military', 'religious', 'award', 'illness', 'celebration', 'custom', 'marriage', 'birth', 'death']],
                        'date' => $nullableString,
                        'date_qualifier' => ['type' => 'string', 'enum' => ['exact', 'about', 'before', 'after']],
                        'place' => $nullableString,
                        'from_place' => $nullableString,
                        'people' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'description' => $nullableString,
                    ],
                ]],
                'follow_up_questions' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    private static function mediaSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'description', 'estimated_year', 'era_reasoning', 'document_type', 'transcription', 'people', 'places', 'dates'],
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'estimated_year' => ['type' => ['integer', 'null']],
                'era_reasoning' => $nullableString,
                // one of: certificate, letter, record, passport, deed, newspaper, other — or null for photos
                'document_type' => $nullableString,
                'transcription' => $nullableString,
                'people' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false, 'required' => ['name', 'matched_person_id', 'role'],
                    'properties' => ['name' => ['type' => 'string'], 'matched_person_id' => ['type' => ['integer', 'null']], 'role' => $nullableString],
                ]],
                'places' => ['type' => 'array', 'items' => ['type' => 'string']],
                'dates' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false, 'required' => ['date', 'meaning'],
                    'properties' => ['date' => ['type' => 'string'], 'meaning' => ['type' => 'string']],
                ]],
            ],
        ];
    }
}
