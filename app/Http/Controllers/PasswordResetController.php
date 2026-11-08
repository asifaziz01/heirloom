<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Symfony\Component\Mailer\Exception\ExceptionInterface as MailerException;

/**
 * Password reset by email, using Laravel's password broker and the configured mailer.
 * Accounts registered with a mobile number only have no address to send a link to.
 */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:190'], [
            'email.email' => 'Enter the email address on your account. Accounts with only a mobile number cannot reset a password yet.',
        ]);

        $credentials = ['email' => mb_strtolower(trim($data['email']))];
        try {
            $status = Password::sendResetLink($credentials);
        } catch (MailerException $e) {
            // The mailer is misconfigured or unreachable (e.g. missing or wrong MAILGUN_* keys). Log it for the
            // operator, drop the token that was already stored so a retry is not throttled, and tell the user.
            report($e);
            if ($user = Password::broker()->getUser($credentials)) {
                Password::broker()->deleteToken($user);
            }

            return back()->withInput()->withErrors([
                'email' => 'The reset email could not be sent right now. Please try again in a few minutes, or ask a family administrator for help.',
            ]);
        }
        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['email' => __($status)]);
        }

        // The same message whether or not the address is known, so the form cannot be used to look up accounts.
        return back()->with('status', 'If that address belongs to an account, a reset link is on its way. Check your spam folder too.');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        $data['email'] = mb_strtolower(trim($data['email']));

        $status = Password::reset($data, function ($user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Your password has been changed. Sign in with the new one.');
    }
}
