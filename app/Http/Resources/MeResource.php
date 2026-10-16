<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuari autenticat amb els permisos que la SPA necessita per mostrar o amagar opcions.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->code->value,
            'role_label' => $this->role->code->label(),
            'must_change_password' => $this->must_change_password,
            'permissions' => [
                'manage_users' => $this->can('viewAny', User::class),
                'manage_settings' => $this->can('manage-settings'),
                'view_audit' => $this->can('view-audit'),
            ],
        ];
    }
}
