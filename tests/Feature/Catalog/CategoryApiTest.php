<?php

namespace Tests\Feature\Catalog;

use App\Models\ApplicationSetting;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class CategoryApiTest extends TestCase
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
            'name' => 'Veterans',
            'game_format' => 'f11',
            'match_duration_min' => 90,
            'post_min' => 10,
            'min_gap_min' => 5,
            'sort_order' => 90,
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_everyone_can_list_categories(): void
    {
        $this->actingAs($this->manager(), 'web')->spa()->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('data.0.name', 'Escoleta')
            ->assertJsonPath('data.0.game_format', 'f7')
            ->assertJsonPath('data.0.teams_count', 2);
    }

    public function test_admin_can_create_and_update_a_category(): void
    {
        $this->actingAs($this->admin(), 'web');

        $id = $this->spa()->postJson('/api/categories', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'veterans')
            ->json('data.id');

        $this->spa()->putJson("/api/categories/{$id}", $this->payload(['match_duration_min' => 80, 'game_format' => 'f7']))
            ->assertOk()
            ->assertJsonPath('data.match_duration_min', 80)
            ->assertJsonPath('data.game_format', 'f7');

        $this->assertDatabaseHas('audit_logs', ['event' => 'category.updated', 'auditable_id' => $id]);
    }

    public function test_category_game_format_can_be_changed_from_the_configuration(): void
    {
        $this->actingAs($this->admin(), 'web');
        $alevi = Category::query()->where('slug', 'alevi')->sole();

        $this->spa()->putJson("/api/categories/{$alevi->id}", $this->payload(['name' => 'Aleví', 'game_format' => 'f11']))->assertOk();

        $this->assertSame('f11', $alevi->fresh()->game_format->value);
    }

    public function test_validation_is_in_catalan(): void
    {
        $this->actingAs($this->admin(), 'web')->spa()->postJson('/api/categories', $this->payload(['name' => 'Escoleta', 'game_format' => 'f5', 'match_duration_min' => 0]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Aquest nom ja existeix.')
            ->assertJsonPath('errors.game_format.0', 'El valor seleccionat per a modalitat no és vàlid.')
            ->assertJsonPath('errors.match_duration_min.0', 'El camp durada del partit ha de ser com a mínim 1.');
    }

    public function test_category_with_teams_cannot_be_deleted(): void
    {
        $category = Category::query()->where('slug', 'escoleta')->sole();

        $this->actingAs($this->admin(), 'web')->spa()->deleteJson("/api/categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.category.0', 'No es pot esborrar una categoria que té equips. Desactiva-la o mou-ne els equips.');
    }

    public function test_manager_cannot_change_categories(): void
    {
        $category = Category::query()->where('slug', 'escoleta')->sole();
        $this->actingAs($this->manager(), 'web');

        $this->spa()->postJson('/api/categories', $this->payload())->assertForbidden();
        $this->spa()->putJson("/api/categories/{$category->id}", $this->payload(['name' => 'Escoleta']))->assertForbidden();
        $this->spa()->deleteJson("/api/categories/{$category->id}")->assertForbidden();
        $this->spa()->postJson('/api/categories/durations-reviewed')->assertForbidden();
    }

    public function test_admin_can_mark_demo_durations_as_reviewed(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->spa()->getJson('/api/reference-data')->assertJsonPath('data.demo_durations_pending_review', true);

        $this->spa()->postJson('/api/categories/durations-reviewed')->assertNoContent();

        $this->assertFalse(ApplicationSetting::get('demo_durations_pending_review'));
    }
}
