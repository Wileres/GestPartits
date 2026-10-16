<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea l'usuari inicial (administrador) a partir de les variables INITIAL_USER_* del .env.
 * La contrasenya no té valor per defecte i no s'escriu mai al repositori.
 */
class InitialUserSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('gestpartits.initial_user');

        if (User::withTrashed()->where('username', $config['username'])->exists()) {
            $this->command?->info("L'usuari inicial «{$config['username']}» ja existeix: no es modifica.");

            return;
        }

        if (blank($config['password'])) {
            $this->command?->warn('No s\'ha creat l\'usuari inicial: falta INITIAL_USER_PASSWORD al .env.');

            return;
        }

        User::query()->create([
            'username' => $config['username'],
            'name' => $config['name'] ?: $config['username'],
            'email' => $config['email'] ?: null,
            'password' => $config['password'],
            'role_id' => Role::forCode(RoleCode::Admin)->id,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $this->command?->info("S'ha creat l'usuari inicial «{$config['username']}» (administrador). Haurà de canviar la contrasenya en el primer accés.");
        $this->command?->warn('Esborra INITIAL_USER_PASSWORD del .env.');
    }
}
