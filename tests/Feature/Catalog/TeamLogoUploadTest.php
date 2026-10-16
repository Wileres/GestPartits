<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

/**
 * Cas 19: els fitxers pujats amb tipus no permesos es rebutgen.
 */
class TeamLogoUploadTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
        Storage::fake('local');
    }

    private function upload(UploadedFile $file)
    {
        $team = $this->team('primer-equip');

        return $this->actingAs($this->manager(), 'web')->spa()->post("/api/teams/{$team->id}/logo", ['logo' => $file]);
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 11, 78, 162));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function test_png_is_re_encoded_and_stored_with_a_random_name(): void
    {
        $response = $this->upload(UploadedFile::fake()->createWithContent('escut del club.png', $this->pngBytes()))
            ->assertOk()
            ->assertJsonPath('data.has_logo', true);

        $path = $this->team('primer-equip')->logo_path;
        $this->assertMatchesRegularExpression('#^logos/[0-9A-Z]{26}\.png$#', $path);
        $this->assertStringNotContainsString('escut', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('image/png', (new \finfo(FILEINFO_MIME_TYPE))->buffer(Storage::disk('local')->get($path)));

        $this->assertNotNull($response->json('data.logo_url'));
    }

    public function test_jpeg_is_accepted_and_converted_to_png(): void
    {
        $this->upload(UploadedFile::fake()->image('escut.jpg', 200, 200))->assertOk();

        $this->assertStringEndsWith('.png', $this->team('primer-equip')->logo_path);
    }

    public function test_svg_is_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->upload(UploadedFile::fake()->createWithContent('escut.svg', $svg))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);

        $this->assertNull($this->team('primer-equip')->logo_path);
    }

    public function test_php_file_disguised_as_png_is_rejected(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('escut.png', '<?php system($_GET["c"]); ?>'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_png_content_with_executable_extension_is_rejected(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('escut.php', $this->pngBytes()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_gif_is_rejected(): void
    {
        $this->upload(UploadedFile::fake()->image('escut.gif'))->assertUnprocessable()->assertJsonValidationErrors(['logo']);
    }

    public function test_files_larger_than_the_limit_are_rejected(): void
    {
        $this->upload(UploadedFile::fake()->image('escut.png', 100, 100)->size(3000))
            ->assertUnprocessable()
            ->assertJsonPath('errors.logo.0', 'El camp escut no pot pesar més de 2048 kilobytes.');
    }

    public function test_logo_is_served_only_to_authenticated_users(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('escut.png', $this->pngBytes()))->assertOk();
        $team = $this->team('primer-equip');

        $this->get("/api/teams/{$team->id}/logo")->assertOk()->assertHeader('Content-Type', 'image/png');

        auth('web')->logout();
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/teams/{$team->id}/logo")->assertUnauthorized();
    }

    public function test_replacing_the_logo_deletes_the_previous_file(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('a.png', $this->pngBytes()))->assertOk();
        $first = $this->team('primer-equip')->logo_path;

        $this->upload(UploadedFile::fake()->createWithContent('b.png', $this->pngBytes()))->assertOk();

        Storage::disk('local')->assertMissing($first);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }
}
