<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta, modificació i baixa d'usuaris amb les regles que no són d'autorització:
 * sempre ha de quedar com a mínim un administrador actiu i ningú no es pot donar de baixa a si mateix.
 */
class UserService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{username: string, name: string, email?: string|null, role: string, password: string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'username' => $data['username'],
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'role_id' => Role::forCode(RoleCode::from($data['role']))->id,
                'password' => $data['password'],
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $this->audit->log('user.created', $user, null, $user->only(['username', 'name', 'email', 'role_id']));

            return $user->load('role');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data): User {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('role', $data)) {
                $role = RoleCode::from($data['role']);
                if ($role !== RoleCode::Admin) {
                    $this->ensureNotLastAdmin($user);
                }
                $user->role_id = Role::forCode($role)->id;
            }

            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                if ($actor->is($user)) {
                    throw ValidationException::withMessages(['is_active' => __('messages.users.cannot_deactivate_self')]);
                }
                $this->ensureNotLastAdmin($user);
            }

            $user->fill(array_intersect_key($data, array_flip(['username', 'name', 'email', 'is_active'])));

            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->must_change_password = true;
                $user->password_changed_at = null;
            }

            $user->save();

            $this->audit->logChanges('user.updated', $user);

            if (! empty($data['password'])) {
                $this->audit->log('user.password_reset_by_admin', $user);
            }

            return $user->load('role');
        });
    }

    public function delete(User $actor, User $user): void
    {
        DB::transaction(function () use ($actor, $user): void {
            if ($actor->is($user)) {
                throw ValidationException::withMessages(['user' => __('messages.users.cannot_delete_self')]);
            }

            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->ensureNotLastAdmin($user);

            $user->is_active = false;
            $user->save();
            $user->delete();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            $this->audit->log('user.deleted', $user, $user->only(['username', 'name']));
        });
    }

    private function ensureNotLastAdmin(User $user): void
    {
        if (! $user->isAdmin()) {
            return;
        }

        $otherAdmins = User::query()
            ->whereKeyNot($user->id)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('code', RoleCode::Admin->value))
            ->lockForUpdate()
            ->count();

        if ($otherAdmins === 0) {
            throw ValidationException::withMessages(['role' => __('messages.users.last_admin')]);
        }
    }
}
