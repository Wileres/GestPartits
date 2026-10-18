<?php

namespace Tests\Feature\Catalog;

use App\Models\ApplicationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

/**
 * Tots els equips comparteixen l'escut del club (Configuració → Aparença), que també es veu al login.
 * Cas 19: es rebutgen els fitxers pujats amb tipus no permesos.
 */
class ClubLogoTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
        Storage::fake('local');
    }

    private function upload(UploadedFile $file): TestResponse
    {
        return $this->actingAs($this->admin(), 'web')->spa()->post('/api/settings/logo', ['logo' => $file]);
    }

    private function png(int $size = 64): string
    {
        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, 11, 78, 162));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function storedPath(): ?string
    {
        return ApplicationSetting::get('branding')['logo_path'] ?? null;
    }

    public function test_png_is_re_encoded_and_stored_once_for_the_whole_club(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('escut del club.png', $this->png()))
            ->assertOk()
            ->assertJsonPath('data.has_logo', true);

        $path = $this->storedPath();
        $this->assertMatchesRegularExpression('#^logos/club/[0-9A-Z]{26}\.png$#', $path);
        $this->assertStringNotContainsString('escut', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('image/png', (new \finfo(FILEINFO_MIME_TYPE))->buffer(Storage::disk('local')->get($path)));
    }

    public function test_jpeg_is_accepted_and_converted_to_png(): void
    {
        $this->upload(UploadedFile::fake()->image('escut.jpg', 200, 200))->assertOk();

        $this->assertStringEndsWith('.png', $this->storedPath());
    }

    public function test_dangerous_or_unsupported_files_are_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->upload(UploadedFile::fake()->createWithContent('escut.svg', $svg))->assertUnprocessable()->assertJsonValidationErrors(['logo']);
        $this->upload(UploadedFile::fake()->createWithContent('escut.png', '<?php system($_GET["c"]); ?>'))->assertUnprocessable()->assertJsonValidationErrors(['logo']);
        $this->upload(UploadedFile::fake()->createWithContent('escut.php', $this->png()))->assertUnprocessable()->assertJsonValidationErrors(['logo']);
        $this->upload(UploadedFile::fake()->image('escut.gif'))->assertUnprocessable()->assertJsonValidationErrors(['logo']);

        $this->assertNull($this->storedPath());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_oversized_files_are_rejected(): void
    {
        $this->upload(UploadedFile::fake()->image('escut.png', 100, 100)->size(3000))
            ->assertUnprocessable()
            ->assertJsonPath('errors.logo.0', 'El camp escut no pot pesar més de 2048 kilobytes.');
    }

    public function test_replacing_the_logo_deletes_the_previous_file(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('a.png', $this->png()))->assertOk();
        $first = $this->storedPath();

        $this->upload(UploadedFile::fake()->createWithContent('b.png', $this->png(80)))->assertOk();

        Storage::disk('local')->assertMissing($first);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_only_administrators_can_change_the_logo(): void
    {
        $this->actingAs($this->manager(), 'web')->spa()->post('/api/settings/logo', ['logo' => UploadedFile::fake()->createWithContent('a.png', $this->png())])->assertForbidden();
        $this->spa()->deleteJson('/api/settings/logo')->assertForbidden();

        $this->assertNull($this->storedPath());
    }

    public function test_the_logo_is_public_so_that_the_login_screen_can_show_it(): void
    {
        $this->spa()->getJson('/api/public/branding')->assertOk()->assertJsonPath('data.logo_url', null)->assertJsonPath('data.club_name', 'Joventut Sant Pere Màrtir');
        $this->spa()->getJson('/api/public/logo')->assertNotFound();

        $this->upload(UploadedFile::fake()->createWithContent('escut.png', $this->png()))->assertOk();
        $this->app['auth']->forgetGuards();
        auth('web')->logout();

        // Sense sessió: es veu el nom i l'escut, i res més.
        $branding = $this->spa()->getJson('/api/public/branding')->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertStringContainsString('/api/public/logo', $branding['logo_url']);
        $this->get('/api/public/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_teams_no_longer_have_their_own_logo(): void
    {
        $team = $this->team('primer-equip');
        $this->actingAs($this->manager(), 'web');

        $this->assertFalse(\Schema::hasColumn('teams', 'logo_path'));
        $this->spa()->post("/api/teams/{$team->id}/logo", ['logo' => UploadedFile::fake()->image('escut.png')])->assertNotFound();
        $this->assertArrayNotHasKey('logo_url', $this->spa()->getJson('/api/teams')->json('data.0'));
    }
}
