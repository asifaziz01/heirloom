<x-app-layout title="Search">
    <h1 class="mb-4 text-3xl font-semibold">Search</h1>
    <form class="mb-6"><input name="q" value="{{ $term }}" autofocus class="input text-lg" placeholder="Names in any script, places, words from stories or captions…"></form>
    @if($results)
        @php $total = collect($results)->sum->count(); @endphp
        <p class="muted mb-4 text-sm">{{ $total }} results for “{{ $term }}”</p>
        @if($results['people']->isNotEmpty())
            <h2 class="section-title">People</h2>
            <div class="mb-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach($results['people'] as $p)<x-person-card :person="$p" :note="$p->names->pluck('full_name')->first()" />@endforeach</div>
        @endif
        @if($results['stories']->isNotEmpty())
            <h2 class="section-title">Stories</h2>
            <div class="mb-6 space-y-2">@foreach($results['stories'] as $s)<a href="{{ route('stories.show', [$family, $s]) }}" class="card block !py-3"><strong>{{ $s->title }}</strong><p class="muted text-sm">{{ $s->excerpt(160) }}</p></a>@endforeach</div>
        @endif
        @if($results['media']->isNotEmpty())
            <h2 class="section-title">Photos & files</h2>
            <div class="mb-6 grid grid-cols-3 gap-2 sm:grid-cols-6">@foreach($results['media'] as $m)<a href="{{ route('media.show', [$family, $m]) }}" class="text-xs">@if($m->isImage())<img src="{{ route('media.file', [$m, 'thumb' => 1]) }}" class="aspect-square w-full rounded-lg object-cover" alt="">@else<div class="grid aspect-square place-items-center rounded-lg bg-paper-2 dark:bg-white/5">{{ ucfirst($m->kind) }}</div>@endif<span class="block truncate">{{ $m->display_title }}</span></a>@endforeach</div>
        @endif
        @if($results['events']->isNotEmpty())
            <h2 class="section-title">Events</h2>
            <div class="mb-6">@foreach($results['events'] as $e)<p class="text-sm"><a class="link" href="{{ route('events.edit', [$family, $e]) }}">{{ $e->title }}</a> <span class="muted">{{ \App\Support\PartialDate::format($e->date) }}</span></p>@endforeach</div>
        @endif
        @if($results['places']->isNotEmpty())
            <h2 class="section-title">Places</h2>
            <p class="text-sm">@foreach($results['places'] as $pl)<a class="badge mr-1" href="{{ route('places.index', $family) }}">{{ $pl->label }}</a>@endforeach</p>
        @endif
    @endif
</x-app-layout>
