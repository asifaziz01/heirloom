<x-app-layout title="Interviews">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-semibold">Record an elder</h1>
            <p class="muted max-w-2xl">Many of the family’s most valuable memories exist only with older relatives. Sit with them, press record, and ask. Heirloom transcribes and helps turn the conversation into stories, people and timeline events. You confirm everything.</p>
        </div>
        <a href="{{ route('interviews.create', $family) }}" class="btn btn-primary">+ New interview</a>
    </div>
    <div class="card divide-y divide-line !p-0 dark:divide-white/10">
        @forelse($interviews as $i)
            <a href="{{ route('interviews.show', [$family, $i]) }}" class="flex items-center justify-between px-5 py-3 hover:bg-paper-2 dark:hover:bg-white/5">
                <span><span class="font-semibold">{{ $i->title }}</span> <span class="muted text-sm">{{ $i->person ? '· '.$i->person->full_name : '' }} · {{ $i->created_at->format('j M Y') }}</span></span>
                <span class="badge">{{ ['draft' => 'Draft', 'extracted' => 'Ready to review', 'applied' => 'Added to archive'][$i->status] ?? $i->status }}</span>
            </a>
        @empty
            <p class="muted p-5">No interviews yet. Who is the oldest relative you could call this week?</p>
        @endforelse
    </div>
</x-app-layout>
