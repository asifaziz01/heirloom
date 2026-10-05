@props(['person', 'note' => null, 'size' => 'sm', 'highlight' => false])
<a href="{{ route('people.show', [$family, $person]) }}"
   {{ $attributes->merge(['class' => 'group flex items-center gap-3 rounded-xl border bg-white px-3 py-2 transition hover:border-accent/50 hover:shadow-sm dark:bg-white/5 '.($highlight ? 'border-accent ring-2 ring-accent/20' : 'border-line dark:border-white/10')]) }}>
    <x-avatar :person="$person" :size="$size" />
    <span class="min-w-0">
        <span class="block truncate text-sm font-semibold group-hover:text-accent">{{ $person->full_name }}</span>
        <span class="block truncate text-xs muted">
            {{ $person->lifespan }}@if($note){{ $person->lifespan ? ' · ' : '' }}{{ $note }}@endif
        </span>
    </span>
</a>
