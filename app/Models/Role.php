<?php

namespace App\Models;

use App\Enums\RoleCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class Role extends Model
{
    protected function casts(): array
    {
        return [
            'code' => RoleCode::class,
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function forCode(RoleCode $code): self
    {
        return static::query()->where('code', $code->value)->firstOrFail();
    }
}
