<x-mail::message>
# You're invited to the {{ $family }} family archive

{{ $inviter }} has invited you to join **{{ $family }}** on {{ config('app.name') }} as a {{ $role }}.
@if($person)
You will be linked to **{{ $person }}** in the family tree, so relatives know which branch is yours.
@endif

{{ config('app.name') }} is a private archive where the family keeps its tree, photos, stories and recorded memories. Only invited members can see it.

<x-mail::button :url="$url">
Accept the invitation
</x-mail::button>

If you do not have an account yet, you can create one with your email address or mobile number after opening the link.
@if($expires)
This invitation expires on {{ $expires }}.
@endif

If you were not expecting this, you can ignore this email.

Warm regards,<br>
{{ config('app.name') }}

<x-slot:subcopy>
If the button does not work, copy this link into your browser: {{ $url }}
</x-slot:subcopy>
</x-mail::message>
