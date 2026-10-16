<?php

namespace App\Models;

use App\Enums\FieldAreaKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['venue_id', 'code', 'name', 'kind', 'sort_order'])]
class FieldArea extends Model
{
    public const CODE_CAMP_1 = 'camp_1';

    public const CODE_CAMP_2 = 'camp_2';

    public const CODE_FULL = 'full';

    protected function casts(): array
    {
        return [
            'kind' => FieldAreaKind::class,
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
