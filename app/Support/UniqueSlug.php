<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class UniqueSlug
{
    /**
     * Slug únic a la taula del model (inclou els registres esborrats lògicament).
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function for(string $modelClass, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'element';
        $slug = $base;
        $suffix = 2;

        while (self::exists($modelClass, $slug, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function exists(string $modelClass, string $slug, ?int $ignoreId): bool
    {
        $query = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
            ? $modelClass::withTrashed()
            : $modelClass::query();

        return $query->where('slug', $slug)->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
    }
}
