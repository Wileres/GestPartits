<?php

namespace App\Models;

use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'address', 'municipality', 'maps_url', 'is_home'])]
class Venue extends Model
{
    use SoftDeletes, TracksAuthors;

    protected function casts(): array
    {
        return [
            'is_home' => 'boolean',
        ];
    }

    /** @return HasMany<FieldArea, $this> */
    public function fieldAreas(): HasMany
    {
        return $this->hasMany(FieldArea::class)->orderBy('sort_order');
    }

    /** @return HasMany<AvailabilitySlot, $this> */
    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(AvailabilitySlot::class);
    }

    /** @return HasMany<FieldBlock, $this> */
    public function fieldBlocks(): HasMany
    {
        return $this->hasMany(FieldBlock::class);
    }

    public static function home(): self
    {
        return static::query()->where('is_home', true)->orderBy('id')->firstOrFail();
    }
}
