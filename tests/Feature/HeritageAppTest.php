<?php

namespace Tests\Feature;

use App\Models\ChangeRequest;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Person;
use App\Models\User;
use App\Services\RelationshipCalculator;
use Database\Seeders\DemoFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeritageAppTest extends TestCase
{
    use RefreshDatabase;

    private Family $family;

    private User $owner;

    private User $contributor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DemoFamilySeeder::class);
        $this->family = Family::firstOrFail();
        $this->owner = User::where('email', 'owner@heirloom.test')->firstOrFail();
        $this->contributor = User::where('email', 'zara@heirloom.test')->firstOrFail();
    }

    private function person(string $given): Person
    {
        return Person::where('family_id', $this->family->id)->where('given_name', $given)->firstOrFail();
    }

    public function test_every_page_renders_for_the_owner(): void
    {
        $f = $this->family;
        $abdul = $this->person('Abdul');
        $story = $f->stories()->first();
        $media = $f->media()->first();
        $event = $f->events()->first();
        $partnership = $f->partnerships()->first();
        $interview = $f->interviews()->create(['title' => 'Test', 'transcript' => 'Hello', 'person_id' => $abdul->id]);
        $album = $f->albums()->create(['title' => 'Weddings']);

        $urls = [
            route('families.index'), route('families.create'),
            route('families.show', $f), route('search', [$f, 'q' => 'Rahman']),
            route('people.index', $f), route('people.index', [$f, 'sort' => 'born', 'filter' => 'living']),
            route('people.show', [$f, $abdul]), route('people.timeline', [$f, $abdul]),
            route('people.create', $f), route('people.create', [$f, 'relation' => 'child', 'of' => $abdul->id]),
            route('people.create', [$f, 'relation' => 'parent', 'of' => $abdul->id]), route('people.edit', [$f, $abdul]),
            route('tree.show', $f), route('tree.show', [$f, 'root' => $abdul->id, 'depth' => 6]),
            route('tree.pedigree', [$f, $this->person('Arman')]), route('tree.pedigree.download', [$f, $this->person('Arman')]),
            route('relationship.calculator', [$f, 'a' => $this->person('Arman')->id, 'b' => $this->person('Zara')->id]),
            route('timeline', $f), route('places.index', $f), route('events.index', $f), route('events.create', $f), route('events.edit', [$f, $event]),
            route('stories.index', $f), route('stories.show', [$f, $story]), route('stories.create', $f), route('stories.edit', [$f, $story]),
            route('media.index', $f), route('media.create', $f), route('media.show', [$f, $media]), route('albums.show', [$f, $album]),
            route('sources.index', $f), route('questions.index', $f), route('book', $f),
            route('partnerships.edit', [$f, $partnership]),
            route('interviews.index', $f), route('interviews.create', $f), route('interviews.show', [$f, $interview]),
            route('review.index', $f), route('members.index', $f), route('activity.index', $f), route('export.index', $f),
            route('export.gedcom', $f), route('families.edit', $f),
        ];
        $this->actingAs($this->owner);
        $this->get(route('home'))->assertRedirect(route('families.show', $f));
        foreach ($urls as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    public function test_media_files_are_private_to_family_members(): void
    {
        $media = $this->family->media()->first();
        $this->actingAs($this->owner)->get(route('media.file', $media))->assertOk();
        $this->actingAs($this->owner)->get(route('media.file', [$media, 'thumb' => 1]))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('media.file', $media))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('families.show', $this->family))->assertForbidden();
    }

    public function test_contributor_changes_wait_for_review_and_apply_on_approval(): void
    {
        $zara = $this->person('Zara');
        $this->actingAs($this->contributor)
            ->post(route('people.store', $this->family), ['given_name' => 'Yahya', 'surname' => 'Siddiqui', 'gender' => 'male', 'birth_date' => '2023', 'relation' => 'child', 'of' => $zara->id])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseMissing('persons', ['given_name' => 'Yahya']);
        $request = ChangeRequest::where('summary', 'like', '%Yahya%')->firstOrFail();

        // contributors cannot reach the review queue
        $this->actingAs($this->contributor)->post(route('review.approve', [$this->family, $request]))->assertForbidden();

        $this->actingAs($this->owner)->post(route('review.approve', [$this->family, $request]))->assertRedirect();
        $yahya = $this->person('Yahya');
        $this->assertSame('2023', $yahya->birth_date);
        $this->assertTrue($yahya->parents()->contains('id', $zara->id));
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_editor_adds_child_to_a_specific_marriage(): void
    {
        $yusuf = $this->person('Yusuf');
        $claire = $this->person('Claire');
        $marriage = $this->family->partnerships()->where('partner2_id', $claire->id)->firstOrFail();
        $this->actingAs($this->owner)->post(route('people.store', $this->family), [
            'given_name' => 'Hannah', 'surname' => 'Rahman', 'gender' => 'female', 'is_living' => 1,
            'relation' => 'child', 'of' => $yusuf->id, 'partnership_id' => $marriage->id, 'link_type' => 'biological',
        ])->assertRedirect();
        $hannah = $this->person('Hannah');
        $this->assertEqualsCanonicalizing([$yusuf->id, $claire->id], $hannah->parents()->pluck('id')->all());

        $calc = new RelationshipCalculator($this->family);
        $this->assertSame('half-sister', $calc->describe($this->person('Ayesha')->id, $hannah->id)['label']);
        $this->assertSame('sister', $calc->describe($this->person('Daniel')->id, $hannah->id)['label']);
    }

    public function test_cycles_are_rejected(): void
    {
        $abdul = $this->person('Abdul');
        $arman = $this->person('Arman');
        $this->actingAs($this->owner)
            ->post(route('relationships.link', [$this->family, $abdul]), ['relation' => 'parent', 'other_id' => $arman->id])
            ->assertSessionHasErrors('relation');
        $this->assertFalse($abdul->parents()->contains('id', $arman->id));
    }

    public function test_relationship_labels(): void
    {
        $calc = new RelationshipCalculator($this->family);
        $cases = [
            ['Arman', 'Abdul', 'grandfather'], ['Abdul', 'Inaya', 'granddaughter'], ['Arman', 'Samir', 'first cousin'],
            ['Arman', 'Sana', 'aunt'], ['Samir', 'Imran', 'uncle'], ['Yusuf', 'Ameena', 'stepmother'], ['Oliver', 'Yusuf', 'stepfather'],
            ['Imran', 'Neha', 'wife'], ['Yusuf', 'Nusrat', 'former wife'], ['Abdul', 'Zainab', 'late wife'],
            ['Daniel', 'Ayesha', 'half-sister'], ['Abdul', 'Huma', 'daughter-in-law'], ['Arman', 'Huma', 'aunt by marriage'],
            ['Zara', 'Arman', 'half-first cousin'],
        ];
        foreach ($cases as [$a, $b, $expected]) {
            $this->assertSame($expected, $calc->describe($this->person($a)->id, $this->person($b)->id)['label'], "{$b} relative to {$a}");
        }
    }

    public function test_conflicting_facts_and_preferred_value(): void
    {
        $abdul = $this->person('Abdul');
        $this->assertSame(2, $abdul->facts()->where('field', 'birth_date')->count());
        $alt = $abdul->facts()->where('value', '1931')->firstOrFail();
        $this->actingAs($this->owner)->post(route('facts.prefer', [$this->family, $alt]))->assertRedirect();
        $this->assertSame('1931', $abdul->fresh()->birth_date);
    }

    public function test_upload_tags_people_and_tracks_storage(): void
    {
        $sana = $this->person('Sana');
        $before = $this->family->storage_used;
        $this->actingAs($this->contributor)->post(route('media.store', $this->family), [
            'files' => [UploadedFile::fake()->image('old.jpg', 600, 400)],
            'people' => [$sana->id],
            'taken_date' => '1975',
        ])->assertRedirect();
        $media = $this->family->media()->latest('id')->first();
        $this->assertSame('photo', $media->kind);
        $this->assertTrue($media->people->contains($sana));
        $this->assertGreaterThan($before, $this->family->fresh()->storage_used);
    }

    public function test_free_plan_limits_people(): void
    {
        config(['services.heritage.billing' => true, 'plans.free.people' => 20]);
        $this->family->update(['plan' => 'free']);
        $this->actingAs($this->owner)->post(route('people.store', $this->family), ['given_name' => 'One', 'gender' => 'unknown'])
            ->assertSessionHasErrors('given_name');
        $this->actingAs($this->owner)->get(route('export.archive', $this->family))->assertForbidden();
    }

    public function test_billing_is_hidden_when_disabled(): void
    {
        $this->actingAs($this->owner)->get(route('billing.index', $this->family))->assertNotFound();
        $this->actingAs($this->owner)->get(route('families.show', $this->family))->assertDontSee('Plan</a>', false);
        $this->get('/')->assertDontSee('One plan for the whole family');
        $this->family->update(['plan' => 'free']);
        $this->assertTrue($this->family->fresh()->hasFeature('book'));

        config(['services.heritage.billing' => true]);
        $this->actingAs($this->owner)->get(route('billing.index', $this->family))->assertOk();
    }

    public function test_invitation_flow(): void
    {
        $this->actingAs($this->owner)->post(route('invitations.store', $this->family), ['role' => 'viewer'])->assertRedirect();
        $invitation = $this->family->invitations()->latest('id')->firstOrFail();
        $guest = User::factory()->create();
        $this->get(route('invitations.show', $invitation->token))->assertOk();
        $this->actingAs($guest)->post(route('invitations.accept', $invitation->token))->assertRedirect(route('families.show', $this->family));
        $member = FamilyMember::where('user_id', $guest->id)->firstOrFail();
        $this->assertSame('viewer', $member->role);
        // viewers can browse but not edit
        $this->actingAs($guest)->get(route('tree.show', $this->family))->assertOk();
        $this->actingAs($guest)->get(route('people.create', $this->family))->assertForbidden();
    }

    public function test_living_people_can_be_hidden_from_viewers(): void
    {
        $viewer = User::factory()->create();
        FamilyMember::create(['family_id' => $this->family->id, 'user_id' => $viewer->id, 'role' => 'viewer']);
        $this->family->update(['hide_living_from_viewers' => true]);
        $this->actingAs($viewer)->get(route('people.show', [$this->family, $this->person('Arman')]))->assertForbidden();
        $this->actingAs($viewer)->get(route('people.show', [$this->family, $this->person('Abdul')]))->assertOk();
    }

    public function test_archive_export_builds_a_zip(): void
    {
        $response = $this->actingAs($this->owner)->get(route('export.archive', $this->family));
        $response->assertOk();
        $this->assertStringContainsString('.zip', $response->headers->get('content-disposition'));
    }

    public function test_activity_is_logged_with_changes(): void
    {
        $abdul = $this->person('Abdul');
        $this->actingAs($this->owner)->put(route('people.update', [$this->family, $abdul]), ['given_name' => 'Abdul', 'surname' => 'Rahman', 'gender' => 'male', 'occupation' => 'Headmaster'])->assertRedirect();
        $log = $this->family->activity()->where('subject_type', 'Person')->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('Headmaster', $log->changes['occupation']['to']);
        $this->assertSame($this->owner->id, $log->user_id);
    }
}
