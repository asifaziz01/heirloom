<x-app-layout :title="$story->exists ? 'Edit story' : 'New story'">
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-4 text-2xl font-semibold">{{ $story->exists ? 'Edit story' : 'Write a family story' }}</h1>
        @if($question)
            <div class="mb-4 rounded-xl bg-accent-soft/60 px-4 py-3 text-sm dark:bg-white/5">Answering: <strong>{{ $question->question }}</strong></div>
        @endif
        <form method="POST" enctype="multipart/form-data" action="{{ $story->exists ? route('stories.update', [$family, $story]) : route('stories.store', $family) }}" class="card space-y-4">
            @csrf
            @if($story->exists) @method('PUT') @endif
            @if($question)<input type="hidden" name="question_id" value="{{ $question->id }}">@endif
            <div><label class="label">Title</label><input name="title" value="{{ old('title', $story->title) }}" required class="input text-lg" dir="auto" placeholder="The year we moved to Lucknow"></div>
            <div>
                <label class="label">The story</label>
                <textarea name="body" rows="14" required class="input leading-7" dir="auto" placeholder="Write it the way it is told at family gatherings…">{{ old('body', $story->body) }}</textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">As told by</label>
                    <x-people-picker :people="$people" name="told_by_person_id" :selected="array_filter([old('told_by_person_id', $story->told_by_person_id)])" :multiple="false" placeholder="Who tells this story?" />
                </div>
                <div><label class="label">When it happened</label><input name="story_date" value="{{ old('story_date', $story->story_date) }}" class="input" placeholder="1972 or 1972-06"></div>
                <div><label class="label">Where</label><input name="place" value="{{ old('place', $story->place?->name) }}" class="input" placeholder="Lucknow, India"></div>
                <div>
                    <label class="label">Language</label>
                    <select name="language" class="input">@foreach(\App\Models\PersonName::LANGUAGES as $v => $l)<option value="{{ $v }}" @selected(old('language', $story->language ?? $family->primary_language) === $v)>{{ $l }}</option>@endforeach</select>
                </div>
            </div>
            <div><label class="label">Who is this story about?</label><x-people-picker :people="$people" :selected="old('people', $selected)" /></div>
            <div><label class="label">Audio recording <span class="normal-case">(optional)</span></label><input type="file" name="audio" accept="audio/*" class="input"></div>
            <div class="flex gap-2">
                <button class="btn btn-primary">Save story</button>
                <a href="{{ url()->previous() }}" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
