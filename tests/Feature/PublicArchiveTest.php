<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\DemoFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An archive opened to visitors can be browsed without an account; everything that
 * adds or changes content still requires a signed-in member with the right role.
 */
class PublicArchiveTest extends TestCase
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

    private function person(string $given): Person
    {
        return Person::where('family_id', $this->family->id)->where('given_name', $given)->firstOrFail();
    }

    public function test_a_private_archive_sends_visitors_to_sign_in(): void
    {
        $media = $this->family->media()->first();

        $this->get(route('families.show', $this->family))->assertRedirect(route('login'));
        $this->get(route('people.show', [$this->family, $this->person('Abdul')]))->assertRedirect(route('login'));
        $this->get(route('media.file', $media))->assertRedirect(route('login'));
        $this->get('/')->assertOk()->assertSee('Start your family archive');
    }

    public function test_visitors_can_browse_an_open_archive_without_an_account(): void
    {
        $this->family->update(['is_public' => true]);
        $f = $this->family;
        $abdul = $this->person('Abdul');
        $story = $f->stories()->first();
        $media = $f->media()->first();
        $album = $f->albums()->create(['title' => 'Weddings']);

        $this->get(route('families.show', $f))->assertOk()
            ->assertSee('Browsing as a visitor')->assertSee('Sign in')
            ->assertDontSee('Recent activity')->assertDontSee('Upload photos')->assertDontSee('Record an elder')->assertDontSee('Sign out');

        $readOnly = [
            route('search', [$f, 'q' => 'Rahman']),
            route('people.index', $f), route('people.show', [$f, $abdul]), route('people.timeline', [$f, $abdul]),
            route('tree.show', $f), route('tree.pedigree', [$f, $this->person('Arman')]), route('tree.pedigree.download', [$f, $this->person('Arman')]),
            route('relationship.calculator', [$f, 'a' => $this->person('Arman')->id, 'b' => $this->person('Zara')->id]),
            route('timeline', $f), route('places.index', $f), route('events.index', $f),
            route('stories.index', $f), route('stories.show', [$f, $story]),
            route('media.index', $f), route('media.show', [$f, $media]), route('albums.show', [$f, $album]),
            route('sources.index', $f), route('questions.index', $f), route('book', $f),
            route('media.file', $media), route('media.file', [$media, 'thumb' => 1]),
        ];
        foreach ($readOnly as $url) {
            $this->get($url)->assertSuccessful();
        }

        // No add/edit controls are offered to visitors
        $this->get(route('people.show', [$f, $abdul]))->assertDontSee(route('people.edit', [$f, $abdul]));
        $this->get(route('stories.index', $f))->assertDontSee('Write a story');
        $this->get(route('media.index', $f))->assertDontSee('>Upload<', false);
    }

    public function test_visitors_must_sign_in_to_add_or_change_anything(): void
    {
        $this->family->update(['is_public' => true]);
        $f = $this->family;
        $abdul = $this->person('Abdul');
        $story = $f->stories()->first();

        $pages = [
            route('people.create', $f), route('people.edit', [$f, $abdul]), route('stories.create', $f), route('stories.edit', [$f, $story]),
            route('events.create', $f), route('media.create', $f), route('interviews.index', $f), route('interviews.create', $f),
            route('review.index', $f), route('members.index', $f), route('activity.index', $f), route('export.index', $f),
            route('export.gedcom', $f), route('families.edit', $f),
        ];
        foreach ($pages as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->post(route('people.store', $f), ['given_name' => 'Intruder', 'gender' => 'unknown'])->assertRedirect(route('login'));
        $this->assertDatabaseMissing('persons', ['given_name' => 'Intruder']);

        $this->post(route('stories.store', $f), ['title' => 'Intruder story', 'body' => 'x'])->assertRedirect(route('login'));
        $this->assertDatabaseMissing('stories', ['title' => 'Intruder story']);

        $this->put(route('families.update', $f), ['name' => 'Hijacked', 'primary_language' => 'en'])->assertRedirect(route('login'));
        $this->assertSame($this->family->name, $f->fresh()->name);

        $this->delete(route('stories.destroy', [$f, $story]))->assertRedirect(route('login'));
        $this->assertDatabaseHas('stories', ['id' => $story->id]);
    }

    public function test_signing_in_after_being_redirected_returns_to_the_requested_page(): void
    {
        $this->family->update(['is_public' => true]);
        $target = route('people.create', $this->family);

        $this->get($target)->assertRedirect(route('login'));
        $this->post('/login', ['login' => 'owner@heirloom.test', 'password' => 'password'])->assertRedirect($target);
    }

    public function test_living_relatives_can_be_hidden_from_visitors(): void
    {
        $this->family->update(['is_public' => true, 'hide_living_from_viewers' => true]);

        $this->get(route('people.show', [$this->family, $this->person('Arman')]))->assertForbidden();
        $this->get(route('people.show', [$this->family, $this->person('Abdul')]))->assertOk();
        $this->get(route('people.index', $this->family))->assertOk()->assertDontSee('Arman');
    }

    public function test_visitors_never_see_edit_controls_for_records_whose_author_is_gone(): void
    {
        $this->family->update(['is_public' => true]);
        $story = $this->family->stories()->first();
        $story->update(['created_by' => null]);

        $this->get(route('stories.show', [$this->family, $story]))->assertOk()->assertDontSee(route('stories.edit', [$this->family, $story]));
    }

    public function test_a_signed_in_non_member_browses_an_open_archive_as_a_visitor(): void
    {
        $this->family->update(['is_public' => true]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('families.show', $this->family))->assertOk()->assertSee('Visitor')->assertSee('Sign out');
        $this->actingAs($stranger)->get(route('people.create', $this->family))->assertForbidden();
        $this->actingAs($stranger)->post(route('people.store', $this->family), ['given_name' => 'Intruder', 'gender' => 'unknown'])->assertForbidden();
        $this->assertNull($stranger->fresh()->current_family_id, 'browsing someone else\'s archive must not make it the user\'s current family');

        // A closed archive still refuses non-members
        $this->family->update(['is_public' => false]);
        $this->actingAs($stranger)->get(route('families.show', $this->family))->assertForbidden();
    }

    public function test_members_keep_their_roles_on_an_open_archive(): void
    {
        $this->family->update(['is_public' => true]);
        $contributor = User::where('email', 'zara@heirloom.test')->firstOrFail();

        $this->actingAs($this->owner)->get(route('families.show', $this->family))->assertOk()->assertSee('Recent activity')->assertSee('Record an elder');
        $this->actingAs($contributor)->get(route('stories.create', $this->family))->assertOk();
        $this->actingAs($contributor)->get(route('review.index', $this->family))->assertForbidden();
    }

    public function test_the_front_page_leads_visitors_to_open_archives(): void
    {
        // Nothing open: the ordinary landing page
        $this->get('/')->assertOk()->assertDontSee('Browse a family archive');

        // Exactly one open archive: straight in
        $this->family->update(['is_public' => true]);
        $this->get('/')->assertRedirect(route('families.show', $this->family));

        // Several: list them
        $other = Family::create(['name' => 'Thompson Family', 'slug' => 'thompson', 'owner_id' => $this->owner->id, 'is_public' => true]);
        $this->get('/')->assertOk()->assertSee('Browse a family archive')->assertSee($this->family->name)->assertSee('Thompson Family');
        $this->get(route('families.show', $other))->assertOk();
    }

    public function test_admins_open_and_close_the_archive_from_settings(): void
    {
        $this->assertFalse($this->family->is_public);
        $base = ['name' => $this->family->name, 'primary_language' => 'en'];

        $this->actingAs($this->owner)->get(route('families.edit', $this->family))->assertOk()->assertSee('Open the archive to visitors');
        $this->actingAs($this->owner)->put(route('families.update', $this->family), $base + ['is_public' => 1])->assertRedirect();
        $this->assertTrue($this->family->fresh()->is_public);
        $this->actingAs($this->owner)->get(route('families.edit', $this->family))->assertSee('This archive is open to visitors');

        $this->actingAs($this->owner)->put(route('families.update', $this->family), $base)->assertRedirect();
        $this->assertFalse($this->family->fresh()->is_public);

        $contributor = User::where('email', 'zara@heirloom.test')->firstOrFail();
        $this->actingAs($contributor)->put(route('families.update', $this->family), $base + ['is_public' => 1])->assertForbidden();
        $this->assertFalse($this->family->fresh()->is_public);
    }
}
