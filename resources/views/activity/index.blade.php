<x-app-layout title="Audit history">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-semibold">Audit history</h1>
            <p class="muted">Every change to the archive: who made it, and what changed.</p>
        </div>
        <form><select name="type" class="input" onchange="this.form.submit()"><option value="">All records</option>@foreach($types as $t)<option @selected(request('type') === $t)>{{ $t }}</option>@endforeach</select></form>
    </div>
    <div class="card divide-y divide-line !p-0 dark:divide-white/10">
        @forelse($logs as $log)
            <div class="px-5 py-2.5 text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <span><strong>{{ $log->user?->name ?? 'System' }}</strong> <span class="badge">{{ $log->action }}</span> {{ $log->description }}</span>
                    <span class="muted text-xs" title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</span>
                </div>
                @if($log->changes)
                    <div class="muted mt-1 text-xs">
                        @foreach($log->changes as $field => $c)
                            <div>{{ str_replace('_', ' ', $field) }}: <del>{{ \Illuminate\Support\Str::limit(is_scalar($c['from']) ? (string) $c['from'] : json_encode($c['from']), 60) ?: '—' }}</del> → {{ \Illuminate\Support\Str::limit(is_scalar($c['to']) ? (string) $c['to'] : json_encode($c['to']), 60) ?: '—' }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="muted p-5">No activity yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-app-layout>
