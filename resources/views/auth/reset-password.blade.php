<x-guest-layout title="Choose a new password">
    <h1 class="mb-1 text-2xl font-semibold">Choose a new password</h1>
    <p class="muted mb-6 text-sm">Pick something at least 8 characters long that you do not use anywhere else.</p>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label class="label" for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" value="{{ old('email', $email) }}" required class="input"></div>
        <div><label class="label" for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required autofocus class="input"></div>
        <div><label class="label" for="password_confirmation">Repeat new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="input"></div>
        <button class="btn btn-primary w-full">Change password</button>
    </form>
    <p class="muted mt-5 text-center text-sm"><a href="{{ route('login') }}" class="link">Back to sign in</a></p>
</x-guest-layout>
