<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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
