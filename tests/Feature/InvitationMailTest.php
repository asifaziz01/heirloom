<?php

namespace Tests\Feature;

use App\Mail\InvitationMail;
use App\Models\Family;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\DemoFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvitationMailTest extends TestCase
{
    use RefreshDatabase;

    private Family $family;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DemoFamilySeeder::class);
        $this->family = Family::firstOrFail();
        $this->owner = User::where('email', 'owner@heirloom.test')->firstOrFail();
    }

    public function test_an_invitation_with_an_email_address_is_sent_by_mail(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)
            ->post(route('invitations.store', $this->family), ['email' => 'cousin@example.com', 'role' => 'contributor'])
            ->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'emailed to cousin@example.com'));

        $invitation = Invitation::where('email', 'cousin@example.com')->firstOrFail();
        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use ($invitation) {
            $mail->assertTo('cousin@example.com')
                ->assertSeeInHtml(route('invitations.show', $invitation->token))
                ->assertSeeInHtml($this->family->name)
                ->assertSeeInText('contributor');

            return $mail->invitation->is($invitation);
        });
    }

    public function test_a_link_only_invitation_sends_no_mail(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)
            ->post(route('invitations.store', $this->family), ['role' => 'viewer'])
            ->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'Share this link'));

        Mail::assertNothingSent();
    }
}
