<?php

namespace App\Http\Requests\Communications;

use App\Domain\Messaging\TemplateRenderer;
use App\Enums\TemplateAppliesTo;
use App\Enums\TemplateKind;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WhatsAppTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('whatsapp_template');

        return $template instanceof WhatsAppTemplate ? $this->user()->can('update', $template) : $this->user()->can('create', WhatsAppTemplate::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'kind' => ['required', Rule::enum(TemplateKind::class)],
            'applies_to' => ['required', Rule::enum(TemplateAppliesTo::class)],
            'body' => ['required', 'string', 'max:4000'],
            'line_body' => ['nullable', 'string', 'max:512'],
            'provider_template_name' => ['nullable', 'string', 'max:128', 'regex:/^[a-z0-9_]+$/'],
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

                $renderer = new TemplateRenderer;
                $summary = $this->input('kind') === TemplateKind::WeekendSummary->value;

                $unknown = $renderer->unknownVariables($this->input('body'), $summary ? TemplateRenderer::SUMMARY_VARIABLES : TemplateRenderer::MATCH_VARIABLES);
                if ($unknown !== []) {
                    $validator->errors()->add('body', __('whatsapp.unknown_variables', ['variables' => implode(', ', $unknown)]));
                }

                if ($summary && $this->filled('line_body')) {
                    $unknown = $renderer->unknownVariables($this->input('line_body'), TemplateRenderer::LINE_VARIABLES);
                    if ($unknown !== []) {
                        $validator->errors()->add('line_body', __('whatsapp.unknown_variables', ['variables' => implode(', ', $unknown)]));
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nom', 'kind' => 'tipus', 'applies_to' => 'partits', 'body' => 'text', 'line_body' => 'línia de cada partit', 'provider_template_name' => 'plantilla aprovada per Meta', 'is_active' => 'activa'];
    }
}
