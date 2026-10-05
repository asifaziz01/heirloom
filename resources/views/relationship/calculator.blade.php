<x-app-layout title="How are we related?">
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-1 text-3xl font-semibold">How are we related?</h1>
        <p class="muted mb-6">Pick two people. Heirloom works out the relationship, including half-relations, in-laws, step-family and cousins “once removed”.</p>
        <form class="card grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <div><label class="label">Person A</label><x-people-picker :people="$people" name="a" :selected="$a ? [$a] : []" :multiple="false" /></div>
            <div><label class="label">Person B</label><x-people-picker :people="$people" name="b" :selected="$b ? [$b] : []" :multiple="false" /></div>
            <button class="btn btn-primary">Calculate</button>
        </form>

        @if($result)
            <div class="card mt-6 text-center">
                <p class="muted text-sm">Person B is Person A’s</p>
                <p class="my-2 font-serif text-4xl font-semibold text-accent">{{ $result['label'] }}</p>
                <p>{{ $result['sentence'] }}</p>
                @if($result['common'])
                    <p class="muted mt-2 text-sm">Closest common ancestor{{ count($result['common']) > 1 ? 's' : '' }}: {{ collect($result['common'])->pluck('full_name')->join(' & ') }}</p>
                @endif
            </div>
            @if($result['path'])
                <div class="card mt-4">
                    <h2 class="section-title">The connection</h2>
                    <ol class="flex flex-wrap items-center gap-2">
                        @foreach($result['path'] as $i => $step)
                            @if($step['via'])
                                <li class="muted text-xs">→ {{ ['parent' => 'whose parent is', 'child' => 'whose child is', 'partner' => 'whose partner is'][$step['via']] }} →</li>
                            @endif
                            <li><x-person-card :person="$step['person']" /></li>
                        @endforeach
                    </ol>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
