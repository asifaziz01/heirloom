<x-mail::message>
# {{ ucfirst($when) }} in the {{ $family->name }}

@foreach($items as $item)
- [{{ $item['text'] }}]({{ $item['url'] }})
@endforeach

@if(collect($items)->contains('type', 'birthday'))
A call or a message means a lot. Their profile has their number if a relative added it.
@endif
@if(collect($items)->contains('type', 'remembrance'))
If you have a memory or a photo of someone remembered {{ $when }}, add it to their profile so the family keeps it.
@endif

<x-mail::button :url="$url">
Open the family home page
</x-mail::button>

Warm regards,<br>
{{ config('app.name') }}

<x-slot:subcopy>
@if($unsubscribeUrl)
Too many emails? [Turn off reminders for this family]({{ $unsubscribeUrl }}).
@endif
</x-slot:subcopy>
</x-mail::message>
