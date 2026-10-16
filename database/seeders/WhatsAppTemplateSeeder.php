<?php

namespace Database\Seeders;

use App\Enums\TemplateAppliesTo;
use App\Enums\TemplateKind;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Seeder;

/**
 * Plantilles de WhatsApp en català (editables des de Configuració).
 */
class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'partit_casa',
                'name' => 'Partit a casa',
                'kind' => TemplateKind::Match,
                'applies_to' => TemplateAppliesTo::Home,
                'body' => implode("\n", [
                    '⚽ Partit d’aquest cap de setmana',
                    '',
                    '🏠 {{equip}} - {{rival}}',
                    '📅 {{data}}',
                    '🕐 Convocatòria: {{hora_convocatoria}}',
                    '🕒 Partit: {{hora_partit}}',
                    '📍 {{installacio}}',
                    '',
                    'Som-hi, equip! 🔵⚪',
                ]),
            ],
            [
                'code' => 'partit_fora',
                'name' => 'Partit a fora',
                'kind' => TemplateKind::Match,
                'applies_to' => TemplateAppliesTo::Away,
                'body' => implode("\n", [
                    '⚽ Partit d’aquest cap de setmana',
                    '',
                    '🚌 {{rival}} - {{equip}}',
                    '📅 {{data}}',
                    '🕐 Convocatòria: {{hora_convocatoria}}',
                    '🕒 Partit: {{hora_partit}}',
                    '📍 {{installacio}}',
                    '🗺️ {{adreca}}',
                    '',
                    'Som-hi, equip! 🔵⚪',
                ]),
            ],
            [
                'code' => 'resum_jornada',
                'name' => 'Resum de la jornada (canal)',
                'kind' => TemplateKind::WeekendSummary,
                'applies_to' => TemplateAppliesTo::Any,
                'body' => implode("\n", [
                    '⚽ Partits de la jornada · {{jornada}}',
                    '',
                    '📅 DISSABTE',
                    '{{partits_dissabte}}',
                    '',
                    '📅 DIUMENGE',
                    '{{partits_diumenge}}',
                    '',
                    'Som-hi, Joventut! 🔵⚪',
                ]),
                'line_body' => '{{icona}} {{hora_partit}} · {{equip}} - {{rival}}',
            ],
        ];

        foreach ($templates as $template) {
            WhatsAppTemplate::query()->firstOrCreate(['code' => $template['code']], [...$template, 'is_active' => true]);
        }
    }
}
