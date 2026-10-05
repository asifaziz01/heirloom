<x-app-layout title="New interview">
    <div class="mx-auto max-w-xl">
        <h1 class="mb-4 text-2xl font-semibold">New interview</h1>
        <form method="POST" action="{{ route('interviews.store', $family) }}" class="card space-y-4">
            @csrf
            <div><label class="label">Who are you interviewing?</label><x-people-picker :people="$people" name="person_id" :selected="array_filter([$preselect])" :multiple="false" /><p class="help">Add them to the tree first if they’re not there yet.</p></div>
            <div><label class="label">Title</label><input name="title" required class="input" value="{{ old('title', 'Conversation, '.now()->format('j M Y')) }}"></div>
            <div>
                <label class="label">Language spoken</label>
                <select name="language" class="input">@foreach(\App\Models\PersonName::LANGUAGES as $v => $l)<option value="{{ $v }}" @selected($v === $family->primary_language)>{{ $l }}</option>@endforeach</select>
                <p class="help">Used for live transcription. Hindi, Urdu and many other languages are supported in Chrome and Edge.</p>
            </div>
            <button class="btn btn-primary">Start</button>
        </form>
    </div>
</x-app-layout>
