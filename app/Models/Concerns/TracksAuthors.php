<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Omple created_by / updated_by amb l'usuari autenticat.
 */
trait TracksAuthors
{
    public static function bootTracksAuthors(): void
    {
        static::creating(function ($model): void {
            if (Auth::id() !== null) {
                $model->created_by ??= Auth::id();
                $model->updated_by ??= Auth::id();
            }
        });

        static::updating(function ($model): void {
            if (Auth::id() !== null) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
