<?php

namespace App\Models;

use App\Enums\DestinationScope;
use App\Enums\GameFormat;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksAuthors;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category_id', 'name', 'slug', 'game_format', 'color', 'match_duration_min', 'post_min', 'logo_path', 'sort_order', 'is_active'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use Auditable, HasFactory, SoftDeletes, TracksAuthors;

    protected function casts(): array
    {
        return [
            'game_format' => GameFormat::class,
            'match_duration_min' => 'integer',
            'post_min' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<GameMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    /** @return HasMany<SchedulingRule, $this> */
    public function schedulingRules(): HasMany
    {
        return $this->hasMany(SchedulingRule::class);
    }

    /** @return HasMany<WhatsAppDestination, $this> */
    public function whatsAppDestinations(): HasMany
    {
        return $this->hasMany(WhatsAppDestination::class)->where('scope', DestinationScope::Team->value);
    }

    public function effectiveGameFormat(): GameFormat
    {
        return $this->game_format ?? $this->category->game_format;
    }

    public function effectiveDurationMin(): int
    {
        return $this->match_duration_min ?? $this->category->match_duration_min;
    }

    public function effectivePostMin(): int
    {
        return $this->post_min ?? $this->category->post_min;
    }
}
