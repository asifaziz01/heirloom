<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_sign_in_with_email_or_mobile_number(): void
    {
        $user = User::factory()->create(['email' => 'asif@example.com', 'phone' => '+919876543210']);

        foreach (['asif@example.com', 'ASIF@example.com', '+91 98765 43210', '+91-98765-43210', '0091 98765 43210'] as $login) {
            $this->post('/login', ['login' => $login, 'password' => 'password'])->assertRedirect('/');
            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }

        $this->post('/login', ['login' => '+919876543210', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_sign_in_sets_the_long_lived_remember_cookie_so_sessions_do_not_expire(): void
    {
        User::factory()->create(['email' => 'asif@example.com']);

        $response = $this->post('/login', ['login' => 'asif@example.com', 'password' => 'password']);

        $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($cookie, 'remember-me cookie was not set');
        // Browsers cap any cookie at 400 days; the session cookie (a year) renews on every visit, so sign-ins outlast any realistic gap in use.
        $this->assertGreaterThan(now()->addDays(365)->timestamp, $cookie->getExpiresTime());
        $this->assertGreaterThanOrEqual(525600, config('session.lifetime'));
    }

    public function test_sign_in_attempts_are_rate_limited_per_account_and_address(): void
    {
        User::factory()->create(['email' => 'asif@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'asif@example.com', 'password' => 'wrong'])->assertSessionHasErrors('login');
        }
        $this->post('/login', ['login' => 'asif@example.com', 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();

        // Another account from the same address is still allowed (until the per-address cap).
        User::factory()->create(['email' => 'other@example.com']);
        $this->post('/login', ['login' => 'other@example.com', 'password' => 'password'])->assertRedirect('/');
    }

    public function test_registration_is_rate_limited_per_address(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/register', ['name' => 'Bad', 'password' => 'x'])->assertSessionHasErrors();
        }
        $this->post('/register', ['name' => 'Late', 'email' => 'late@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertStatus(429);
        $this->assertDatabaseMissing('users', ['email' => 'late@example.com']);
    }

    public function test_registration_accepts_email_mobile_or_both(): void
    {
        $this->post('/register', ['name' => 'Phone Only', 'phone' => '+1 (408) 555-0199', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertRedirect(route('families.create'));
        $this->assertDatabaseHas('users', ['name' => 'Phone Only', 'phone' => '+14085550199', 'email' => null]);
        auth()->logout();

        $this->post('/register', ['name' => 'Email Only', 'email' => 'email@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertRedirect(route('families.create'));
        auth()->logout();

        $this->post('/register', ['name' => 'Neither', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertSessionHasErrors(['email', 'phone']);
        $this->post('/register', ['name' => 'Duplicate', 'phone' => '+14085550199', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertSessionHasErrors('phone');
        $this->post('/register', ['name' => 'Bad', 'phone' => '12', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertSessionHasErrors('phone');
    }
}
