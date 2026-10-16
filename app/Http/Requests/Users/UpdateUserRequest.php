<?php

namespace App\Http\Requests\Users;

use App\Enums\RoleCode;
use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:64', 'regex:/^[\pL\pN._-]+$/u', Rule::unique('users', 'username')->ignore($user)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['sometimes', 'required', Rule::enum(RoleCode::class)],
            'is_active' => ['sometimes', 'boolean'],
            // Si s'informa, és una contrasenya temporal i s'obliga a canviar-la.
            'password' => ['sometimes', 'nullable', 'string', PasswordRules::strong()],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'usuari',
            'name' => 'nom',
            'email' => 'correu electrònic',
            'role' => 'rol',
            'is_active' => 'actiu',
            'password' => 'contrasenya temporal',
        ];
    }
}
