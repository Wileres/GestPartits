<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksAuthors;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'starts_on', 'ends_on', 'is_current'])]
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use Auditable, HasFactory, TracksAuthors;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /** @return HasMany<Matchday, $this> */
    public function matchdays(): HasMany
    {
        return $this->hasMany(Matchday::class)->orderBy('starts_on');
    }
}
