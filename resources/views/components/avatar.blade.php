@props(['person', 'size' => 'md'])
@php
    $cls = ['xs' => 'size-6 text-[10px]', 'sm' => 'size-9 text-xs', 'md' => 'size-12 text-sm', 'lg' => 'size-20 text-xl', 'xl' => 'size-28 text-3xl'][$size];
    $tone = match ($person?->gender) { 'male' => 'bg-[#dfe7ef] text-sky', 'female' => 'bg-[#f2e1e4] text-rose', default => 'bg-paper-2 text-ink-soft' };
@endphp
@if($person?->photo_media_id && $person->photo)
    <img src="{{ route('media.file', [$person->photo_media_id, 'thumb' => 1]) }}" alt="{{ $person->full_name }}" {{ $attributes->merge(['class' => "$cls shrink-0 rounded-full object-cover ring-2 ring-white dark:ring-white/10"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$cls $tone shrink-0 grid place-items-center rounded-full font-semibold ring-2 ring-white dark:ring-white/10"]) }} aria-hidden="true">{{ $person?->initials ?: '?' }}</span>
@endif
