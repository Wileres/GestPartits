<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\InitialUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cas 18: la contrasenya mai no es desa en text pla.
 */
class PasswordStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_passwords_are_stored_as_argon2id_hashes(): void
    {
        $user = User::factory()->create(['password' => 'TextEnClar2026']);

        $stored = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame('TextEnClar2026', $stored);
        $this->assertStringStartsWith('$argon2id$', $stored);
        $this->assertStringNotContainsString('TextEnClar2026', $stored);
        $this->assertTrue(password_verify('TextEnClar2026', $stored));
    }

    public function test_password_is_never_serialised(): void
    {
        $user = User::factory()->create();

        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_initial_user_password_comes_from_the_environment_and_is_hashed(): void
    {
        $this->seed(RoleSeeder::class);
        config(['gestpartits.initial_user.password' => 'ContrasenyaInicial2026']);

        $this->seed(InitialUserSeeder::class);

        $stored = DB::table('users')->where('username', 'Gestor')->value('password');
        $this->assertStringStartsWith('$argon2id$', $stored);
        $this->assertTrue(password_verify('ContrasenyaInicial2026', $stored));
    }

    public function test_initial_password_has_no_default_value_in_the_repository(): void
    {
        $this->assertMatchesRegularExpression('/^INITIAL_USER_PASSWORD=$/m', (string) file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString("'password' => env('INITIAL_USER_PASSWORD'),", (string) file_get_contents(config_path('gestpartits.php')));
    }

    public function test_initial_user_is_not_created_without_a_password(): void
    {
        $this->seed(RoleSeeder::class);
        config(['gestpartits.initial_user.password' => null]);

        $this->seed(InitialUserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }
}
