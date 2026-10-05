<x-app-layout title="Plan">
    <h1 class="mb-1 text-3xl font-semibold">Family plan</h1>
    <p class="muted mb-6">One subscription for the whole family. Currently on <strong>{{ config('plans.'.$family->plan.'.name') }}</strong>.</p>
    <div class="card mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        @php $plan = config('plans.'.$family->plan); @endphp
        @foreach(['people' => 'People', 'photos' => 'Photos', 'storage_mb' => 'Storage (MB)', 'contributors' => 'Contributors'] as $k => $l)
            <div>
                <div class="label">{{ $l }}</div>
                <div class="text-xl font-semibold">{{ $usage[$k] }} <span class="muted text-sm font-normal">/ {{ $plan[$k] ? number_format($plan[$k]) : '∞' }}</span></div>
                @if($plan[$k])<div class="mt-1 h-1.5 rounded-full bg-paper-2 dark:bg-white/10"><div class="h-full rounded-full bg-accent" style="width: {{ min(100, $usage[$k] / $plan[$k] * 100) }}%"></div></div>@endif
            </div>
        @endforeach
    </div>
    <div class="grid gap-4 md:grid-cols-4">
        @foreach($plans as $key => $p)
            <div class="card flex flex-col {{ $family->plan === $key ? 'ring-2 ring-accent' : '' }}">
                <div class="font-serif text-xl font-semibold">{{ $p['name'] }}</div>
                <div class="my-2 text-3xl font-semibold">${{ $p['price'] }}<span class="muted text-sm font-normal">/year</span></div>
                <ul class="muted mb-4 flex-1 space-y-1 text-sm">
                    <li>{{ $p['people'] ? number_format($p['people']).' people' : 'Unlimited people' }}</li>
                    <li>{{ $p['storage_mb'] >= 1024 ? ($p['storage_mb'] / 1024).' GB' : $p['storage_mb'].' MB' }} storage</li>
                    <li>{{ $p['contributors'] ? $p['contributors'].' contributors' : 'Unlimited contributors' }}</li>
                    @foreach(['documents' => 'Documents', 'video' => 'Video', 'ai' => 'AI interviews, biographies, photo analysis', 'gedcom' => 'GEDCOM export', 'archive_export' => 'Full archive export', 'book' => 'Family book'] as $f => $l)
                        @if(in_array($f, $p['features']))<li>✓ {{ $l }}</li>@endif
                    @endforeach
                </ul>
                @if($family->plan === $key)
                    <span class="btn" aria-disabled="true">Current plan</span>
                @elseif($member->atLeast('owner'))
                    <form method="POST" action="{{ route('billing.change', $family) }}">@csrf<input type="hidden" name="plan" value="{{ $key }}"><button class="btn btn-primary w-full">Switch to {{ $p['name'] }}</button></form>
                @endif
            </div>
        @endforeach
    </div>
    <p class="muted mt-4 text-xs">Payments aren’t connected yet. Switching changes the plan immediately so limits can be tested.</p>
</x-app-layout>
