<x-app-layout title="Sources & evidence">
    <h1 class="mb-1 text-3xl font-semibold">Sources & evidence</h1>
    <p class="muted mb-6">What we know, and how we know it. Facts are added from each person’s profile.</p>

    @if($disputed->isNotEmpty())
        <section class="card mb-6 border-red-200 dark:border-red-400/30">
            <h2 class="section-title">Disputed facts</h2>
            @foreach($disputed as $f)
                <p class="py-1 text-sm"><a class="link" href="{{ route('people.show', [$family, $f->person]) }}#facts">{{ $f->person->full_name }}</a>: {{ \App\Models\Fact::FIELDS[$f->field] }} = <strong>{{ $f->displayValue() }}</strong> <span class="muted">({{ $f->source?->title ?? 'no source' }})</span></p>
            @endforeach
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card">
            <h2 class="section-title">Sources</h2>
            @forelse($sources as $s)
                <details class="border-b border-line py-2 last:border-0 dark:border-white/10">
                    <summary class="flex cursor-pointer justify-between text-sm"><span><strong>{{ $s->title }}</strong> <span class="badge">{{ \App\Models\Source::TYPES[$s->type] ?? $s->type }}</span></span><span class="muted">{{ $s->facts_count }} facts</span></summary>
                    @if($s->citation)<p class="muted mt-1 text-sm">{{ $s->citation }}</p>@endif
                    @if($s->media)<a href="{{ route('media.show', [$family, $s->media]) }}" class="link text-sm">View document</a>@endif
                    <ul class="mt-1 text-sm">
                        @foreach($s->facts as $f)
                            <li>{{ $f->person->full_name }}: {{ \App\Models\Fact::FIELDS[$f->field] }} = {{ $f->displayValue() }} <span class="muted">({{ $f->status }})</span></li>
                        @endforeach
                    </ul>
                </details>
            @empty
                <p class="muted text-sm">No sources yet.</p>
            @endforelse
        </section>
        <section class="card">
            <h2 class="section-title">Facts without a source</h2>
            @forelse($unsourced as $f)
                <p class="py-1 text-sm"><a class="link" href="{{ route('people.show', [$family, $f->person]) }}#facts">{{ $f->person->full_name }}</a>: {{ \App\Models\Fact::FIELDS[$f->field] }} = {{ $f->displayValue() }}</p>
            @empty
                <p class="muted text-sm">Every recorded fact has a source.</p>
            @endforelse
        </section>
    </div>
</x-app-layout>
