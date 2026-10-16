<?php

namespace Tests\Feature\Users;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['username' => 'Gestor']);
    }

    public function test_admin_can_list_users(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->create();

        $this->actingAs($admin, 'web')->spa()->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_admin_can_create_a_user_who_must_change_the_password(): void
    {
        $this->actingAs($this->admin(), 'web')->spa()->postJson('/api/users', [
            'username' => 'entrenador',
            'name' => 'Entrenador Aleví',
            'email' => 'entrenador@example.org',
            'role' => 'manager',
            'password' => 'Temporal2026Segura',
        ])->assertCreated()
            ->assertJsonPath('data.username', 'entrenador')
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.must_change_password', true);

        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created']);
        $this->assertStringNotContainsString('Temporal2026Segura', json_encode(AuditLog::all()->toArray()));
    }

    public function test_validation_messages_are_in_catalan(): void
    {
        $this->actingAs($this->admin(), 'web')->spa()->postJson('/api/users', [
            'username' => 'Gestor',
            'role' => 'superheroi',
            'password' => 'curta',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'Aquest usuari ja existeix.')
            ->assertJsonPath('errors.name.0', 'El camp nom és obligatori.')
            ->assertJsonPath('errors.role.0', 'El valor seleccionat per a rol no és vàlid.')
            ->assertJsonPath('errors.password.0', 'El camp contrasenya temporal ha de tenir com a mínim 12 caràcters.');
    }

    public function test_manager_cannot_manage_users(): void
    {
        $manager = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($manager, 'web')->spa()->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tens permís per fer aquesta acció.');

        $this->spa()->postJson('/api/users', [
            'username' => 'intrus', 'name' => 'Intrús', 'role' => 'admin', 'password' => 'Temporal2026Segura',
        ])->assertForbidden();

        $this->spa()->patchJson("/api/users/{$other->id}", ['role' => 'admin'])->assertForbidden();
        $this->spa()->deleteJson("/api/users/{$other->id}")->assertForbidden();

        $this->assertSame('manager', $other->fresh()->role->code->value);
    }

    public function test_admin_can_reset_a_password_and_force_a_change(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin(), 'web')->spa()->patchJson("/api/users/{$user->id}", [
            'password' => 'NovaTemporal2026',
        ])->assertOk()->assertJsonPath('data.must_change_password', true);

        $this->assertDatabaseHas('audit_logs', ['event' => 'user.password_reset_by_admin', 'auditable_id' => $user->id]);
    }

    public function test_last_admin_cannot_be_demoted_or_deleted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->spa()->patchJson("/api/users/{$admin->id}", ['role' => 'manager'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'Ha de quedar com a mínim un administrador actiu.');

        $this->spa()->deleteJson("/api/users/{$admin->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.user.0', 'No pots donar-te de baixa a tu mateix.');
    }

    public function test_admin_can_delete_another_user_and_their_sessions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin(), 'web')->spa()->deleteJson("/api/users/{$user->id}")->assertNoContent();

        $this->assertSoftDeleted($user);
        $this->assertFalse($user->fresh()->is_active);
    }
}
