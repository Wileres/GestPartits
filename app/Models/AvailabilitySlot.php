<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Franja d'inici permès. Setmanal (weekday) o excepció d'una data (specific_date):
 * si una data té franges pròpies, substitueixen les setmanals d'aquell dia.
 */
#[Fillable(['venue_id', 'weekday', 'specific_date', 'label', 'opens_at', 'last_kickoff_at', 'default_fill_order', 'is_active'])]
class AvailabilitySlot extends Model
{
    use Auditable, TracksAuthors;

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'specific_date' => 'date',
            'default_fill_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
