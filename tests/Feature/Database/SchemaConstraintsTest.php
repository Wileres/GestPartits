<?php

namespace Tests\Feature\Database;

use App\Models\Venue;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Les restriccions CHECK de la base de dades protegeixen la integritat encara que falli la validació.
 */
class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_game_format_is_restricted(): void
    {
        $this->expectException(QueryException::class);

        DB::table('categories')->insert(['name' => 'X', 'slug' => 'x', 'game_format' => 'f5', 'match_duration_min' => 40]);
    }

    public function test_slot_must_be_weekly_or_for_a_specific_date_but_not_both(): void
    {
        $venue = Venue::query()->create(['name' => 'Camp', 'is_home' => true]);

        $this->expectException(QueryException::class);

        DB::table('availability_slots')->insert([
            'venue_id' => $venue->id, 'weekday' => 6, 'specific_date' => '2026-10-17',
            'label' => 'X', 'opens_at' => '09:00', 'last_kickoff_at' => '13:00',
        ]);
    }

    public function test_slot_last_kickoff_cannot_be_before_opening(): void
    {
        $venue = Venue::query()->create(['name' => 'Camp', 'is_home' => true]);

        $this->expectException(QueryException::class);

        DB::table('availability_slots')->insert([
            'venue_id' => $venue->id, 'weekday' => 6, 'label' => 'X', 'opens_at' => '13:00', 'last_kickoff_at' => '09:00',
        ]);
    }

    public function test_scheduling_rule_needs_a_scope(): void
    {
        $this->expectException(QueryException::class);

        DB::table('scheduling_rules')->insert(['weekday' => 6, 'preferred_time' => '10:00', 'strength' => 'recommended']);
    }

    public function test_club_destination_without_team_is_allowed_but_team_destination_needs_a_team(): void
    {
        DB::table('whatsapp_destinations')->insert([
            'scope' => 'club', 'label' => 'Canal del club', 'provider' => 'manual', 'destination_type' => 'channel',
        ]);
        $this->assertDatabaseCount('whatsapp_destinations', 1);

        $this->expectException(QueryException::class);

        DB::table('whatsapp_destinations')->insert([
            'scope' => 'team', 'label' => 'Grup', 'provider' => 'manual', 'destination_type' => 'group',
        ]);
    }

    public function test_audit_logs_cannot_be_modified(): void
    {
        $log = app(AuditLogger::class)->log('prova');

        $this->expectException(\LogicException::class);

        $log->update(['event' => 'modificat']);
    }
}
