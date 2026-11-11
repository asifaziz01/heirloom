<x-mail::message>
# {{ $occasion->icon() }} {{ $occasion->title }} is {{ $when }}

**When:** {{ $occasion->whenLabel() }}@if($years) · the {{ \App\Models\Occasion::ordinal($years) }} time @endif

@if($occasion->whereLabel())
**Where:** {{ $occasion->whereLabel() }}
@endif
@if($occasion->people->isNotEmpty())
**For:** {{ $occasion->people->pluck('full_name')->join(', ') }}
@endif
@if($occasion->description)

{{ \Illuminate\Support\Str::limit($occasion->description, 600) }}
@endif
@if($occasion->rsvp_enabled)

@if($mine)
You replied **{{ strtolower(\App\Models\Occasion::RSVP_STATUSES[$mine->status]) }}**{{ $mine->status === 'going' && $mine->guests ? ' with '.$mine->guests.' guest'.($mine->guests === 1 ? '' : 's') : '' }}. Changed plans? Update your reply below.
@else
You have not said yet whether you are coming.
@endif
@endif

<x-mail::button :url="$url">
@if($occasion->rsvp_enabled) Open & reply @else Open @endif
</x-mail::button>

Warm regards,<br>
{{ config('app.name') }}

<x-slot:subcopy>
@if($unsubscribeUrl)
Too many emails? [Turn off reminders for this family]({{ $unsubscribeUrl }}).
@endif
If the button does not work, copy this link into your browser: {{ $url }}
</x-slot:subcopy>
</x-mail::message>
