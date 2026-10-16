<?php

namespace App\Http\Resources;

use App\Models\WhatsAppDestination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * L'identificador (telèfon o id del grup) mai no es retorna sencer: només els últims caràcters.
 *
 * @mixin WhatsAppDestination
 */
class WhatsAppDestinationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $id = $this->external_id;

        return [
            'id' => $this->id,
            'scope' => $this->scope->value,
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', fn () => $this->team?->name),
            'label' => $this->label,
            'provider' => $this->provider->value,
            'provider_label' => $this->provider->label(),
            'destination_type' => $this->destination_type->value,
            'destination_type_label' => $this->destination_type->label(),
            'external_id_hint' => $id === null || $id === '' ? null : '…'.mb_substr($id, -4),
            'invite_url' => $this->invite_url,
            'include_images' => $this->include_images,
            'is_active' => $this->is_active,
        ];
    }
}
