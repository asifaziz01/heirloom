@php $aiStatus = $media->aiStatus(); $ai = $aiStatus === 'done' ? $media->ai_suggestions : null; $canEdit = $member->atLeast('contributor'); @endphp
<x-app-layout :title="$media->display_title">
    <div class="mb-3 flex items-center justify-between">
        <a href="{{ route('media.index', $family) }}" class="link text-sm">← Photos & files</a>
        <div class="flex gap-2">
            @if($prev)<a href="{{ route('media.show', [$family, $prev]) }}" class="btn btn-sm">← Prev</a>@endif
            @if($next)<a href="{{ route('media.show', [$family, $next]) }}" class="btn btn-sm">Next →</a>@endif
        </div>
    </div>
    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
        <div>
            <div class="card grid min-h-80 place-items-center !p-2">
                @if($media->isImage())
                    <img src="{{ route('media.file', $media) }}" alt="{{ $media->display_title }}" class="max-h-[75vh] rounded-lg">
                @elseif($media->kind === 'audio')
                    <audio controls src="{{ route('media.file', $media) }}" class="w-full"></audio>
                @elseif($media->kind === 'video')
                    <video controls src="{{ route('media.file', $media) }}" class="max-h-[75vh] w-full rounded-lg"></video>
                @elseif($media->mime === 'application/pdf')
                    <iframe src="{{ route('media.file', $media) }}" class="h-[75vh] w-full rounded-lg" title="{{ $media->display_title }}"></iframe>
                @else
                    <a href="{{ route('media.file', [$media, 'download' => 1]) }}" class="btn">Download {{ $media->original_name }}</a>
                @endif
            </div>
            <p class="muted mt-2 text-xs">{{ $media->original_name }} · {{ number_format($media->size / 1048576, 1) }} MB · uploaded by {{ $media->uploader?->name ?? '—' }} {{ $media->created_at->diffForHumans() }} · <a class="link" href="{{ route('media.file', [$media, 'download' => 1]) }}">download original</a></p>

            @if(in_array($aiStatus, ['pending', 'running']))
                <x-job-status :url="route('media.status', [$family, $media])" :title="$media->kind === 'document' ? 'Reading this document…' : 'Looking at this photo…'" class="mt-5" />
            @elseif($ai)
                <div class="card mt-5 border-accent/40">
                    <h2 class="section-title">✨ AI suggestions <span class="muted font-sans text-xs font-normal">check before using</span></h2>
                    <p class="font-semibold">{{ $ai['title'] ?? '' }}</p>
                    <p class="mt-1 text-sm">{{ $ai['description'] ?? '' }}</p>
                    @if(!empty($ai['estimated_year']))<p class="muted mt-2 text-sm">Estimated year: <strong>{{ $ai['estimated_year'] }}</strong>{{ !empty($ai['era_reasoning']) ? ' — '.$ai['era_reasoning'] : '' }}</p>@endif
                    @if(!empty($ai['people']))<p class="mt-2 text-sm">Names mentioned: {{ collect($ai['people'])->map(fn ($p) => $p['name'].(!empty($p['role']) ? ' ('.$p['role'].')' : ''))->join(', ') }}</p>@endif
                    @if(!empty($ai['dates']))<p class="mt-1 text-sm">Dates: {{ collect($ai['dates'])->map(fn ($d) => $d['date'].' — '.$d['meaning'])->join('; ') }}</p>@endif
                    @if(!empty($ai['places']))<p class="mt-1 text-sm">Places: {{ implode(', ', $ai['places']) }}</p>@endif
                    @if(!empty($ai['transcription']))<details class="mt-2"><summary class="link cursor-pointer text-sm">Transcribed text</summary><p class="mt-2 text-sm whitespace-pre-line" dir="auto">{{ $ai['transcription'] }}</p></details>@endif
                    @if($canEdit)
                        <button type="button" class="btn btn-sm mt-3" onclick="
                            const f = document.getElementById('media-form');
                            if (!f.title.value) f.title.value = @js($ai['title'] ?? '');
                            if (!f.description.value) f.description.value = @js($ai['description'] ?? '');
                            if (!f.taken_date.value && @js($ai['estimated_year'] ?? null)) f.taken_date.value = @js((string) ($ai['estimated_year'] ?? ''));
                            if (f.transcription && !f.transcription.value) f.transcription.value = @js($ai['transcription'] ?? '');
                            f.scrollIntoView({ behavior: 'smooth' });">Copy into the form</button>
                        <span class="muted ml-2 text-xs">Tag people yourself; AI never guesses who is in a photo.</span>
                    @endif
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <form id="media-form" method="POST" action="{{ route('media.update', [$family, $media]) }}" class="card space-y-3">
                @csrf @method('PUT')
                <fieldset @disabled(! $canEdit) class="space-y-3">
                    <div><label class="label">Title</label><input name="title" value="{{ $media->title }}" class="input" dir="auto"></div>
                    <div><label class="label">People</label><x-people-picker :people="$people" :selected="$media->people->pluck('id')->all()" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="label">Date</label><input name="taken_date" value="{{ $media->taken_date }}" class="input" placeholder="1958"></div>
                        <div><label class="label">Place</label><input name="place" value="{{ $media->place?->name }}" class="input"></div>
                    </div>
                    <div><label class="label">Album</label><select name="album_id" class="input"><option value="">—</option>@foreach($albums as $a)<option value="{{ $a->id }}" @selected($media->album_id === $a->id)>{{ $a->title }}</option>@endforeach</select></div>
                    @if($media->kind === 'document')
                        <div><label class="label">Document type</label><select name="document_type" class="input">@foreach(\App\Models\Media::DOCUMENT_TYPES as $v => $l)<option value="{{ $v }}" @selected($media->document_type === $v)>{{ $l }}</option>@endforeach</select></div>
                    @endif
                    <div><label class="label">Description</label><textarea name="description" rows="4" class="input" dir="auto">{{ $media->description }}</textarea></div>
                    @if(in_array($media->kind, ['document', 'audio', 'video']))
                        <div><label class="label">Transcription</label><textarea name="transcription" rows="5" class="input" dir="auto">{{ $media->transcription }}</textarea></div>
                    @endif
                    @if($canEdit)<button class="btn btn-primary w-full">Save</button>@endif
                </fieldset>
            </form>

            @if($canEdit && $media->isImage())
                @if($family->hasFeature('ai') && \App\Services\HeritageAi::enabled() && ! in_array($aiStatus, ['pending', 'running']))
                    <form method="POST" action="{{ route('media.analyze', [$family, $media]) }}" class="card">
                        @csrf
                        <p class="mb-2 text-sm">{{ $media->kind === 'document' ? 'Read names, dates and places from this document.' : 'Describe this photo and estimate when it was taken.' }}</p>
                        @if($aiStatus === 'failed')
                            <p class="mb-2 text-sm text-red-700 dark:text-red-300">Last attempt failed: {{ $media->ai_suggestions['error'] ?? 'unknown error' }}</p>
                        @endif
                        <button class="btn w-full">✨ Analyse with AI</button>
                    </form>
                @endif
                @foreach($media->people as $p)
                    @if($p->photo_media_id !== $media->id)
                        <form method="POST" action="{{ route('people.photo', [$family, $p]) }}">@csrf<input type="hidden" name="media_id" value="{{ $media->id }}"><button class="link text-xs">Use as {{ $p->given_name }}’s profile photo</button></form>
                    @endif
                @endforeach
            @endif
            @if($member->canManage($media->uploaded_by))
                <form method="POST" action="{{ route('media.destroy', [$family, $media]) }}" onsubmit="return confirm('Delete this file permanently?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm w-full">Delete file</button></form>
            @endif
        </div>
    </div>
</x-app-layout>
