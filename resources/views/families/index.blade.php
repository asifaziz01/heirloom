<x-guest-layout title="Your family archives">
    <h1 class="mb-5 text-2xl font-semibold">Your family archives</h1>
    <div class="space-y-2">
        @foreach($families as $f)
            <a href="{{ route('families.show', $f) }}" class="flex items-center justify-between rounded-xl border border-line px-4 py-3 hover:border-accent dark:border-white/10">
                <span class="font-semibold">{{ $f->name }}</span>
                <span class="muted text-sm">{{ $f->persons_count }} people · {{ $f->pivot->role }}</span>
            </a>
        @endforeach
    </div>
    <a href="{{ route('families.create') }}" class="btn mt-5 w-full">+ Start another archive</a>
</x-guest-layout>
