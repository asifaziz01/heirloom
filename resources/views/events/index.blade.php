<x-app-layout title="Life events">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-semibold">Life events</h1>
        @if($member->atLeast('contributor'))<a href="{{ route('events.create', $family) }}" class="btn btn-primary">+ Add event</a>@endif
    </div>
    <div class="mb-4 flex flex-wrap gap-1.5">
        <a href="{{ route('events.index', $family) }}" class="badge {{ !request('type') ? '!bg-accent !text-white' : '' }}">All</a>
        @foreach(\App\Models\Event::TYPES as $v => $l)
            <a href="{{ route('events.index', [$family, 'type' => $v]) }}" class="badge {{ request('type') === $v ? '!bg-accent !text-white' : '' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="card divide-y divide-line !p-0 dark:divide-white/10">
        @forelse($events as $e)
            <div class="flex flex-wrap items-start gap-4 px-5 py-3">
                <div class="w-24 shrink-0 text-sm font-semibold">{{ \App\Support\PartialDate::format($e->date, $e->date_qualifier) ?: '—' }}</div>
                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ $e->title }} <span class="badge ml-1">{{ \App\Models\Event::TYPES[$e->type] ?? $e->type }}</span></div>
                    <div class="muted text-sm">
                        @if($e->type === 'migration' && $e->fromPlace){{ $e->fromPlace->label }} → @endif{{ $e->place?->label }}
                        @if($e->people->isNotEmpty()) · {{ $e->people->pluck('full_name')->join(', ') }}@endif
                    </div>
                    @if($e->description)<p class="muted mt-1 text-sm">{{ \Illuminate\Support\Str::limit($e->description, 200) }}</p>@endif
                </div>
                @if($member->atLeast('contributor'))<a href="{{ route('events.edit', [$family, $e]) }}" class="link text-sm">Edit</a>@endif
            </div>
        @empty
            <p class="muted p-5">No events yet: moves, schooling, jobs, military service, pilgrimages, awards. These make the timeline come alive.</p>
        @endforelse
    </div>
</x-app-layout>
