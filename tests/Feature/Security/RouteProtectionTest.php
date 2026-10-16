<?php

namespace Tests\Feature\Security;

use App\Models\FieldBlock;
use App\Models\WhatsAppDestination;
use App\Support\ClubSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

/**
 * Totes les rutes de l'API que modifiquen dades exigeixen sessió; les de configuració, ser administrador.
 */
class RouteProtectionTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    /** Rutes públiques: inici de sessió i recuperació de contrasenya. */
    private const PUBLIC = ['auth.login', 'auth.password.forgot', 'auth.password.reset'];

    /** Rutes que només pot fer servir l'administrador. */
    private const ADMIN_ONLY = [
        'users.index', 'users.store', 'users.update', 'users.destroy',
        'categories.store', 'categories.update', 'categories.destroy', 'categories.durations-reviewed',
        'availability-slots.store', 'availability-slots.update', 'availability-slots.destroy',
        'field-blocks.store', 'field-blocks.update', 'field-blocks.destroy',
        'scheduling-rules.store', 'scheduling-rules.update', 'scheduling-rules.destroy',
        'whatsapp-templates.store', 'whatsapp-templates.update', 'whatsapp-templates.destroy',
        'whatsapp-destinations.store', 'whatsapp-destinations.update', 'whatsapp-destinations.destroy',
        'settings.update', 'settings.logo.upload', 'settings.logo.delete',
        'audit-logs.index',
    ];

    /**
     * @return list<Route>
     */
    private function apiRoutes(): array
    {
        return array_values(array_filter(RouteFacade::getRoutes()->getRoutes(), fn (Route $r) => str_starts_with($r->uri(), 'api/')));
    }

    private function uri(Route $route): string
    {
        return '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
    }

    public function test_every_api_route_except_login_requires_authentication(): void
    {
        $checked = 0;

        foreach ($this->apiRoutes() as $route) {
            if (in_array($route->getName(), self::PUBLIC, true)) {
                continue;
            }

            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $status = $this->spa()->json($method, $this->uri($route))->getStatusCode();

            $this->assertSame(401, $status, "{$method} {$route->uri()} hauria d'exigir sessió");
            $checked++;
        }

        $this->assertGreaterThan(60, $checked);
    }

    public function test_configuration_routes_are_forbidden_to_managers(): void
    {
        $this->seedClub();
        FieldBlock::query()->create(['venue_id' => app(ClubSettings::class)->homeVenue()->id, 'starts_at' => '2026-10-17 09:00:00', 'ends_at' => '2026-10-17 10:00:00', 'reason' => 'Prova']);
        WhatsAppDestination::query()->create(['scope' => 'club', 'label' => 'Canal', 'provider' => 'manual', 'destination_type' => 'channel']);
        $this->actingAs($this->manager(), 'web');
        $names = array_filter(array_map(fn (Route $r) => $r->getName(), $this->apiRoutes()));

        foreach (self::ADMIN_ONLY as $name) {
            $this->assertContains($name, $names, "La ruta {$name} no existeix");
            $route = RouteFacade::getRoutes()->getByName($name);
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();

            // Es fa servir un id que existeix perquè la resposta no sigui un 404.
            $uri = preg_replace('/\{[^}]+\}/', (string) $this->existingId($route->uri()), $route->uri());

            $this->assertSame(403, $this->spa()->json($method, '/'.$uri, [])->getStatusCode(), "{$method} {$route->uri()} hauria d'estar prohibida al gestor");
        }
    }

    private function existingId(string $uri): int
    {
        $table = match (true) {
            str_contains($uri, 'users') => 'users',
            str_contains($uri, 'categories') => 'categories',
            str_contains($uri, 'availability-slots') => 'availability_slots',
            str_contains($uri, 'scheduling-rules') => 'scheduling_rules',
            str_contains($uri, 'whatsapp-templates') => 'whatsapp_templates',
            str_contains($uri, 'whatsapp-destinations') => 'whatsapp_destinations',
            str_contains($uri, 'field-blocks') => 'field_blocks',
            default => null,
        };

        return $table === null ? 1 : (int) \DB::table($table)->value('id');
    }
}
