<x-guest-layout title="Forgot your password?">
    <h1 class="mb-1 text-2xl font-semibold">Forgot your password?</h1>
    <p class="muted mb-6 text-sm">Enter the email address on your account and we will send you a link to choose a new one.</p>
    @if(session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('status') }}</div>
    @endif
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div><label class="label" for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required autofocus class="input" placeholder="you@example.com"></div>
        <button class="btn btn-primary w-full">Email me a reset link</button>
        <p class="muted text-center text-xs">Signed up with a mobile number only? Password reset by SMS is not available yet.</p>
    </form>
    <p class="muted mt-5 text-center text-sm"><a href="{{ route('login') }}" class="link">Back to sign in</a></p>
</x-guest-layout>
