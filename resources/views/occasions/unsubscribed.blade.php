<x-guest-layout title="Reminder emails turned off">
    <h1 class="font-serif text-2xl font-semibold">Reminder emails are off</h1>
    <p class="muted mt-2 text-sm">You will no longer get reminder or digest emails for the <strong>{{ $family->name }}</strong> archive. Occasions, birthdays and anniversaries still appear on the family home page.</p>
    <p class="muted mt-2 text-sm">Changed your mind? Open <a class="link" href="{{ route('occasions.index', $family) }}">Occasions & reminders</a> and turn them back on.</p>
    <a href="{{ route('families.show', $family) }}" class="btn btn-primary mt-5">Back to the family</a>
</x-guest-layout>
