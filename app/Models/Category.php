<?php

namespace App\Models;

use App\Enums\GameFormat;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksAuthors;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'game_format', 'match_duration_min', 'post_min', 'min_gap_min', 'sort_order', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use Auditable, HasFactory, SoftDeletes, TracksAuthors;

    protected function casts(): array
    {
        return [
            'game_format' => GameFormat::class,
            'match_duration_min' => 'integer',
            'post_min' => 'integer',
            'min_gap_min' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /** @return HasMany<SchedulingRule, $this> */
    public function schedulingRules(): HasMany
    {
        return $this->hasMany(SchedulingRule::class);
    }
}
