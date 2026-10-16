<?php

namespace Tests\Feature\Scheduling;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class AvailabilitySlotApiTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
    }

    public function test_lists_weekly_slots(): void
    {
        $this->actingAs($this->manager(), 'web')->spa()->getJson('/api/availability-slots')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.label', 'Dissabte matí')
            ->assertJsonPath('data.0.opens_at', '09:00')
            ->assertJsonPath('data.0.last_kickoff_at', '13:00');
    }

    public function test_admin_can_add_a_date_exception_and_deactivate_a_slot(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->spa()->postJson('/api/availability-slots', [
            'specific_date' => '2026-12-26', 'label' => 'Sant Esteve', 'opens_at' => '10:00', 'last_kickoff_at' => '12:00',
            'default_fill_order' => 1, 'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.weekday', null)->assertJsonPath('data.specific_date', '2026-12-26');

        $id = $this->spa()->getJson('/api/availability-slots')->json('data.1.id');
        $this->spa()->putJson("/api/availability-slots/{$id}", [
            'weekday' => 6, 'label' => 'Dissabte tarda', 'opens_at' => '17:00', 'last_kickoff_at' => '19:00', 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_slot_needs_weekday_or_date_but_not_both(): void
    {
        $this->actingAs($this->admin(), 'web');
        $base = ['label' => 'X', 'opens_at' => '10:00', 'last_kickoff_at' => '12:00', 'is_active' => true];

        $this->spa()->postJson('/api/availability-slots', $base)
            ->assertUnprocessable()->assertJsonPath('errors.weekday.0', 'Indica un dia de la setmana o una data concreta.');

        $this->spa()->postJson('/api/availability-slots', [...$base, 'weekday' => 6, 'specific_date' => '2026-12-26'])
            ->assertUnprocessable()->assertJsonPath('errors.weekday.0', 'Indica un dia de la setmana o una data concreta, però no tots dos.');
    }

    public function test_slot_times_are_on_the_grid_and_ordered(): void
    {
        $this->actingAs($this->admin(), 'web')->spa()->postJson('/api/availability-slots', [
            'weekday' => 5, 'label' => 'Divendres', 'opens_at' => '18:10', 'last_kickoff_at' => '17:00', 'is_active' => true,
        ])->assertUnprocessable()->assertJsonPath('errors.opens_at.0', 'L\'hora ha de ser en punt, i quart, i mitja o menys quart.');

        $this->spa()->postJson('/api/availability-slots', [
            'weekday' => 5, 'label' => 'Divendres', 'opens_at' => '19:00', 'last_kickoff_at' => '18:00', 'is_active' => true,
        ])->assertUnprocessable()->assertJsonPath('errors.last_kickoff_at.0', 'L\'última hora d\'inici no pot ser anterior a la primera.');
    }
}
