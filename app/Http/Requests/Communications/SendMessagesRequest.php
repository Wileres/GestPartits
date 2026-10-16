<?php

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * L'enviament exigeix una confirmació explícita (confirm = true) després de la previsualització.
 */
class SendMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('schedule'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'message_ids' => ['required', 'array', 'min:1', 'max:200'],
            'message_ids.*' => ['integer', 'distinct'],
            'confirm' => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return ['message_ids' => 'missatges', 'confirm' => 'confirmació'];
    }

    public function messages(): array
    {
        return ['confirm.accepted' => __('whatsapp.confirm_required')];
    }
}
