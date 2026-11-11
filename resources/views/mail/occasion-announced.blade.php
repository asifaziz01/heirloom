<x-mail::message>
# {{ $occasion->icon() }} {{ $occasion->title }}

{{ $addedBy }} added a new occasion to the **{{ $family->name }}** calendar.

**When:** {{ $occasion->whenLabel() }}@if($occasion->repeats_yearly) (every year)@endif

@if($occasion->whereLabel())
**Where:** {{ $occasion->whereLabel() }}
@endif
@if($occasion->people->isNotEmpty())
**For:** {{ $occasion->people->pluck('full_name')->join(', ') }}
@endif
@if($occasion->description)

{{ \Illuminate\Support\Str::limit($occasion->description, 600) }}
@endif

<x-mail::button :url="$url">
@if($occasion->rsvp_enabled) See details & reply @else See details @endif
</x-mail::button>

[Add it to your calendar]({{ $calendarUrl }}). You will get a reminder a week before, the day before and on the day.

Warm regards,<br>
{{ config('app.name') }}

<x-slot:subcopy>
@if($unsubscribeUrl)
Too many emails? [Turn off reminders for this family]({{ $unsubscribeUrl }}).
@endif
If the button does not work, copy this link into your browser: {{ $url }}
</x-slot:subcopy>
</x-mail::message>
