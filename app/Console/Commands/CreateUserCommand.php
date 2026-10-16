<?php

namespace App\Console\Commands;

use App\Enums\RoleCode;
use App\Services\UserService;
use App\Support\PasswordRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea un usuari des de la terminal. La contrasenya es demana de manera interactiva (no es mostra
 * ni queda a l'historial de l'intèrpret d'ordres).
 */
class CreateUserCommand extends Command
{
    protected $signature = 'app:user:create
        {username : Nom d\'usuari}
        {--name= : Nom complet}
        {--email= : Correu electrònic (per restablir la contrasenya)}
        {--role=manager : Rol (admin o manager)}';

    protected $description = 'Crea un usuari (haurà de canviar la contrasenya en el primer accés)';

    public function handle(UserService $users): int
    {
        $password = (string) $this->secret('Contrasenya temporal');
        $confirmation = (string) $this->secret('Repeteix la contrasenya');

        $data = [
            'username' => (string) $this->argument('username'),
            'name' => (string) ($this->option('name') ?: $this->argument('username')),
            'email' => $this->option('email') ?: null,
            'role' => (string) $this->option('role'),
            'password' => $password,
            'password_confirmation' => $confirmation,
        ];

        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[\pL\pN._-]+$/u', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:'.implode(',', array_column(RoleCode::cases(), 'value'))],
            'password' => ['required', 'confirmed', PasswordRules::strong()],
        ], [], ['username' => 'usuari', 'name' => 'nom', 'email' => 'correu electrònic', 'role' => 'rol', 'password' => 'contrasenya']);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = $users->create($validator->validated());

        $this->info("S'ha creat l'usuari «{$user->username}» ({$user->role->code->label()}).");

        return self::SUCCESS;
    }
}
