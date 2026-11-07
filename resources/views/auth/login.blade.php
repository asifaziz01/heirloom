<x-guest-layout title="Sign in">
    <h1 class="mb-1 text-2xl font-semibold">Welcome back</h1>
    <p class="muted mb-6 text-sm">Sign in to your family archive.</p>
    @if(session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('status') }}</div>
    @endif
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div><label class="label" for="login">Email or mobile number</label><input id="login" name="login" type="text" inputmode="email" autocomplete="username" value="{{ old('login') }}" required autofocus class="input" placeholder="you@example.com or +91 98765 43210"></div>
        <div>
            <div class="flex items-baseline justify-between"><label class="label" for="password">Password</label><a href="{{ route('password.request') }}" class="link text-xs">Forgot it?</a></div>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="input">
        </div>
        <button class="btn btn-primary w-full">Sign in</button>
        <p class="muted text-center text-xs">You stay signed in on this device until you sign out.</p>
    </form>
    <p class="muted mt-5 text-center text-sm">New here? <a href="{{ route('register') }}" class="link">Start your family archive</a></p>
</x-guest-layout>
