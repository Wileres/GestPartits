<?php

namespace App\Models;

use App\Enums\DestinationScope;
use App\Enums\DestinationType;
use App\Enums\WhatsAppProviderKey;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table(name: 'whatsapp_destinations')]
#[Fillable(['scope', 'team_id', 'label', 'provider', 'destination_type', 'external_id', 'invite_url', 'include_images', 'is_active'])]
#[Hidden(['external_id'])]
class WhatsAppDestination extends Model
{
    use SoftDeletes, TracksAuthors;

    protected function casts(): array
    {
        return [
            'scope' => DestinationScope::class,
            'provider' => WhatsAppProviderKey::class,
            'destination_type' => DestinationType::class,
            'external_id' => 'encrypted',
            'include_images' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
