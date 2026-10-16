<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bloqueig del camp (manteniment, festius...). field_area_id NULL = tot el camp.
 * starts_at / ends_at en hora local del club.
 */
#[Fillable(['venue_id', 'field_area_id', 'starts_at', 'ends_at', 'reason'])]
class FieldBlock extends Model
{
    use Auditable, SoftDeletes, TracksAuthors;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<FieldArea, $this> */
    public function fieldArea(): BelongsTo
    {
        return $this->belongsTo(FieldArea::class);
    }
}
