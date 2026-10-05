<x-app-layout title="Upload">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-1 text-2xl font-semibold">Add photos, documents & recordings</h1>
        <p class="muted mb-5 text-sm">Take a photo, tag who’s in it, add a rough date. That’s all it takes.</p>
        <form method="POST" action="{{ route('media.store', $family) }}" enctype="multipart/form-data" class="card space-y-4" x-data="{ count: 0 }">
            @csrf
            <label class="block cursor-pointer rounded-xl border-2 border-dashed border-line p-8 text-center hover:border-accent dark:border-white/15">
                <input type="file" name="files[]" multiple required class="sr-only" accept="image/*,video/*,audio/*,application/pdf" @change="count = $event.target.files.length">
                <div class="text-3xl">📷</div>
                <div class="mt-2 font-semibold" x-text="count ? count + ' file(s) selected' : 'Choose files or take a photo'"></div>
                <div class="muted text-xs">Photos, scanned documents (PDF), audio and video, up to 200 MB each</div>
            </label>
            <div><label class="label">Who is in it?</label><x-people-picker :people="$people" :selected="array_filter([$preselect])" /></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label">Title</label><input name="title" class="input" placeholder="Nani’s wedding"></div>
                <div><label class="label">Approximate date</label><input name="taken_date" class="input" placeholder="1958 or 1958-05"></div>
                <div><label class="label">Place</label><input name="place" class="input" placeholder="Gorakhpur"></div>
                <div><label class="label">Album</label><select name="album_id" class="input"><option value="">—</option>@foreach($albums as $a)<option value="{{ $a->id }}">{{ $a->title }}</option>@endforeach</select></div>
                <div class="sm:col-span-2">
                    <label class="label">Is this a document?</label>
                    <select name="document_type" class="input"><option value="">No, it’s a photo / recording</option>@foreach(\App\Models\Media::DOCUMENT_TYPES as $v => $l)<option value="{{ $v }}" @selected($kind === 'document' && $v === 'certificate')>{{ $l }}</option>@endforeach</select>
                </div>
            </div>
            <div><label class="label">Description</label><textarea name="description" rows="3" class="input" dir="auto" placeholder="Who, where, what was happening…"></textarea></div>
            <button class="btn btn-primary">Upload</button>
        </form>
    </div>
</x-app-layout>
