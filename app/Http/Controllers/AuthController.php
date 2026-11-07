<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Sign in with an email address or a mobile number. Sessions never expire on their
     * own: every sign-in sets the long-lived "remember me" cookie, so users stay signed
     * in on the device until they choose Sign out.
     */
    public function login(Request $request)
    {
        $data = $request->validate(['login' => 'required|string|max:190', 'password' => 'required']);
        $credentials = User::credentialsFor($data['login']) + ['password' => $data['password']];

        if (! Auth::attempt($credentials, remember: true)) {
            return back()->withInput($request->only('login'))->withErrors(['login' => 'Those details do not match our records.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge([
            // Lowercased, because sign-in and password reset look the address up lowercased.
            'email' => mb_strtolower(trim((string) $request->input('email'))) ?: null,
            'phone' => User::normalizePhone($request->input('phone')),
        ]);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'nullable|required_without:phone|email|max:190|unique:users',
            'phone' => ['nullable', 'required_without:email', 'regex:/^\+?[0-9]{7,15}$/', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'email.required_without' => 'Enter an email address or a mobile number.',
            'phone.required_without' => 'Enter an email address or a mobile number.',
            'phone.regex' => 'Enter a mobile number with 7 to 15 digits, with the country code if you have one (e.g. +91 98765 43210).',
        ]);
        $user = User::create($data);
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('families.create'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
