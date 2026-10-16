<?php

namespace App\Http\Requests\Communications;

use App\Enums\DestinationScope;
use App\Enums\DestinationType;
use App\Enums\WhatsAppProviderKey;
use App\Infrastructure\WhatsApp\WhatsAppProviderRegistry;
use App\Models\WhatsAppDestination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WhatsAppDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $destination = $this->route('whatsapp_destination');

        return $destination instanceof WhatsAppDestination ? $this->user()->can('update', $destination) : $this->user()->can('create', WhatsAppDestination::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::enum(DestinationScope::class)],
            'team_id' => ['nullable', 'required_if:scope,team', 'prohibited_if:scope,club', 'integer', Rule::exists('teams', 'id')->whereNull('deleted_at')],
            'label' => ['required', 'string', 'max:128'],
            'provider' => ['required', Rule::enum(WhatsAppProviderKey::class)],
            'destination_type' => ['required', Rule::enum(DestinationType::class)],
            // Buit en editar = es manté l'identificador actual (no es retorna mai a la interfície).
            'external_id' => ['nullable', 'string', 'max:255'],
            'invite_url' => ['nullable', 'url:https', 'max:2048'],
            'include_images' => ['sometimes', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('destination_type') === DestinationType::Channel->value && $this->input('scope') !== DestinationScope::Club->value) {
                    $validator->errors()->add('destination_type', __('whatsapp.channel_club_only'));
                }

                $registry = app(WhatsAppProviderRegistry::class);
                $key = (string) $this->input('provider');

                if (! $registry->isAvailable($key)) {
                    $validator->errors()->add('provider', __('whatsapp.provider_unavailable'));

                    return;
                }

                $provider = $registry->for($key);
                if (! $provider->capabilities()->supports((string) $this->input('destination_type'))) {
                    $validator->errors()->add('destination_type', __('whatsapp.provider_type', ['provider' => WhatsAppProviderKey::from($key)->label()]));

                    return;
                }

                if ($provider->capabilities()->manual) {
                    return;
                }

                $externalId = $this->input('external_id') ?? $this->route('whatsapp_destination')?->external_id;
                if ($externalId === null || $externalId === '') {
                    $validator->errors()->add('external_id', __('whatsapp.external_id_required'));

                    return;
                }

                $error = $provider->validateDestination((string) $this->input('destination_type'), $externalId);
                if ($error !== null) {
                    $validator->errors()->add('external_id', $error);
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['scope' => 'àmbit', 'team_id' => 'equip', 'label' => 'nom', 'provider' => 'proveïdor', 'destination_type' => 'tipus de destí', 'external_id' => 'identificador', 'invite_url' => 'enllaç', 'is_active' => 'actiu'];
    }
}
