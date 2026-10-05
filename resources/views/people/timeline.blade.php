<x-app-layout :title="$person->full_name.' — timeline'">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('people.show', [$family, $person]) }}" class="link text-sm">← {{ $person->full_name }}</a>
        <h1 class="mt-1 mb-6 text-3xl font-semibold">The life of {{ $person->given_name }}</h1>
        @if($items->isEmpty())
            <p class="muted">No dated events yet. Add birth, marriage and life events to build this timeline.</p>
        @else
            <x-timeline-list :items="$items" :show-age="true" />
        @endif
        @if($member->atLeast('contributor'))<a href="{{ route('events.create', [$family, 'person' => $person->id]) }}" class="btn mt-4">+ Add life event</a>@endif
    </div>
</x-app-layout>
