<x-app-layout title="Photos & files">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-semibold">{{ $person ? 'Photos of '.$person->full_name : 'Photos & files' }}</h1>
        @if($member->atLeast('contributor'))<a href="{{ route('media.create', $family) }}" class="btn btn-primary">Upload</a>@endif
    </div>
    <div class="mb-5 flex flex-wrap items-center gap-2">
        @foreach(['' => 'Everything', 'photo' => 'Photos', 'document' => 'Documents', 'audio' => 'Audio', 'video' => 'Video'] as $k => $l)
            <a href="{{ route('media.index', array_filter([$family, 'kind' => $k, 'person' => request('person')])) }}" class="badge {{ request('kind', '') === $k ? '!bg-accent !text-white' : '' }}">{{ $l }}</a>
        @endforeach
        <form class="ml-auto"><input type="hidden" name="kind" value="{{ request('kind') }}"><input name="q" value="{{ request('q') }}" placeholder="Search captions & transcriptions" class="input"></form>
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach($albums as $a)
            <a href="{{ route('albums.show', [$family, $a]) }}" class="card !px-4 !py-2 text-sm hover:border-accent/50">📁 {{ $a->title }} <span class="muted">· {{ $a->media_count }}</span></a>
        @endforeach
        @if($member->atLeast('contributor'))
            <form method="POST" action="{{ route('albums.store', $family) }}" class="flex gap-1" x-data="{ open: false }">
                @csrf
                <button type="button" x-show="!open" @click="open = true" class="btn btn-sm">+ New album</button>
                <template x-if="open"><span class="flex gap-1"><input name="title" required placeholder="Album name" class="input !py-1"><button class="btn btn-sm">Create</button></span></template>
            </form>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
        @forelse($media as $m)
            <a href="{{ route('media.show', [$family, $m]) }}" class="group">
                @if($m->isImage())
                    <img src="{{ route('media.file', [$m, 'thumb' => 1]) }}" alt="{{ $m->display_title }}" class="aspect-square w-full rounded-xl object-cover transition group-hover:opacity-90" loading="lazy">
                @else
                    <div class="grid aspect-square place-items-center rounded-xl border border-line bg-white text-center dark:border-white/10 dark:bg-white/5">
                        <div><div class="text-3xl">{{ ['audio' => '🎙️', 'video' => '🎞️', 'document' => '📄'][$m->kind] ?? '📁' }}</div><div class="muted mt-1 px-2 text-xs">{{ \Illuminate\Support\Str::limit($m->display_title, 30) }}</div></div>
                    </div>
                @endif
                <div class="mt-1 truncate text-xs">{{ $m->title ?: ($m->people->pluck('given_name')->join(', ') ?: ' ') }}</div>
            </a>
        @empty
            <p class="muted col-span-full">Nothing here yet. Old photographs are the easiest place to start: scan or photograph them with your phone.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $media->links() }}</div>
</x-app-layout>
