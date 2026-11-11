<x-app-layout title="Occasions & reminders">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-semibold">Occasions & reminders</h1>
            <p class="muted mt-1 text-sm">Weddings, anniversaries, gatherings and remembrance days the family is looking forward to.</p>
        </div>
        @if($member->atLeast('contributor'))<a href="{{ route('occasions.create', $family) }}" class="btn btn-primary">+ Add an occasion</a>@endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ route('occasions.index', $family) }}" class="badge {{ !$showPast ? '!bg-accent !text-white' : '' }}">Coming up</a>
                <a href="{{ route('occasions.index', [$family, 'past' => 1]) }}" class="badge {{ $showPast ? '!bg-accent !text-white' : '' }}">Past ({{ $pastCount }})</a>
            </div>
            <div class="card divide-y divide-line !p-0 dark:divide-white/10">
                @forelse($occasions as $o)
                    @php $when = $o->nextOccurrence() ?? $o->date; $count = $o->headcount(); @endphp
                    <a href="{{ route('occasions.show', [$family, $o]) }}" class="flex items-start gap-4 px-5 py-4 hover:bg-paper-2/60 dark:hover:bg-white/5">
                        <div class="w-14 shrink-0 text-center">
                            <div class="text-xs uppercase muted">{{ $when->format('M') }}</div>
                            <div class="font-serif text-2xl font-semibold leading-none">{{ $when->format('j') }}</div>
                            <div class="muted text-[11px]">{{ $when->format('Y') }}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $o->icon() }} {{ $o->title }} <span class="badge ml-1">{{ $o->typeLabel() }}</span>@if($o->repeats_yearly)<span class="badge ml-1">every year</span>@endif</div>
                            <div class="muted text-sm">{{ $o->whenLabel() }}@if($o->whereLabel()) · {{ $o->whereLabel() }}@endif</div>
                            @if($o->people->isNotEmpty())<div class="muted text-sm">For {{ $o->people->pluck('full_name')->join(', ') }}</div>@endif
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                @unless($showPast)
                                    <span class="font-semibold text-accent">{{ $when->isToday() ? 'Today' : ($when->isTomorrow() ? 'Tomorrow' : $when->diffForHumans(['parts' => 1])) }}</span>
                                @endunless
                                @if($o->rsvp_enabled && $count['going'])<span class="muted">· {{ $count['going'] }} going</span>@endif
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="muted p-5">
                        @if($showPast) Nothing has passed yet.
                        @else Nothing planned yet. Add the next wedding, a milestone anniversary, a reunion or an annual remembrance, and everyone gets a reminder before the day.
                        @endif
                    </p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <section class="card">
                <h2 class="section-title">Reminders</h2>
                @if($member->isGuest())
                    <p class="muted text-sm">Family members who sign in get reminder emails before each occasion, birthday and anniversary, and a Monday digest of the week ahead.</p>
                @else
                    <p class="text-sm">
                        @if($member->email_reminders)
                            You get an email a week before, the day before and on the morning of each occasion, the day before and on the day of birthdays and anniversaries, and a Monday digest of the week ahead.
                        @else
                            Reminder emails are <strong>off</strong> for you in this family.
                        @endif
                    </p>
                    <form method="POST" action="{{ route('reminders.update', $family) }}" class="mt-3">
                        @csrf @method('PUT')
                        <input type="hidden" name="email_reminders" value="{{ $member->email_reminders ? 0 : 1 }}">
                        <button class="btn btn-sm">{{ $member->email_reminders ? 'Turn reminder emails off' : 'Turn reminder emails on' }}</button>
                    </form>
                    @unless(auth()->user()->email)<p class="muted mt-2 text-xs">Your account has no email address, so reminders cannot reach you yet.</p>@endunless
                @endif
                <h3 class="mt-5 text-sm font-semibold">On your phone’s calendar</h3>
                <p class="muted mt-1 text-sm">Every occasion, birthday and anniversary, with the calendar app’s own alerts.</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <a href="{{ route('calendar.download', $family) }}" class="btn btn-sm">Download .ics</a>
                    @if($calendarUrl)
                        <button type="button" class="btn btn-sm" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js($calendarUrl)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" x-text="copied ? 'Copied!' : 'Copy subscription link'">Copy subscription link</button>
                    @endif
                </div>
                @if($calendarUrl)
                    <p class="muted mt-2 text-xs">Paste the link into Google Calendar (<em>Other calendars → From URL</em>), Apple Calendar (<em>File → New Calendar Subscription</em>) or Outlook (<em>Add calendar → Subscribe from web</em>). It stays up to date by itself. The link is personal: do not forward it.</p>
                @endif
            </section>

            <section class="card">
                <h2 class="section-title">Birthdays & anniversaries <span class="muted font-sans text-xs font-normal">next 90 days</span></h2>
                @forelse($upcoming as $u)
                    <div class="flex items-center gap-3 border-b border-line py-2 text-sm last:border-0 dark:border-white/10">
                        <div class="w-12 shrink-0 text-center">
                            <div class="text-[10px] uppercase muted">{{ $u['when']->format('M') }}</div>
                            <div class="font-serif text-lg font-semibold leading-none">{{ $u['when']->format('j') }}</div>
                        </div>
                        <a class="link min-w-0 truncate" href="{{ \App\Services\Upcoming::url($family, $u) }}">{{ \App\Services\Upcoming::describe($u) }}</a>
                    </div>
                @empty
                    <p class="muted text-sm">None in the next three months. Full birth and wedding dates on profiles feed this list.</p>
                @endforelse
            </section>
        </div>
    </div>
</x-app-layout>
