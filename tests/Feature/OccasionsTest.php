<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Occasion;
use App\Models\User;
use App\Notifications\DatesReminder;
use App\Notifications\OccasionAnnounced;
use App\Notifications\OccasionReminder;
use App\Notifications\WeeklyDigest;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OccasionsTest extends TestCase
{
    use RefreshDatabase;

    private Family $family;

    private User $owner;

    private User $zara;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DemoFamilySeeder::class);
        $this->family = Family::firstOrFail();
        $this->owner = User::where('email', 'owner@heirloom.test')->firstOrFail();
        $this->zara = User::where('email', 'zara@heirloom.test')->firstOrFail();
    }

    private function wedding(): Occasion
    {
        return $this->family->occasions()->where('type', 'wedding')->firstOrFail();
    }

    public function test_occasion_pages_render(): void
    {
        $wedding = $this->wedding();
        $this->actingAs($this->owner);
        $this->get(route('occasions.index', $this->family))->assertOk()->assertSee('Arman &amp; Hiba’s wedding', false)->assertSee('Copy subscription link');
        $this->get(route('occasions.index', [$this->family, 'past' => 1]))->assertOk();
        $this->get(route('occasions.show', [$this->family, $wedding]))->assertOk()->assertSee('Are you coming?')->assertSee('Landing Friday morning')->assertSee('Share on WhatsApp');
        $this->get(route('occasions.create', $this->family))->assertOk();
        $this->get(route('occasions.edit', [$this->family, $wedding]))->assertOk();
        $this->get(route('families.show', $this->family))->assertOk()->assertSee('Occasions &amp; reminders', false)->assertSee('Inaya');

        $barsi = $this->family->occasions()->where('type', 'memorial')->firstOrFail();
        $this->assertTrue($barsi->repeats_yearly);
        $this->assertNotNull($barsi->nextOccurrence());
        $this->assertGreaterThan(15, $barsi->yearsSince());
        $this->get(route('occasions.show', [$this->family, $barsi]))->assertOk()->assertDontSee('Are you coming?');
    }

    public function test_adding_an_occasion_emails_the_rest_of_the_family(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)->post(route('occasions.store', $this->family), [
            'type' => 'anniversary', 'title' => 'Imran & Neha’s 35th anniversary dinner', 'date' => now()->addDays(40)->toDateString(),
            'time' => '19:30', 'place' => 'San Jose, USA', 'venue' => 'Home', 'rsvp_enabled' => 1, 'send_reminders' => 1,
        ])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, '1 relative has been emailed'));

        $occasion = Occasion::where('title', 'like', 'Imran & Neha%')->firstOrFail();
        Notification::assertSentTo($this->zara, OccasionAnnounced::class, function (OccasionAnnounced $n) use ($occasion) {
            $mail = $n->toMail($this->zara);
            $html = $mail->render();

            return $n->occasion->is($occasion)
                && str_contains($html, 'anniversary dinner')
                && str_contains($html, route('occasions.show', [$this->family, $occasion]))
                && str_contains($html, '/reminders/');
        });
        Notification::assertNotSentTo($this->owner, OccasionAnnounced::class);
        $this->assertSame('19:30', $occasion->time);
    }

    public function test_occasion_reminders_go_out_a_week_before_the_day_before_and_on_the_day_but_never_twice(): void
    {
        Notification::fake();
        $wedding = $this->wedding();
        $day = $wedding->date;

        $this->artisan('family:remind', ['--date' => $day->subDays(7)->toDateString()])->assertSuccessful();
        Notification::assertSentTo($this->owner, OccasionReminder::class, fn (OccasionReminder $n) => $n->occasion->is($wedding) && $n->daysUntil === 7 && str_contains($n->toMail($this->owner)->render(), 'is in 7 days'));
        Notification::assertSentTo($this->zara, OccasionReminder::class, fn (OccasionReminder $n) => str_contains($n->toMail($this->zara)->render(), 'You replied **going**') || str_contains($n->toMail($this->zara)->render(), 'going'));

        // Same morning again (cron ran twice): nothing more.
        $this->artisan('family:remind', ['--date' => $day->subDays(7)->toDateString()])->assertSuccessful();
        Notification::assertSentToTimes($this->owner, OccasionReminder::class, 1);

        // Four days out: not a reminder day.
        $this->artisan('family:remind', ['--date' => $day->subDays(4)->toDateString()]);
        Notification::assertSentToTimes($this->owner, OccasionReminder::class, 1);

        $this->artisan('family:remind', ['--date' => $day->subDay()->toDateString()]);
        Notification::assertSentTo($this->owner, OccasionReminder::class, fn (OccasionReminder $n) => $n->daysUntil === 1);
        $this->artisan('family:remind', ['--date' => $day->toDateString()]);
        Notification::assertSentTo($this->owner, OccasionReminder::class, fn (OccasionReminder $n) => $n->daysUntil === 0);
        Notification::assertSentToTimes($this->owner, OccasionReminder::class, 3);

        // The organiser can switch reminders off for one occasion.
        $barsi = $this->family->occasions()->where('type', 'memorial')->firstOrFail();
        $barsi->update(['send_reminders' => false]);
        $this->artisan('family:remind', ['--date' => $barsi->nextOccurrence()->subDay()->toDateString()]);
        Notification::assertNotSentTo($this->owner, OccasionReminder::class, fn (OccasionReminder $n) => $n->occasion->is($barsi));
    }

    public function test_birthdays_and_death_anniversaries_from_the_tree_are_reminded_too(): void
    {
        Notification::fake();
        $today = CarbonImmutable::today();

        // The demo seeder gives Inaya a birthday two days from now.
        $this->artisan('family:remind', ['--date' => $today->addDay()->toDateString()])->assertSuccessful();
        Notification::assertSentTo($this->owner, DatesReminder::class, function (DatesReminder $n) {
            $texts = collect($n->items)->pluck('text')->join(' ');

            return $n->when === 'tomorrow' && str_contains($texts, 'Inaya') && str_contains($n->toMail($this->owner)->render(), 'Inaya');
        });
        Notification::assertSentTo($this->zara, DatesReminder::class);

        $this->artisan('family:remind', ['--date' => $today->addDay()->toDateString()]);
        Notification::assertSentToTimes($this->owner, DatesReminder::class, 1);

        // Abdul Rahman passed away on 19 January 2008: remembered on the day.
        $next = $today->setDate($today->year, 1, 19);
        $next = $next->lt($today) ? $next->addYear() : $next;
        $this->artisan('family:remind', ['--date' => $next->toDateString()]);
        Notification::assertSentTo($this->owner, DatesReminder::class, fn (DatesReminder $n) => $n->when === 'today' && str_contains(collect($n->items)->pluck('text')->join(' '), 'Remembering Abdul Rahman'));

        // Imran & Neha married 28 November 1993: anniversary the day before.
        $ann = $today->setDate($today->year, 11, 28);
        $ann = $ann->lt($today) ? $ann->addYear() : $ann;
        $this->artisan('family:remind', ['--date' => $ann->subDay()->toDateString()]);
        Notification::assertSentTo($this->owner, DatesReminder::class, fn (DatesReminder $n) => $n->when === 'tomorrow' && str_contains(collect($n->items)->pluck('text')->join(' '), 'Imran & Neha'));
    }

    public function test_weekly_digest_lists_the_week_ahead(): void
    {
        Notification::fake();

        $this->artisan('family:digest')->assertSuccessful();
        Notification::assertSentTo($this->owner, WeeklyDigest::class, function (WeeklyDigest $n) {
            $texts = collect($n->items)->pluck('text')->join(' ');

            return $n->family->is($this->family) && str_contains($texts, 'Inaya') && str_contains($texts, 'Imran') && str_contains($n->toMail($this->owner)->render(), 'This week in the Rahman Family');
        });
    }

    public function test_members_can_turn_reminder_emails_off_and_the_unsubscribe_link_works_without_signing_in(): void
    {
        Notification::fake();

        $this->actingAs($this->zara)->put(route('reminders.update', $this->family), ['email_reminders' => 0])->assertRedirect();
        $this->assertFalse($this->zara->membershipIn($this->family)->email_reminders);
        $this->artisan('family:remind', ['--date' => $this->wedding()->date->subDays(7)->toDateString()]);
        Notification::assertSentTo($this->owner, OccasionReminder::class);
        Notification::assertNotSentTo($this->zara, OccasionReminder::class);
        $this->artisan('family:digest');
        Notification::assertNotSentTo($this->zara, WeeklyDigest::class);

        $this->actingAs($this->zara)->put(route('reminders.update', $this->family), ['email_reminders' => 1]);
        $this->assertTrue($this->zara->membershipIn($this->family)->fresh()->email_reminders);

        $member = $this->owner->membershipIn($this->family);
        $this->get(route('reminders.unsubscribe', $member))->assertForbidden(); // unsigned
        $this->get(URL::signedRoute('reminders.unsubscribe', ['member' => $member->id]))->assertOk()->assertSee('Reminder emails are off');
        $this->assertFalse($member->fresh()->email_reminders);
    }

    public function test_members_rsvp_and_visitors_cannot(): void
    {
        $wedding = $this->wedding();
        $this->actingAs($this->owner)->post(route('occasions.rsvp', [$this->family, $wedding]), ['status' => 'going', 'guests' => 2, 'note' => 'With the kids'])->assertRedirect();
        $this->actingAs($this->owner)->post(route('occasions.rsvp', [$this->family, $wedding]), ['status' => 'maybe', 'guests' => 2])->assertRedirect();
        $this->assertDatabaseHas('occasion_rsvps', ['occasion_id' => $wedding->id, 'user_id' => $this->owner->id, 'status' => 'maybe', 'guests' => 0]);
        $this->assertSame(['going' => 2, 'maybe' => 1, 'no' => 0], $wedding->fresh()->load('rsvps')->headcount());

        $this->family->update(['is_public' => true]);
        auth()->logout();
        $this->get(route('occasions.show', [$this->family, $wedding]))->assertOk()->assertSee('sign in')->assertDontSee('Landing Friday morning');
        $this->post(route('occasions.rsvp', [$this->family, $wedding]), ['status' => 'going'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('occasions.rsvp', [$this->family, $wedding]), ['status' => 'going'])->assertForbidden();
    }

    public function test_calendar_feeds_carry_occasions_birthdays_and_anniversaries(): void
    {
        $this->actingAs($this->owner)->get(route('calendar.download', $this->family))
            ->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR', false)->assertSee('wedding', false)->assertSee('RRULE:FREQ=YEARLY', false)->assertSee('Inaya Rahman', false)->assertSee('anniversary', false);

        $this->actingAs($this->owner)->get(route('calendar.occasion', [$this->family, $this->wedding()]))->assertOk()->assertSee('BEGIN:VEVENT', false)->assertSee('Gomti Nagar', false)->assertDontSee('Inaya');

        $token = $this->zara->membershipIn($this->family)->calendarToken();
        $this->assertSame($token, $this->zara->membershipIn($this->family)->fresh()->calendar_token);
        $this->get(route('calendar.feed', $token))->assertOk()->assertSee('X-WR-CALNAME:Rahman Family', false);
        $this->get(route('calendar.feed', str_repeat('x', 40)))->assertNotFound();
    }

    public function test_only_the_creator_or_an_editor_can_remove_an_occasion(): void
    {
        $wedding = $this->wedding(); // created by the owner
        $this->actingAs($this->zara)->delete(route('occasions.destroy', [$this->family, $wedding]))->assertForbidden();
        $this->actingAs($this->zara)->put(route('occasions.update', [$this->family, $wedding]), ['type' => 'wedding', 'title' => 'Arman & Hiba’s wedding', 'date' => $wedding->date->toDateString()])->assertRedirect();
        $this->actingAs($this->owner)->delete(route('occasions.destroy', [$this->family, $wedding]))->assertRedirect(route('occasions.index', $this->family));
        $this->assertDatabaseMissing('occasions', ['id' => $wedding->id]);
    }
}
