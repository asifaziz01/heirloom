@props(['title' => null])
<x-layouts.base :title="$title">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-8 flex items-center gap-2">
            <span class="grid size-9 place-items-center rounded-lg bg-accent font-serif text-lg text-white">H</span>
            <span class="font-serif text-2xl font-semibold">Heirloom</span>
        </a>
        <div class="card w-full max-w-md p-7">
            @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-500/10 dark:text-red-200">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif
            {{ $slot }}
        </div>
        <p class="muted mt-6 max-w-md text-center text-xs">Private by default. No advertising. Your family owns its data and can export everything at any time.</p>
    </div>
</x-layouts.base>
