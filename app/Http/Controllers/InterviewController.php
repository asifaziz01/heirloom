<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Interview;
use App\Models\PersonName;
use App\Services\Governance;
use App\Services\HeritageAi;
use App\Services\PlaceResolver;
use App\Support\PartialDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

/**
 * Elder-story capture: record (with live browser transcription), extract with AI,
 * then let the family confirm each suggestion before it enters the archive.
 */
class InterviewController extends Controller
{
    public const GUIDE = [
        'Childhood' => ['Where were you born, and what do you remember about that place?', 'Who lived in your house when you were growing up?', 'What games did you play, and who were your friends?'],
        'Parents & grandparents' => ['What were your parents like? What work did they do?', 'What do you know about your grandparents?', 'Were there stories your parents often told?'],
        'Moves & migration' => ['Did the family ever move to a new town or country? Why?', 'What was the journey like, and what did you leave behind?'],
        'Work & education' => ['Where did you go to school? Who was your favourite teacher?', 'What was your first job? How did you get it?'],
        'Love & family' => ['How did you meet your husband or wife?', 'What do you remember about your wedding?', 'What was it like when your first child was born?'],
        'Traditions & values' => ['Which festivals did the family celebrate, and how?', 'Is there a family recipe or saying that should be passed on?', 'What advice would you give your great-grandchildren?'],
    ];

    public function index(Family $family)
    {
        return view('interviews.index', ['interviews' => $family->interviews()->with('person')->latest()->get()]);
    }

    public function create(Request $request, Family $family)
    {
        return view('interviews.create', [
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
            'preselect' => $request->integer('person') ?: null,
        ]);
    }

    public function store(Request $request, Family $family)
    {
        $data = $request->validate([
            'person_id' => ['nullable', Rule::exists('persons', 'id')->where('family_id', $family->id)],
            'title' => 'required|string|max:200',
            'language' => ['required', Rule::in(array_keys(PersonName::LANGUAGES))],
        ]);
        $interview = $family->interviews()->create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('interviews.show', [$family, $interview]);
    }

    public function show(Family $family, Interview $interview)
    {
        $interview->load(['person', 'audio']);

        return view('interviews.show', [
            'interview' => $interview,
            'guide' => self::GUIDE,
            'aiEnabled' => HeritageAi::enabled() && $family->hasFeature('ai'),
            'people' => $family->persons()->get()->keyBy('id'),
        ]);
    }

    /** Saves the transcript and (optionally) the recorded audio blob. */
    public function update(Request $request, Family $family, Interview $interview)
    {
        $data = $request->validate([
            'transcript' => 'nullable|string|max:500000',
            'audio' => 'nullable|file|max:204800|mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4,audio/mpeg,audio/wav,audio/x-wav',
        ]);
        $attrs = ['transcript' => $data['transcript'] ?? $interview->transcript];
        if ($file = $request->file('audio')) {
            if ($msg = $family->limitReached('storage_mb', (int) ceil($file->getSize() / 1048576))) {
                return $this->respond($request, $msg, 422);
            }
            $audio = $family->media()->create([
                'kind' => 'audio',
                'title' => 'Interview: '.$interview->title,
                'path' => $file->store("families/{$family->id}", 'local'),
                'original_name' => 'interview-'.$interview->id.'.webm',
                'mime' => $file->getMimeType() ?: 'audio/webm',
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
            if ($interview->person_id) {
                $audio->people()->sync([$interview->person_id]);
            }
            $family->increment('storage_used', $file->getSize());
            $attrs['audio_media_id'] = $audio->id;
        }
        $interview->update($attrs);

        return $this->respond($request, 'Interview saved.');
    }

    public function extract(Family $family, Interview $interview, HeritageAi $ai)
    {
        abort_unless($family->hasFeature('ai'), 403, 'AI extraction is part of the Heritage plan.');
        if (blank($interview->transcript)) {
            return back()->withErrors(['ai' => 'Add or record a transcript first.']);
        }
        $known = $family->persons()->get()->map(fn ($p) => "{$p->id}: {$p->full_name}".($p->lifespan ? " ({$p->lifespan})" : ''))->all();
        try {
            $interview->update(['extraction' => $ai->extractInterview($interview->transcript, $interview->person, $known), 'status' => 'extracted']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('status', 'Review what the AI found. Nothing is saved until you confirm.');
    }

    /** Apply only the suggestions the user ticked. */
    public function apply(Request $request, Family $family, Interview $interview)
    {
        $ex = $interview->extraction ?? [];
        $member = $this->member();
        $linked = [];
        $queued = 0;

        DB::transaction(function () use ($request, $family, $interview, $ex, $member, &$linked, &$queued) {
            foreach ($request->input('people', []) as $i => $row) {
                $suggested = $ex['people'][$i] ?? null;
                if (! $suggested || empty($row['include'])) {
                    continue;
                }
                if (! empty($suggested['matched_person_id']) && $family->persons()->whereKey($suggested['matched_person_id'])->exists()) {
                    $linked[] = (int) $suggested['matched_person_id'];

                    continue;
                }
                [$given, $surname] = $this->splitName($row['name'] ?? $suggested['name']);
                $relation = $interview->person_id ? $this->relationFor($row['relation'] ?? '', $interview->person_id) : null;
                $payload = [
                    'person' => [
                        'given_name' => $given,
                        'surname' => $surname,
                        'gender' => $suggested['gender'] ?? 'unknown',
                        'birth_date' => PartialDate::normalize($row['birth_date'] ?? $suggested['birth_date'] ?? null),
                        'death_date' => PartialDate::normalize($suggested['death_date'] ?? null),
                        'birth_place' => $suggested['birth_place'] ?? null,
                        'occupation' => $suggested['occupation'] ?? null,
                        'is_living' => empty($suggested['death_date']),
                        'biography' => $suggested['notes'] ?? null,
                    ],
                    'relation' => $relation,
                ];
                $summary = "Add {$given} {$surname} (from interview “{$interview->title}”)";
                try {
                    $outcome = Governance::perform($member, 'person.create', $summary, $payload);
                } catch (InvalidArgumentException) {
                    // e.g. a sibling of someone with no recorded parents: keep the person, skip the link
                    $outcome = Governance::perform($member, 'person.create', $summary, ['relation' => null] + $payload);
                }
                $outcome['applied'] ? $linked[] = $outcome['result']->id : $queued++;
            }

            if ($interview->person_id) {
                $linked[] = $interview->person_id;
            }

            if ($request->boolean('story.include') && ! empty($ex['story_body'])) {
                $story = $family->stories()->create([
                    'title' => $request->input('story.title', $ex['story_title'] ?? $interview->title),
                    'body' => $request->input('story.body', $ex['story_body']),
                    'language' => $interview->language,
                    'told_by_person_id' => $interview->person_id,
                    'audio_media_id' => $interview->audio_media_id,
                    'created_by' => $request->user()->id,
                ]);
                $story->people()->sync(array_unique($linked));
            }

            foreach ($request->input('events', []) as $i => $row) {
                $e = $ex['events'][$i] ?? null;
                if (! $e || empty($row['include']) || in_array($e['type'], ['birth', 'death', 'marriage'], true)) {
                    continue;
                }
                $event = $family->events()->create([
                    'type' => $e['type'],
                    'title' => $row['title'] ?? $e['title'],
                    'description' => $e['description'],
                    'date' => PartialDate::normalize($row['date'] ?? $e['date']),
                    'date_qualifier' => $e['date_qualifier'] ?? 'exact',
                    'place_id' => PlaceResolver::resolve($family, $e['place']),
                    'from_place_id' => PlaceResolver::resolve($family, $e['from_place']),
                    'created_by' => $request->user()->id,
                ]);
                $event->people()->sync(array_values(array_unique(array_filter([$interview->person_id]))));
            }

            if ($request->boolean('add_questions')) {
                foreach ($ex['follow_up_questions'] ?? [] as $q) {
                    $family->questions()->create(['question' => $q, 'about_person_id' => $interview->person_id, 'asked_by' => $request->user()->id]);
                }
            }
            $interview->update(['status' => 'applied']);
        });

        return redirect()->route('interviews.show', [$family, $interview])->with('status', 'Added to the family archive.'.($queued ? " {$queued} new people were sent for admin review." : ''));
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name));
        if (count($parts) === 1) {
            return [$parts[0], null];
        }
        $surname = array_pop($parts);

        return [implode(' ', $parts), $surname];
    }

    /** Map free-text relationships ("elder brother", "mother") to graph relations. */
    private function relationFor(string $text, int $intervieweeId): ?array
    {
        $t = mb_strtolower($text);
        $type = match (true) {
            (bool) preg_match('/\b(mother|father|parent|mom|dad|amma|abba|papa|mummy)\b/', $t) && ! str_contains($t, 'grand') && ! str_contains($t, 'in-law') => 'parent',
            (bool) preg_match('/\b(son|daughter|child)\b/', $t) && ! str_contains($t, 'grand') && ! str_contains($t, 'in-law') => 'child',
            (bool) preg_match('/\b(wife|husband|spouse|partner)\b/', $t) => 'partner',
            (bool) preg_match('/\b(brother|sister|sibling)\b/', $t) && ! str_contains($t, 'in-law') => 'sibling',
            default => null,
        };

        return $type ? ['type' => $type, 'person_id' => $intervieweeId] : null;
    }

    private function respond(Request $request, string $message, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return $status >= 400 ? back()->withErrors(['audio' => $message]) : back()->with('status', $message);
    }
}
