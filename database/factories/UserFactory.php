<?php

namespace Database\Factories;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Contrasenya en clar de les proves (compleix la política de contrasenyes).
     */
    public const PASSWORD = 'Contrasenya-Prova-2026';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make(self::PASSWORD),
            'role_id' => fn () => Role::query()->firstOrCreate(
                ['code' => RoleCode::Manager->value],
                ['name' => RoleCode::Manager->label()],
            )->id,
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Recarrega l'usuari creat perquè tingui totes les columnes (com quan l'autentica el guard).
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (User $user) => $user->refresh());
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::query()->firstOrCreate(
                ['code' => RoleCode::Admin->value],
                ['name' => RoleCode::Admin->label()],
            )->id,
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true, 'password_changed_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
