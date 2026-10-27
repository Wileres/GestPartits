<?php

namespace App\Http\Requests\Communications;

use Illuminate\Foundation\Http\FormRequest;

class MailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-settings');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'in:none,tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            // En blanc = es conserva l'actual.
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function attributes(): array
    {
        return ['host' => 'servidor', 'port' => 'port', 'encryption' => 'xifratge', 'username' => 'usuari', 'password' => 'contrasenya', 'from_address' => 'adreça del remitent', 'from_name' => 'nom del remitent'];
    }
}
