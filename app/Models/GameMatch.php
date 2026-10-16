<?php

namespace App\Models;

use App\Enums\GameFormat;
use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksAuthors;
use Database\Factories\GameMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Partit (taula `matches`; `Match` és una paraula reservada de PHP).
 */
#[Table(name: 'matches')]
#[Fillable([
    'matchday_id', 'team_id', 'round_label', 'opponent', 'home_away', 'status', 'game_format',
    'match_date', 'kickoff_time', 'call_time', 'duration_min', 'post_min', 'field_area_id', 'venue_id',
    'venue_name', 'address', 'municipality', 'maps_url', 'is_locked', 'placement_source',
    'rule_override_reason', 'notes', 'whatsapp_extra', 'show_in_image',
])]
class GameMatch extends Model
{
    /** @use HasFactory<GameMatchFactory> */
    use Auditable, HasFactory, SoftDeletes, TracksAuthors;

    protected function casts(): array
    {
        return [
            'home_away' => HomeAway::class,
            'status' => MatchStatus::class,
            'game_format' => GameFormat::class,
            'match_date' => 'date',
            'duration_min' => 'integer',
            'post_min' => 'integer',
            'is_locked' => 'boolean',
            'show_in_image' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function auditEventPrefix(): string
    {
        return 'match';
    }

    /** @return BelongsTo<Matchday, $this> */
    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class)->withTrashed();
    }

    /** @return BelongsTo<FieldArea, $this> */
    public function fieldArea(): BelongsTo
    {
        return $this->belongsTo(FieldArea::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<WhatsAppMessage, $this> */
    public function whatsAppMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'match_id');
    }
}
