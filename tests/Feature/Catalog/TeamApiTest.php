<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'category_id' => Category::query()->where('slug', 'alevi')->value('id'),
            'name' => 'Aleví B',
            'game_format' => null,
            'color' => '#0B4EA2',
            'match_duration_min' => null,
            'post_min' => null,
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_teams_are_listed_in_order_with_effective_values_and_rules(): void
    {
        $response = $this->actingAs($this->manager(), 'web')->spa()->getJson('/api/teams')->assertOk();

        $response->assertJsonPath('data.0.name', 'Escoleta A')
            ->assertJsonPath('data.9.name', 'Primer equip')
            ->assertJsonPath('data.9.effective.game_format', 'f11')
            ->assertJsonPath('data.9.scheduling_rules.0.preferred_time', '17:00');
    }

    public function test_manager_can_create_a_team_that_inherits_category_values(): void
    {
        $this->actingAs($this->manager(), 'web')->spa()->postJson('/api/teams', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'alevi-b')
            ->assertJsonPath('data.game_format', null)
            ->assertJsonPath('data.effective.game_format', 'f7')
            ->assertJsonPath('data.effective.match_duration_min', 60);
    }

    public function test_team_can_override_duration_and_format(): void
    {
        $this->actingAs($this->manager(), 'web')->spa()->postJson('/api/teams', $this->payload(['match_duration_min' => 70, 'game_format' => 'f11']))
            ->assertCreated()
            ->assertJsonPath('data.effective.match_duration_min', 70)
            ->assertJsonPath('data.effective.game_format', 'f11');
    }

    public function test_manager_can_deactivate_and_rename_a_team(): void
    {
        $team = $this->team('benjami-c');

        $this->actingAs($this->manager(), 'web')->spa()->putJson("/api/teams/{$team->id}", $this->payload([
            'category_id' => $team->category_id,
            'name' => 'Benjamí C (nou)',
            'is_active' => false,
        ]))->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.slug', 'benjami-c-nou');
    }

    public function test_manager_can_reorder_teams(): void
    {
        $primer = $this->team('primer-equip');
        $escoletaA = $this->team('escoleta-a');

        $this->actingAs($this->manager(), 'web')->spa()->putJson('/api/teams/order', ['team_ids' => [$primer->id, $escoletaA->id]])
            ->assertNoContent();

        $this->assertSame([$primer->id, $escoletaA->id], Team::query()->orderBy('sort_order')->limit(2)->pluck('id')->all());
        $this->assertSame(10, Team::query()->count());
        $this->assertSame(10, Team::query()->distinct()->count('sort_order'));
    }

    public function test_team_name_must_be_unique_and_color_valid(): void
    {
        $this->actingAs($this->manager(), 'web')->spa()->postJson('/api/teams', $this->payload(['name' => 'Aleví A', 'color' => 'blau']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Aquest nom ja existeix.')
            ->assertJsonPath('errors.color.0', 'El color ha de tenir el format #RRGGBB.');
    }

    public function test_deleted_team_is_soft_deleted(): void
    {
        $team = $this->team('escoleta-b');

        $this->actingAs($this->manager(), 'web')->spa()->deleteJson("/api/teams/{$team->id}")->assertNoContent();

        $this->assertSoftDeleted($team);
        $this->assertDatabaseHas('audit_logs', ['event' => 'team.deleted', 'auditable_id' => $team->id]);
    }
}
