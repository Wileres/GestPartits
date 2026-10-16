<?php

namespace App\Models;

use App\Enums\TemplateAppliesTo;
use App\Enums\TemplateKind;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(name: 'whatsapp_templates')]
#[Fillable(['code', 'name', 'kind', 'applies_to', 'body', 'line_body', 'provider_template_name', 'provider_template_params', 'is_active'])]
class WhatsAppTemplate extends Model
{
    use TracksAuthors;

    protected function casts(): array
    {
        return [
            'kind' => TemplateKind::class,
            'applies_to' => TemplateAppliesTo::class,
            'provider_template_params' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
