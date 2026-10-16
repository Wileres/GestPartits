<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuració clau/valor (valor JSON).
 */
#[Fillable(['key', 'value', 'updated_by'])]
class ApplicationSetting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting === null ? $default : $setting->value;
    }

    public static function put(string $key, mixed $value, ?int $userId = null): self
    {
        return static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
    }
}
