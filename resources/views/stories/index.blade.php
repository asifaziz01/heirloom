<x-app-layout title="Stories">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-semibold">Family stories</h1>
        <div class="flex gap-2">
            <form><input name="q" value="{{ request('q') }}" placeholder="Search stories…" class="input"></form>
            @if($member->atLeast('contributor'))<a href="{{ route('stories.create', $family) }}" class="btn btn-primary">+ Write a story</a>@endif
        </div>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($stories as $s)
            <a href="{{ route('stories.show', [$family, $s]) }}" class="card block hover:border-accent/50">
                <h2 class="font-serif text-xl font-semibold">{{ $s->title }}</h2>
                <p class="muted mt-1 text-xs">
                    {{ $s->teller ? 'As told by '.$s->teller->full_name : '' }}{{ $s->teller && $s->story_date ? ' · ' : '' }}{{ \App\Support\PartialDate::format($s->story_date) }}
                    @if($s->audio) · 🎙️ recording @endif
                </p>
                <p class="mt-2 text-sm leading-relaxed" dir="auto">{{ $s->excerpt(240) }}</p>
                @if($s->people->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-1">@foreach($s->people->take(5) as $p)<span class="badge">{{ $p->given_name }}</span>@endforeach</div>
                @endif
            </a>
        @empty
            <div class="card md:col-span-2">
                <p class="font-serif text-xl">“Our grandparents know all these stories, but nobody has recorded them.”</p>
                <p class="muted mt-2">Start with one memory. Or <a class="link" href="{{ route('interviews.create', $family) }}">record an elder</a> and let Heirloom help write it up.</p>
            </div>
        @endforelse
    </div>
    <div class="mt-6">{{ $stories->links() }}</div>
</x-app-layout>
