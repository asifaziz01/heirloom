@props(['items', 'showAge' => false])
@php
    $icons = ['birth' => '🌱', 'death' => '🕯️', 'marriage' => '💍', 'divorce' => '⟂', 'migration' => '🧳', 'residence' => '🏠', 'education' => '🎓', 'occupation' => '💼', 'military' => '🎖️', 'religious' => '🕌', 'award' => '🏅', 'illness' => '⚕️', 'celebration' => '🎉', 'story' => '📖', 'photo' => '📷', 'document' => '📄', 'audio' => '🎙️', 'video' => '🎞️', 'custom' => '•'];
@endphp
<ol class="relative ml-3 border-l border-line dark:border-white/15">
    @foreach($items as $t)
        <li class="mb-4 ml-6 {{ !empty($t['relative']) ? 'opacity-70' : '' }}">
            <span class="absolute -left-3.5 grid size-7 place-items-center rounded-full border border-line bg-white text-sm dark:border-white/15 dark:bg-[#231d17]">{{ $icons[$t['type']] ?? '•' }}</span>
            <div class="flex flex-wrap items-baseline gap-x-2">
                <span class="text-sm font-semibold">{{ $t['label'] }}</span>
                @if($showAge && $t['age'] !== null)<span class="muted text-xs">age {{ $t['age'] }}</span>@endif
            </div>
            <div class="flex gap-3">
                <div class="min-w-0">
                    @if($t['url'])<a href="{{ $t['url'] }}" class="font-medium hover:text-accent">{{ $t['title'] }}</a>@else<span class="font-medium">{{ $t['title'] }}</span>@endif
                    @if($t['place'])<div class="muted text-sm">{{ $t['place'] }}</div>@endif
                    @if($t['detail'])<p class="muted text-sm">{{ $t['detail'] }}</p>@endif
                </div>
                @if(!empty($t['media']) && $t['media']->isImage())
                    <img src="{{ route('media.file', [$t['media'], 'thumb' => 1]) }}" class="size-16 rounded-lg object-cover" loading="lazy" alt="">
                @endif
            </div>
        </li>
    @endforeach
</ol>
