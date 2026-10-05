<x-app-layout :title="$album->title">
    <a href="{{ route('media.index', $family) }}" class="link text-sm">← Photos & files</a>
    <h1 class="mt-1 text-3xl font-semibold">{{ $album->title }}</h1>
    @if($album->description)<p class="muted">{{ $album->description }}</p>@endif
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
        @forelse($media as $m)
            <a href="{{ route('media.show', [$family, $m]) }}">
                @if($m->isImage())
                    <img src="{{ route('media.file', [$m, 'thumb' => 1]) }}" class="aspect-square w-full rounded-xl object-cover" loading="lazy" alt="{{ $m->display_title }}">
                @else
                    <div class="grid aspect-square place-items-center rounded-xl bg-paper-2 text-sm dark:bg-white/5">{{ $m->display_title }}</div>
                @endif
            </a>
        @empty
            <p class="muted col-span-full">This album is empty. Choose it when uploading.</p>
        @endforelse
    </div>
</x-app-layout>
