<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_reset_their_password_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'asif@example.com']);

        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => 'Asif@Example.com'])->assertRedirect()->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=asif%40example.com")->assertOk()->assertSee('asif@example.com');
        $this->post('/reset-password', [
            'token' => $token, 'email' => 'asif@example.com',
            'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-secret-9', $user->fresh()->password));
        $this->post('/login', ['login' => 'asif@example.com', 'password' => 'new-secret-9'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_mail_failure_shows_a_message_instead_of_an_error_page_and_allows_a_retry(): void
    {
        $user = User::factory()->create(['email' => 'asif@example.com']);
        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Unable to send an email: Forbidden (code 401).');
            }

            public function __toString(): string
            {
                return 'failing://';
            }
        });
        config(['mail.default' => 'failing', 'mail.mailers.failing' => ['transport' => 'failing']]);

        $this->post('/forgot-password', ['email' => 'asif@example.com'])
            ->assertRedirect()->assertSessionHasErrors('email')->assertSessionMissing('status');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'asif@example.com']);

        // Once the mailer works again the user is not told to wait for the throttle to expire.
        Notification::fake();
        $this->post('/forgot-password', ['email' => 'asif@example.com'])->assertRedirect()->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_addresses_get_the_same_message_and_bad_tokens_are_rejected(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'asif@example.com']);

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertRedirect()->assertSessionHas('status');
        Notification::assertNothingSent();

        $this->post('/forgot-password', ['email' => '+91 98765 43210'])->assertSessionHasErrors('email');

        $this->post('/reset-password', [
            'token' => 'not-a-real-token', 'email' => 'asif@example.com',
            'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9',
        ])->assertSessionHasErrors('email');
    }

    public function test_registration_stores_the_email_lowercased_so_reset_and_sign_in_find_it(): void
    {
        $this->post('/register', ['name' => 'Mixed', 'email' => 'Mixed.Case@Example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertRedirect(route('families.create'));
        $this->assertDatabaseHas('users', ['email' => 'mixed.case@example.com']);
    }
}
