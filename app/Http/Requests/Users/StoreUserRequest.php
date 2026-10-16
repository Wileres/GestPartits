<?php

namespace App\Http\Requests\Users;

use App\Enums\RoleCode;
use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[\pL\pN._-]+$/u', Rule::unique('users', 'username')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(RoleCode::class)],
            // Contrasenya temporal: l'usuari l'haurà de canviar en el primer accés.
            'password' => ['required', 'string', PasswordRules::strong()],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'usuari',
            'name' => 'nom',
            'email' => 'correu electrònic',
            'role' => 'rol',
            'password' => 'contrasenya temporal',
        ];
    }
}
