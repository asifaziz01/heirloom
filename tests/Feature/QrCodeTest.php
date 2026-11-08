<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\User;
use chillerlan\QRCode\QRCode;
use Database\Seeders\DemoFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admins can turn the family's home page address into a QR code and download it for printing.
 */
class QrCodeTest extends TestCase
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

    public function test_admins_see_a_qr_code_for_the_home_page(): void
    {
        $home = route('families.show', $this->family);

        $this->actingAs($this->owner)->get(route('qr.show', $this->family))
            ->assertOk()
            ->assertSee('QR code')
            ->assertSee($home)
            ->assertSee('<svg', false)
            ->assertSee('members only') // the archive is private, so scanners will be asked to sign in
            ->assertSee(route('qr.download', [$this->family, 'format' => 'png', 'size' => 1024]))
            ->assertSee(route('qr.download', [$this->family, 'format' => 'svg']));

        $this->family->update(['is_public' => true]);
        $this->actingAs($this->owner)->get(route('qr.show', $this->family))->assertOk()->assertDontSee('members only');
    }

    public function test_the_downloaded_png_scans_to_the_home_page_address(): void
    {
        $home = route('families.show', $this->family);

        $response = $this->actingAs($this->owner)->get(route('qr.download', [$this->family, 'format' => 'png', 'size' => 512]));
        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$this->family->slug.'-qr-code-512.png"');

        $png = $response->getContent();
        $this->assertStringStartsWith("\x89PNG", $png);
        [$width, $height] = getimagesizefromstring($png);
        $this->assertSame($width, $height);
        $this->assertGreaterThanOrEqual(400, $width);
        $this->assertLessThanOrEqual(512, $width);

        $this->assertSame($home, (string) (new QRCode)->readFromBlob($png));
    }

    public function test_the_svg_download_is_a_standalone_file(): void
    {
        $response = $this->actingAs($this->owner)->get(route('qr.download', [$this->family, 'format' => 'svg']));
        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$this->family->slug.'-qr-code.svg"');
        $this->assertStringStartsWith('<?xml', $response->getContent());
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_bad_download_options_are_rejected(): void
    {
        $this->actingAs($this->owner)->get(route('qr.download', [$this->family, 'format' => 'gif']))->assertSessionHasErrors('format');
        $this->actingAs($this->owner)->get(route('qr.download', [$this->family, 'format' => 'png', 'size' => 999]))->assertSessionHasErrors('size');
    }

    public function test_only_admins_can_generate_qr_codes(): void
    {
        $this->get(route('qr.show', $this->family))->assertRedirect(route('login'));

        // Opening the archive to visitors does not open the generator to them.
        $this->family->update(['is_public' => true]);
        $this->get(route('qr.show', $this->family))->assertRedirect(route('login'));
        $this->get(route('qr.download', [$this->family, 'format' => 'svg']))->assertRedirect(route('login'));

        // Contributors and editors are members, but only admins and the owner may generate codes.
        $this->actingAs($this->contributor)->get(route('qr.show', $this->family))->assertForbidden();
        $this->actingAs($this->contributor)->get(route('qr.download', [$this->family, 'format' => 'png']))->assertForbidden();
    }
}
