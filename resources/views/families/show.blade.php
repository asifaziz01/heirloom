<x-app-layout :title="$family->name">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="muted text-sm">{{ now()->format('l, j F Y') }}</p>
            <h1 class="text-3xl font-semibold">{{ $family->name }}</h1>
            @if($family->description)<p class="muted mt-1 max-w-2xl">{{ $family->description }}</p>@endif
        </div>
        @if($member->atLeast('contributor'))
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('media.create', $family) }}" class="btn">Upload photos</a>
                <a href="{{ route('stories.create', $family) }}" class="btn">Write a story</a>
                <a href="{{ route('interviews.create', $family) }}" class="btn btn-primary">Record an elder</a>
            </div>
        @endif
    </div>

    <div class="mb-6 grid grid-cols-3 gap-3 md:grid-cols-6">
        @foreach(['people' => [route('people.index', $family), 'People'], 'photos' => [route('media.index', [$family, 'kind' => 'photo']), 'Photos'], 'stories' => [route('stories.index', $family), 'Stories'], 'documents' => [route('media.index', [$family, 'kind' => 'document']), 'Documents'], 'places' => [route('places.index', $family), 'Places'], 'members' => [$member->atLeast('admin') ? route('members.index', $family) : '#', 'Members']] as $k => [$url, $label])
            <a href="{{ $url }}" class="card !p-4 hover:border-accent/50">
                <div class="font-serif text-2xl font-semibold">{{ number_format($stats[$k]) }}</div>
                <div class="muted text-xs">{{ $label }}</div>
            </a>
        @endforeach
    </div>

    @if($stats['people'] < 5)
        <div class="card mb-6 border-accent/30 bg-accent-soft/50">
            <h2 class="section-title">Getting started</h2>
            <ol class="grid gap-3 text-sm md:grid-cols-4">
                <li><a class="link font-semibold" href="{{ $me ? route('people.show', [$family, $me]) : route('people.create', $family) }}">1. Add your parents & partner</a><p class="muted">Open your profile and use “Add relative”.</p></li>
                <li><a class="link font-semibold" href="{{ route('export.index', $family) }}">2. Import an existing tree</a><p class="muted">Have a GEDCOM file? Bring it in.</p></li>
                <li><a class="link font-semibold" href="{{ route('members.index', $family) }}">3. Invite relatives</a><p class="muted">The archive grows fastest together.</p></li>
                <li><a class="link font-semibold" href="{{ route('interviews.create', $family) }}">4. Record a story</a><p class="muted">Start with the oldest relative you can reach.</p></li>
            </ol>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if($pendingReviews)
                <a href="{{ route('review.index', $family) }}" class="card flex items-center justify-between border-accent/40 hover:bg-accent-soft/40">
                    <span><strong>{{ $pendingReviews }}</strong> contribution{{ $pendingReviews > 1 ? 's' : '' }} waiting for your review</span>
                    <span class="btn btn-sm btn-primary">Review</span>
                </a>
            @endif

            <section class="card">
                <h2 class="section-title">Coming up <span class="muted font-sans text-xs font-normal">next 30 days</span></h2>
                @forelse($upcoming as $u)
                    <div class="flex items-center gap-3 border-b border-line py-2 last:border-0 dark:border-white/10">
                        <div class="w-14 shrink-0 text-center">
                            <div class="text-xs uppercase muted">{{ $u['when']->format('M') }}</div>
                            <div class="font-serif text-xl font-semibold leading-none">{{ $u['when']->format('j') }}</div>
                        </div>
                        <div class="min-w-0 text-sm">
                            @if($u['type'] === 'birthday')
                                🎂 <a class="link font-semibold" href="{{ route('people.show', [$family, $u['person']]) }}">{{ $u['person']->full_name }}</a> turns {{ $u['years'] }}
                            @elseif($u['type'] === 'anniversary')
                                💍 {{ $u['partnership']->partner1?->given_name }} & {{ $u['partnership']->partner2?->given_name }} — {{ $u['years'] }} years together
                            @else
                                🕯️ Remembering <a class="link font-semibold" href="{{ route('people.show', [$family, $u['person']]) }}">{{ $u['person']->full_name }}</a> — {{ $u['years'] }} years
                            @endif
                            <span class="muted">· {{ $u['when']->isToday() ? 'today' : $u['when']->diffForHumans(['parts' => 1]) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="muted text-sm">No birthdays or anniversaries in the next month. Add full birth dates and wedding dates to get reminders here.</p>
                @endforelse
            </section>

            @if($onThisDay->isNotEmpty())
                <section class="card">
                    <h2 class="section-title">On this day</h2>
                    @foreach($onThisDay as $o)
                        <p class="text-sm"><span class="font-semibold">{{ $o['year'] }}</span> — <a class="link" href="{{ route('people.show', [$family, $o['person']]) }}">{{ $o['text'] }}</a></p>
                    @endforeach
                </section>
            @endif

            <section class="card">
                <h2 class="section-title">Latest stories <a href="{{ route('stories.index', $family) }}" class="link font-sans text-sm font-normal">All stories</a></h2>
                @forelse($recentStories as $s)
                    <a href="{{ route('stories.show', [$family, $s]) }}" class="block border-b border-line py-3 last:border-0 dark:border-white/10">
                        <div class="font-serif text-lg font-semibold">{{ $s->title }}</div>
                        <p class="muted text-sm">{{ $s->excerpt(180) }}</p>
                        @if($s->people->isNotEmpty())<p class="mt-1 text-xs muted">About {{ $s->people->pluck('given_name')->join(', ') }}</p>@endif
                    </a>
                @empty
                    <p class="muted text-sm">No stories yet. The best time to record Grandma’s stories is now. <a class="link" href="{{ route('interviews.create', $family) }}">Start an interview</a>.</p>
                @endforelse
            </section>

            @if($recentPhotos->isNotEmpty())
                <section class="card">
                    <h2 class="section-title">Recent photos <a href="{{ route('media.index', [$family, 'kind' => 'photo']) }}" class="link font-sans text-sm font-normal">Gallery</a></h2>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach($recentPhotos as $m)
                            <a href="{{ route('media.show', [$family, $m]) }}"><img src="{{ route('media.file', [$m, 'thumb' => 1]) }}" alt="{{ $m->display_title }}" class="aspect-square w-full rounded-lg object-cover" loading="lazy"></a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="space-y-6">
            @if($questions->isNotEmpty())
                <section class="card">
                    <h2 class="section-title">Questions for the family</h2>
                    @foreach($questions as $q)
                        <div class="border-b border-line py-2 text-sm last:border-0 dark:border-white/10">
                            <p>{{ $q->question }}</p>
                            <div class="mt-1 flex items-center justify-between">
                                <span class="muted text-xs">{{ $q->about ? 'About '.$q->about->given_name : '' }}</span>
                                @if($member->atLeast('contributor'))<a href="{{ route('questions.answer', [$family, $q]) }}" class="link text-xs">Answer →</a>@endif
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif

            @if($needsLove->isNotEmpty())
                <section class="card">
                    <h2 class="section-title">Help complete the archive</h2>
                    <div class="space-y-2">
                        @foreach($needsLove as $n)
                            <x-person-card :person="$n['person']" :note="'missing '.implode(', ', array_slice($n['c']['missing'], 0, 2))" />
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="card">
                <h2 class="section-title">Recent activity</h2>
                @forelse($activity as $a)
                    <p class="py-1 text-sm"><span class="font-medium">{{ $a->user?->name ?? 'System' }}</span> <span class="muted">{{ $a->action }}</span> {{ $a->description }} <span class="muted text-xs">· {{ $a->created_at->diffForHumans() }}</span></p>
                @empty
                    <p class="muted text-sm">Nothing yet.</p>
                @endforelse
            </section>
        </div>
    </div>
</x-app-layout>
