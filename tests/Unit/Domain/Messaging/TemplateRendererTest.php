<?php

namespace Tests\Unit\Domain\Messaging;

use App\Domain\Messaging\TemplateRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    private const TEMPLATE = "⚽ Partit d’aquest cap de setmana\n\n🏠 {{equip}} - {{rival}}\n📅 {{data}}\n🕐 Convocatòria: {{hora_convocatoria}}\n🕒 Partit: {{hora_partit}}\n📍 {{installacio}}\n\nSom-hi, equip! 🔵⚪";

    public function test_replaces_the_variables(): void
    {
        $text = (new TemplateRenderer)->render(self::TEMPLATE, [
            'equip' => 'Aleví A', 'rival' => 'CF Olot', 'data' => 'dissabte 17/10/2026', 'hora_convocatoria' => '10:30',
            'hora_partit' => '11:15', 'installacio' => 'Camp del Joventut Sant Pere Màrtir',
        ], TemplateRenderer::MATCH_VARIABLES);

        $this->assertSame("⚽ Partit d’aquest cap de setmana\n\n🏠 Aleví A - CF Olot\n📅 dissabte 17/10/2026\n🕐 Convocatòria: 10:30\n🕒 Partit: 11:15\n📍 Camp del Joventut Sant Pere Màrtir\n\nSom-hi, equip! 🔵⚪", $text);
    }

    public function test_lines_with_empty_variables_are_removed(): void
    {
        $text = (new TemplateRenderer)->render(self::TEMPLATE, ['equip' => 'Aleví A', 'rival' => 'CF Olot', 'data' => 'dissabte', 'hora_partit' => '11:15', 'installacio' => 'Camp'], TemplateRenderer::MATCH_VARIABLES);

        $this->assertStringNotContainsString('Convocatòria', $text);
        $this->assertStringContainsString('🕒 Partit: 11:15', $text);
    }

    public function test_values_are_not_evaluated_as_templates_or_code(): void
    {
        $text = (new TemplateRenderer)->render('{{equip}}', ['equip' => '{{rival}} <script>alert(1)</script>', 'rival' => 'X'], TemplateRenderer::MATCH_VARIABLES);

        $this->assertSame('{{rival}} <script>alert(1)</script>', $text);
    }

    public function test_unknown_variables_are_detected_and_rejected(): void
    {
        $renderer = new TemplateRenderer;

        $this->assertSame(['contrasenya'], $renderer->unknownVariables('Hola {{equip}} {{contrasenya}}', TemplateRenderer::MATCH_VARIABLES));
        $this->expectException(InvalidArgumentException::class);
        $renderer->render('{{contrasenya}}', [], TemplateRenderer::MATCH_VARIABLES);
    }
}
