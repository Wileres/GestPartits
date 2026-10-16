<?php

namespace Tests\Feature\Scheduling;

use App\Models\Category;
use App\Models\SchedulingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class SchedulingRuleApiTest extends TestCase
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
            'team_id' => null, 'category_id' => null, 'game_format' => null,
            'weekday' => 6, 'preferred_time' => '10:00', 'not_before' => null, 'not_after' => null,
            'preferred_field_area_id' => null, 'priority' => 50, 'strength' => 'recommended',
            'valid_from' => null, 'valid_to' => null, 'is_active' => true, 'notes' => 'Prova',
            ...$overrides,
        ];
    }

    public function test_lists_the_initial_rules(): void
    {
        $this->actingAs($this->manager(), 'web')->spa()->getJson('/api/scheduling-rules')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.scope_label', 'Primer equip')
            ->assertJsonPath('data.0.preferred_time', '17:00');
    }

    public function test_admin_can_create_a_team_rule_with_validity(): void
    {
        $this->actingAs($this->admin(), 'web')->spa()->postJson('/api/scheduling-rules', $this->payload([
            'team_id' => $this->team('infantil-a')->id, 'preferred_field_area_id' => $this->area('full')->id,
            'valid_from' => '2026-10-01', 'valid_to' => '2026-12-31',
        ]))->assertCreated()->assertJsonPath('data.scope_label', 'Infantil A')->assertJsonPath('data.valid_to', '2026-12-31');
    }

    public function test_admin_can_create_a_window_rule_for_a_category(): void
    {
        $category = Category::query()->where('slug', 'prebenjami')->sole();

        $this->actingAs($this->admin(), 'web')->spa()->postJson('/api/scheduling-rules', $this->payload([
            'category_id' => $category->id, 'weekday' => null, 'preferred_time' => null, 'not_before' => '09:30', 'not_after' => '12:00', 'strength' => 'mandatory',
        ]))->assertCreated()->assertJsonPath('data.scope_label', 'Categoria Prebenjamí')->assertJsonPath('data.not_before', '09:30');
    }

    public function test_rule_needs_exactly_one_scope_and_some_content(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->spa()->postJson('/api/scheduling-rules', $this->payload())
            ->assertUnprocessable()->assertJsonPath('errors.team_id.0', 'Indica a quin equip, categoria o modalitat s\'aplica la regla.');

        $this->spa()->postJson('/api/scheduling-rules', $this->payload(['team_id' => $this->team('alevi-a')->id, 'game_format' => 'f7']))
            ->assertUnprocessable()->assertJsonPath('errors.team_id.0', 'Indica només un àmbit: un equip, una categoria o una modalitat.');

        $this->spa()->postJson('/api/scheduling-rules', $this->payload(['game_format' => 'f7', 'preferred_time' => null]))
            ->assertUnprocessable()->assertJsonPath('errors.preferred_time.0', 'Indica una hora preferent o una finestra horària (no abans de / no després de).');
    }

    public function test_preferred_time_needs_a_weekday_and_the_grid(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->spa()->postJson('/api/scheduling-rules', $this->payload(['game_format' => 'f7', 'weekday' => null]))
            ->assertUnprocessable()->assertJsonPath('errors.weekday.0', 'Cal indicar el dia de l\'hora preferent.');

        $this->spa()->postJson('/api/scheduling-rules', $this->payload(['game_format' => 'f7', 'preferred_time' => '10:20']))
            ->assertUnprocessable()->assertJsonPath('errors.preferred_time.0', 'L\'hora ha de ser en punt, i quart, i mitja o menys quart.');
    }

    public function test_f11_rule_cannot_prefer_half_a_field(): void
    {
        $this->actingAs($this->admin(), 'web')->spa()->postJson('/api/scheduling-rules', $this->payload([
            'team_id' => $this->team('primer-equip')->id, 'preferred_field_area_id' => $this->area('camp_1')->id,
        ]))->assertUnprocessable()->assertJsonPath('errors.preferred_field_area_id.0', 'Un partit de futbol 11 necessita el camp complet.');
    }

    public function test_admin_can_deactivate_and_delete_rules(): void
    {
        $rule = SchedulingRule::query()->whereNotNull('team_id')->firstOrFail();
        $this->actingAs($this->admin(), 'web');

        $this->spa()->putJson("/api/scheduling-rules/{$rule->id}", $this->payload(['team_id' => $rule->team_id, 'is_active' => false, 'weekday' => 6, 'preferred_time' => '17:00']))
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->spa()->deleteJson("/api/scheduling-rules/{$rule->id}")->assertNoContent();
        $this->assertSoftDeleted($rule);
    }
}
