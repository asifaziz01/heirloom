<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Story;
use App\Services\PlaceResolver;
use App\Support\PartialDate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoryController extends Controller
{
    public function index(Request $request, Family $family)
    {
        $q = $family->stories()->with(['people', 'teller', 'audio'])->latest();
        if ($personId = $request->integer('person')) {
            $q->whereHas('people', fn ($w) => $w->where('persons.id', $personId));
        }
        if ($term = $request->string('q')->trim()->value()) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('body', 'like', "%{$term}%"));
        }

        return view('stories.index', ['stories' => $q->paginate(20)->withQueryString()]);
    }

    public function create(Request $request, Family $family)
    {
        $question = $request->integer('question') ? $family->questions()->find($request->integer('question')) : null;

        return view('stories.form', [
            'story' => new Story([
                'told_by_person_id' => $request->integer('teller') ?: null,
                'title' => $question?->question,
            ]),
            'selected' => array_filter([$request->integer('person'), $question?->about_person_id]),
            'question' => $question,
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
        ]);
    }

    public function store(Request $request, Family $family)
    {
        $data = $this->validated($request, $family);
        $story = $family->stories()->create($this->attributes($request, $family, $data) + ['created_by' => $request->user()->id]);
        $story->people()->sync($data['people'] ?? []);
        if ($request->filled('question_id') && ($q = $family->questions()->find($request->integer('question_id')))) {
            $q->update(['answer_story_id' => $story->id, 'status' => 'answered']);
        }

        return redirect()->route('stories.show', [$family, $story])->with('status', 'Story saved to the family archive.');
    }

    public function show(Family $family, Story $story)
    {
        $story->load(['people', 'teller', 'place', 'audio', 'author', 'media']);

        return view('stories.show', ['story' => $story]);
    }

    public function edit(Family $family, Story $story)
    {
        $this->authorizeEdit($story);

        return view('stories.form', [
            'story' => $story->load('place'),
            'selected' => $story->people()->pluck('persons.id')->all(),
            'question' => null,
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
        ]);
    }

    public function update(Request $request, Family $family, Story $story)
    {
        $this->authorizeEdit($story);
        $data = $this->validated($request, $family);
        $story->update($this->attributes($request, $family, $data, $story));
        $story->people()->sync($data['people'] ?? []);

        return redirect()->route('stories.show', [$family, $story])->with('status', 'Story updated.');
    }

    public function destroy(Family $family, Story $story)
    {
        $this->authorizeEdit($story);
        $story->delete();

        return redirect()->route('stories.index', $family)->with('status', 'Story deleted.');
    }

    private function authorizeEdit(Story $story): void
    {
        abort_unless($this->member()->atLeast('editor') || $story->created_by === auth()->id(), 403, 'Only editors or the original author can change this story.');
    }

    private function validated(Request $request, Family $family): array
    {
        return $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:200000',
            'language' => 'nullable|string|max:8',
            'told_by_person_id' => ['nullable', Rule::exists('persons', 'id')->where('family_id', $family->id)],
            'story_date' => 'nullable|string|max:20',
            'place' => 'nullable|string|max:200',
            'people' => 'nullable|array',
            'people.*' => [Rule::exists('persons', 'id')->where('family_id', $family->id)],
            'audio' => 'nullable|file|max:204800|mimetypes:audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/webm,audio/ogg,video/webm',
        ]);
    }

    private function attributes(Request $request, Family $family, array $data, ?Story $story = null): array
    {
        $attrs = [
            'title' => $data['title'],
            'body' => $data['body'],
            'language' => $data['language'] ?? 'en',
            'told_by_person_id' => $data['told_by_person_id'] ?? null,
            'story_date' => PartialDate::normalize($data['story_date'] ?? null),
            'place_id' => PlaceResolver::resolve($family, $data['place'] ?? null),
        ];
        if ($file = $request->file('audio')) {
            $audio = $family->media()->create([
                'kind' => 'audio',
                'title' => 'Recording: '.$data['title'],
                'path' => $file->store("families/{$family->id}", 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?: 'audio/webm',
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
            $audio->people()->sync($data['people'] ?? []);
            $family->increment('storage_used', $file->getSize());
            $attrs['audio_media_id'] = $audio->id;
        }

        return $attrs;
    }
}
