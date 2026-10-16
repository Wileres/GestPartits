<?php

namespace Tests\Feature\Security;

use App\Models\AvailabilitySlot;
use App\Models\Category;
use App\Models\FieldBlock;
use App\Models\SchedulingRule;
use App\Support\ClubSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

/**
 * Cas 17: un usuari sense permisos no pot canviar la configuració.
 */
class ConfigurationAuthorizationTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
    }

    public static function protectedEndpoints(): array
    {
        return [
            'crear categoria' => ['POST', '/api/categories'],
            'editar categoria' => ['PUT', '/api/categories/{category}'],
            'esborrar categoria' => ['DELETE', '/api/categories/{category}'],
            'crear franja' => ['POST', '/api/availability-slots'],
            'editar franja' => ['PUT', '/api/availability-slots/{slot}'],
            'esborrar franja' => ['DELETE', '/api/availability-slots/{slot}'],
            'crear bloqueig' => ['POST', '/api/field-blocks'],
            'editar bloqueig' => ['PUT', '/api/field-blocks/{block}'],
            'esborrar bloqueig' => ['DELETE', '/api/field-blocks/{block}'],
            'crear regla' => ['POST', '/api/scheduling-rules'],
            'editar regla' => ['PUT', '/api/scheduling-rules/{rule}'],
            'esborrar regla' => ['DELETE', '/api/scheduling-rules/{rule}'],
            'gestionar usuaris' => ['POST', '/api/users'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_manager_cannot_change_configuration(string $method, string $uri): void
    {
        $block = FieldBlock::query()->create([
            'venue_id' => app(ClubSettings::class)->homeVenue()->id,
            'starts_at' => '2026-10-17 09:00:00', 'ends_at' => '2026-10-17 10:00:00', 'reason' => 'Prova',
        ]);

        $uri = strtr($uri, [
            '{category}' => (string) Category::query()->value('id'),
            '{slot}' => (string) AvailabilitySlot::query()->value('id'),
            '{block}' => (string) $block->id,
            '{rule}' => (string) SchedulingRule::query()->value('id'),
        ]);

        $this->actingAs($this->manager(), 'web')->spa()->json($method, $uri, [])
            ->assertForbidden()
            ->assertJsonPath('message', 'No tens permís per fer aquesta acció.');
    }

    public function test_configuration_is_unchanged_after_forbidden_attempts(): void
    {
        $before = SchedulingRule::query()->count();

        $this->actingAs($this->manager(), 'web')->spa()->postJson('/api/scheduling-rules', [
            'game_format' => 'f7', 'weekday' => 6, 'preferred_time' => '10:00', 'priority' => 1, 'strength' => 'mandatory', 'is_active' => true,
        ])->assertForbidden();

        $this->assertSame($before, SchedulingRule::query()->count());
    }

    public function test_unauthenticated_users_cannot_read_anything(): void
    {
        foreach (['/api/teams', '/api/categories', '/api/seasons', '/api/availability-slots', '/api/field-blocks', '/api/scheduling-rules', '/api/reference-data'] as $uri) {
            $this->spa()->getJson($uri)->assertUnauthorized();
        }
    }
}
