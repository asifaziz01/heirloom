<x-mail::message>
# This week in the {{ $family->name }}

@foreach($items as $item)
- **{{ $item['date'] }}** · [{{ $item['text'] }}]({{ $item['url'] }})
@endforeach

<x-mail::button :url="$url">
Occasions & reminders
</x-mail::button>

Warm regards,<br>
{{ config('app.name') }}

<x-slot:subcopy>
@if($unsubscribeUrl)
Too many emails? [Turn off reminders for this family]({{ $unsubscribeUrl }}).
@endif
</x-slot:subcopy>
</x-mail::message>
