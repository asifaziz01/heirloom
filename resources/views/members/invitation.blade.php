<x-guest-layout title="You're invited">
    @if(! $invitation->isUsable())
        <h1 class="mb-2 text-2xl font-semibold">This invitation has expired</h1>
        <p class="muted text-sm">Ask {{ $invitation->inviter->name }} to send you a new link.</p>
    @else
        <p class="muted text-sm">{{ $invitation->inviter->name }} invited you to</p>
        <h1 class="mb-2 text-2xl font-semibold">{{ $invitation->family->name }}</h1>
        <p class="mb-5 text-sm">A private family archive of our tree, photos and stories. You’ll join as <strong>{{ $invitation->role }}</strong>{{ $invitation->person ? ', and your profile is '.$invitation->person->full_name : '' }}.</p>
        @auth
            <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">@csrf<button class="btn btn-primary w-full">Join the family archive</button></form>
        @else
            <a href="{{ route('register') }}" class="btn btn-primary w-full">Create an account to join</a>
            <a href="{{ route('login') }}" class="btn mt-2 w-full">I already have an account</a>
        @endauth
    @endif
</x-guest-layout>
