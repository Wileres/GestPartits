<?php

namespace Tests\Feature\Scheduling;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class FieldBlockApiTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
        $this->travelTo('2026-10-15 12:00:00');
    }

    public function test_admin_can_block_the_whole_field_or_one_half(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->spa()->postJson('/api/field-blocks', [
            'field_area_id' => null, 'starts_at' => '2026-10-17T09:00', 'ends_at' => '2026-10-17T11:00', 'reason' => 'Manteniment de la gespa',
        ])->assertCreated()->assertJsonPath('data.field_area', null)->assertJsonPath('data.starts_at', '2026-10-17T09:00');

        $this->spa()->postJson('/api/field-blocks', [
            'field_area_id' => $this->area('camp_2')->id, 'starts_at' => '2026-10-18T09:00', 'ends_at' => '2026-10-18T13:00', 'reason' => 'Torneig',
        ])->assertCreated()->assertJsonPath('data.field_area.code', 'camp_2');

        $this->spa()->getJson('/api/field-blocks')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_block_must_end_after_it_starts(): void
    {
        $this->actingAs($this->admin(), 'web')->spa()->postJson('/api/field-blocks', [
            'starts_at' => '2026-10-17T11:00', 'ends_at' => '2026-10-17T09:00', 'reason' => 'X',
        ])->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);
    }

    public function test_past_blocks_are_not_listed_by_default(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->spa()->postJson('/api/field-blocks', ['starts_at' => '2026-10-01T09:00', 'ends_at' => '2026-10-01T11:00', 'reason' => 'Passat'])->assertCreated();

        $this->spa()->getJson('/api/field-blocks')->assertJsonCount(0, 'data');
        $this->spa()->getJson('/api/field-blocks?from=2026-09-01')->assertJsonCount(1, 'data');
    }
}
