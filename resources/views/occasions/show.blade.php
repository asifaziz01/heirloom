<x-app-layout :title="$occasion->title">
    @php $when = $occasion->nextOccurrence(); $past = $occasion->isPast(); $years = $occasion->yearsSince(); @endphp
    <div class="mx-auto max-w-3xl">
        <p class="mb-2 text-sm"><a href="{{ route('occasions.index', $family) }}" class="link">← All occasions</a></p>
        <div class="card">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge">{{ $occasion->typeLabel() }}</span>
                        @if($occasion->repeats_yearly)<span class="badge">every year</span>@endif
                        @if($past)<span class="badge">past</span>@endif
                    </div>
                    <h1 class="mt-2 font-serif text-3xl font-semibold">{{ $occasion->icon() }} {{ $occasion->title }}</h1>
                    <p class="mt-2 text-lg">{{ $occasion->whenLabel() }}@if($years) <span class="muted">· {{ \App\Models\Occasion::ordinal($years) }} time</span>@endif</p>
                    @if($occasion->whereLabel())<p class="muted">📍 {{ $occasion->whereLabel() }}</p>@endif
                    @if(!$past && $when)
                        <p class="mt-1 text-sm font-semibold text-accent">{{ $when->isToday() ? 'Today!' : ($when->isTomorrow() ? 'Tomorrow' : $when->diffForHumans(['parts' => 2])) }}</p>
                    @endif
                </div>
                @if($member->atLeast('contributor'))
                    <a href="{{ route('occasions.edit', [$family, $occasion]) }}" class="btn btn-sm">Edit</a>
                @endif
            </div>

            @if($occasion->people->isNotEmpty())
                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="muted">For</span>
                    @foreach($occasion->people as $p)
                        <a href="{{ route('people.show', [$family, $p]) }}" class="badge !bg-accent-soft !text-accent">{{ $p->full_name }}</a>
                    @endforeach
                </div>
            @endif

            @if($occasion->description)
                <div class="prose prose-stone mt-4 max-w-none text-sm dark:prose-invert" dir="auto">{!! nl2br(e($occasion->description)) !!}</div>
            @endif

            <div class="mt-5 flex flex-wrap gap-2 border-t border-line pt-4 dark:border-white/10">
                <a href="{{ route('calendar.occasion', [$family, $occasion]) }}" class="btn btn-sm">📅 Add to my calendar</a>
                <a href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener" class="btn btn-sm">Share on WhatsApp</a>
                <button type="button" class="btn btn-sm" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js(route('occasions.show', [$family, $occasion]))).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" x-text="copied ? 'Copied!' : 'Copy link'">Copy link</button>
            </div>
            @if($occasion->creator)<p class="muted mt-3 text-xs">Added by {{ $occasion->creator->name }} {{ $occasion->created_at->diffForHumans() }}.</p>@endif
        </div>

        @if($occasion->rsvp_enabled && !$past)
            <div class="card mt-6" x-data="{ status: @js(old('status', $mine?->status)) }">
                <h2 class="section-title">Are you coming?</h2>
                @if($member->isGuest())
                    <p class="muted text-sm">Family members can <a class="link" href="{{ route('login') }}">sign in</a> to reply.</p>
                @else
                    <form method="POST" action="{{ route('occasions.rsvp', [$family, $occasion]) }}" class="space-y-3">
                        @csrf
                        <div class="flex flex-wrap gap-2">
                            @foreach(\App\Models\Occasion::RSVP_STATUSES as $v => $l)
                                <label class="btn cursor-pointer" :class="status === @js($v) ? 'btn-primary' : ''">
                                    <input type="radio" name="status" value="{{ $v }}" x-model="status" class="sr-only" required> {{ $l }}
                                </label>
                            @endforeach
                        </div>
                        <div class="grid gap-3 sm:grid-cols-[8rem_1fr]">
                            <div x-show="status === 'going'"><label class="label">Extra guests</label><input type="number" name="guests" min="0" max="50" value="{{ old('guests', $mine?->guests ?? 0) }}" class="input"></div>
                            <div class="sm:col-start-2"><label class="label">Note <span class="normal-case">(optional)</span></label><input name="note" value="{{ old('note', $mine?->note) }}" class="input" placeholder="Arriving Friday night, staying with Ammi"></div>
                        </div>
                        <button class="btn btn-primary" x-show="status">{{ $mine ? 'Update my reply' : 'Send my reply' }}</button>
                    </form>
                @endif

                @unless($member->isGuest())
                    <div class="mt-5 border-t border-line pt-4 dark:border-white/10">
                        <div class="flex flex-wrap gap-4 text-sm">
                            <span><strong>{{ $headcount['going'] }}</strong> going</span>
                            <span><strong>{{ $headcount['maybe'] }}</strong> maybe</span>
                            <span class="muted"><strong>{{ $headcount['no'] }}</strong> can’t make it</span>
                        </div>
                        @if($occasion->rsvps->isNotEmpty())
                            <ul class="mt-3 space-y-1 text-sm">
                                @foreach($occasion->rsvps->sortBy(fn ($r) => ['going' => 0, 'maybe' => 1, 'no' => 2][$r->status]) as $r)
                                    <li>
                                        <span class="font-medium">{{ $r->user?->name ?? 'Former member' }}</span>
                                        <span class="muted">· {{ strtolower(\App\Models\Occasion::RSVP_STATUSES[$r->status]) }}{{ $r->status === 'going' && $r->guests ? ' +'.$r->guests : '' }}</span>
                                        @if($r->note)<span class="muted">— {{ $r->note }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endunless
            </div>
        @endif

        @if($member->canManage($occasion->created_by))
            <form method="POST" action="{{ route('occasions.destroy', [$family, $occasion]) }}" class="mt-4" onsubmit="return confirm('Remove this occasion? Replies will be lost too.')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Remove occasion</button></form>
        @endif
    </div>
</x-app-layout>
