<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Database\Seeders\DemoFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FamilyDeletionTest extends TestCase
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

    public function test_owner_can_delete_the_whole_archive_including_files(): void
    {
        $this->actingAs($this->owner)->post(route('media.store', $this->family), [
            'files' => [UploadedFile::fake()->image('old.jpg', 600, 400)],
        ])->assertRedirect();
        $path = $this->family->media()->firstOrFail()->path;
        Storage::disk('local')->assertExists($path);
        $this->assertGreaterThan(0, $this->family->people()->count());

        $this->actingAs($this->owner)
            ->delete(route('families.destroy', $this->family), ['confirm_name' => $this->family->name])
            ->assertRedirect(route('families.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('families', ['id' => $this->family->id]);
        $this->assertDatabaseMissing('persons', ['family_id' => $this->family->id]);
        $this->assertDatabaseMissing('family_members', ['family_id' => $this->family->id]);
        $this->assertDatabaseMissing('media', ['family_id' => $this->family->id]);
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($this->owner->fresh()->current_family_id);
    }

    public function test_deletion_requires_typing_the_family_name(): void
    {
        $this->actingAs($this->owner)
            ->from(route('families.edit', $this->family))
            ->delete(route('families.destroy', $this->family), ['confirm_name' => 'wrong name'])
            ->assertRedirect(route('families.edit', $this->family))
            ->assertSessionHasErrors('confirm_name');

        $this->assertDatabaseHas('families', ['id' => $this->family->id]);
    }

    public function test_only_the_owner_can_delete_even_if_admin(): void
    {
        $admin = User::factory()->create();
        FamilyMember::create(['family_id' => $this->family->id, 'user_id' => $admin->id, 'role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('families.destroy', $this->family), ['confirm_name' => $this->family->name])
            ->assertForbidden();
        $this->actingAs($admin)->get(route('families.edit', $this->family))->assertOk()->assertDontSee('Delete this tree');
        $this->actingAs($this->owner)->get(route('families.edit', $this->family))->assertOk()->assertSee('Delete this tree');

        $this->assertDatabaseHas('families', ['id' => $this->family->id]);
    }
}
