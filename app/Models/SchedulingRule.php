<?php

namespace App\Models;

use App\Enums\GameFormat;
use App\Enums\RuleStrength;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'team_id', 'category_id', 'game_format', 'weekday', 'preferred_time', 'not_before', 'not_after',
    'preferred_field_area_id', 'priority', 'strength', 'valid_from', 'valid_to', 'is_active', 'notes',
])]
class SchedulingRule extends Model
{
    use Auditable, SoftDeletes, TracksAuthors;

    protected function casts(): array
    {
        return [
            'game_format' => GameFormat::class,
            'strength' => RuleStrength::class,
            'weekday' => 'integer',
            'priority' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<FieldArea, $this> */
    public function preferredFieldArea(): BelongsTo
    {
        return $this->belongsTo(FieldArea::class, 'preferred_field_area_id');
    }
}
