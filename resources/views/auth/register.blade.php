<x-guest-layout title="Create account">
    <h1 class="mb-1 text-2xl font-semibold">Create your account</h1>
    <p class="muted mb-6 text-sm">Free for up to 100 people. No card needed.</p>
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div><label class="label" for="name">Your name</label><input id="name" name="name" value="{{ old('name') }}" required autofocus class="input"></div>
        <div><label class="label" for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required class="input"></div>
        <div><label class="label" for="password">Password</label><input id="password" name="password" type="password" required class="input"><p class="help">At least 8 characters.</p></div>
        <div><label class="label" for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required class="input"></div>
        <button class="btn btn-primary w-full">Create account</button>
    </form>
    <p class="muted mt-5 text-center text-sm">Already have an account? <a href="{{ route('login') }}" class="link">Sign in</a></p>
</x-guest-layout>
