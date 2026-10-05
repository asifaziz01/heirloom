@php $tone = match ($p->gender) { 'male' => 'border-t-sky', 'female' => 'border-t-rose', default => 'border-t-stone-400' }; @endphp
<a href="{{ route('people.show', [$family, $p]) }}"
   class="block w-36 rounded-xl border border-t-4 border-line {{ $tone }} bg-white px-2 py-2 text-center shadow-sm transition hover:shadow-md dark:border-white/10 dark:bg-[#231d17] {{ !empty($spouse) ? 'opacity-90' : '' }}">
    <x-avatar :person="$p" size="sm" class="mx-auto mb-1" />
    <div class="truncate text-xs font-semibold" title="{{ $p->full_name }}">{{ $p->full_name }}</div>
    <div class="muted text-[11px]">{{ $p->lifespan ?: ' ' }}</div>
    @if($linkType && $linkType !== 'biological')<div class="mt-0.5 text-[10px] font-semibold text-accent uppercase">{{ $linkType }}</div>@endif
</a>
