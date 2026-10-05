<x-app-layout title="Review queue">
    <h1 class="mb-1 text-3xl font-semibold">Review queue</h1>
    <p class="muted mb-6">Contributors’ changes to people and relationships wait here. Approving applies the change; rejecting discards it.</p>
    <section class="space-y-3">
        @forelse($pending as $cr)
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <span class="badge">{{ \App\Services\Governance::ACTIONS[$cr->action] ?? $cr->action }}</span>
                        <p class="mt-1 font-semibold">{{ $cr->summary }}</p>
                        <p class="muted text-xs">Suggested by {{ $cr->user->name }} · {{ $cr->created_at->diffForHumans() }}</p>
                    </div>
                    <form method="POST" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input name="note" class="input !w-56" placeholder="Note to contributor (optional)">
                        <button formaction="{{ route('review.approve', [$family, $cr]) }}" class="btn btn-primary btn-sm">Approve</button>
                        <button formaction="{{ route('review.reject', [$family, $cr]) }}" class="btn btn-danger btn-sm">Reject</button>
                    </form>
                </div>
                @php $details = collect($cr->payload['person'] ?? $cr->payload)->except(['names', 'relation'])->filter(fn ($v) => filled($v) && ! is_array($v)); @endphp
                @if($details->isNotEmpty())
                    <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                        @foreach($details as $k => $v)
                            <div><dt class="muted inline text-xs">{{ str_replace('_', ' ', $k) }}:</dt> <dd class="inline">{{ \Illuminate\Support\Str::limit((string) $v, 120) }}</dd></div>
                        @endforeach
                    </dl>
                @endif
            </div>
        @empty
            <div class="card muted">Nothing waiting for review. 🎉</div>
        @endforelse
    </section>
    @if($recent->isNotEmpty())
        <h2 class="section-title mt-8">Recently reviewed</h2>
        <div class="card divide-y divide-line !p-0 dark:divide-white/10">
            @foreach($recent as $cr)
                <div class="flex flex-wrap justify-between gap-2 px-5 py-2 text-sm">
                    <span>{{ $cr->summary }} <span class="muted">by {{ $cr->user->name }}</span></span>
                    <span class="{{ $cr->status === 'approved' ? 'text-moss' : 'text-red-700' }}">{{ ucfirst($cr->status) }} by {{ $cr->reviewer?->name }}{{ $cr->review_note ? ': “'.$cr->review_note.'”' : '' }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
