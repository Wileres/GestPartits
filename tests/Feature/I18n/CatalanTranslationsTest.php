<?php

namespace Tests\Feature\I18n;

use App\Enums\MatchStatus;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Cas 22 (backend): els textos visibles són en català.
 */
class CatalanTranslationsTest extends TestCase
{
    public function test_application_locale_is_catalan(): void
    {
        $this->assertSame('ca', app()->getLocale());
        $this->assertSame('ca', config('app.fallback_locale'));
    }

    public function test_every_framework_validation_message_has_a_catalan_translation(): void
    {
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $catalan = require lang_path('ca/validation.php');

        $missing = array_diff(
            array_keys(Arr::dot(Arr::except($english, ['custom', 'attributes']))),
            array_keys(Arr::dot(Arr::except($catalan, ['custom', 'attributes']))),
        );

        $this->assertSame([], array_values($missing), 'Falten traduccions de validació: '.implode(', ', $missing));
    }

    public function test_auth_and_password_messages_have_catalan_translations(): void
    {
        foreach (['auth', 'passwords'] as $file) {
            $english = require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php");
            $catalan = require lang_path("ca/{$file}.php");

            $this->assertSame([], array_values(array_diff(array_keys($english), array_keys($catalan))), $file);
        }
    }

    public function test_match_statuses_are_labelled_in_catalan(): void
    {
        $this->assertSame(
            ['Proposat', 'Confirmat', 'Cancel·lat'],
            array_map(fn (MatchStatus $s) => $s->label(), MatchStatus::cases()),
        );
    }

    public function test_api_errors_are_in_catalan(): void
    {
        $this->getJson('/api/no-existeix')->assertNotFound()->assertJsonPath('message', 'No s\'ha trobat el recurs demanat.');
    }

    public function test_generic_http_errors_are_in_catalan(): void
    {
        $this->getJson('/api/auth/login')->assertStatus(405)->assertJsonPath('message', 'Aquesta operació no està permesa.');
    }

    public function test_backend_texts_do_not_contain_common_spanish_words(): void
    {
        $spanish = '/\b(importantes|usuarios?|contraseñas?|guardar|borrar|también|partidos?|equipos?|fecha|campo|semana|después|cuando|está|están|puede|pueden|ningún|configuración)\b/iu';

        foreach (glob(lang_path('ca/*.php')) as $file) {
            $texts = Arr::flatten(require $file);
            foreach ($texts as $text) {
                $this->assertDoesNotMatchRegularExpression($spanish, (string) $text, basename($file).': '.$text);
            }
        }
    }
}
