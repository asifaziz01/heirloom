<x-guest-layout title="Sign in">
    <h1 class="mb-1 text-2xl font-semibold">Welcome back</h1>
    <p class="muted mb-6 text-sm">Sign in to your family archive.</p>
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div><label class="label" for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input"></div>
        <div><label class="label" for="password">Password</label><input id="password" name="password" type="password" required class="input"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <button class="btn btn-primary w-full">Sign in</button>
    </form>
    <p class="muted mt-5 text-center text-sm">New here? <a href="{{ route('register') }}" class="link">Start your family archive</a></p>
</x-guest-layout>
