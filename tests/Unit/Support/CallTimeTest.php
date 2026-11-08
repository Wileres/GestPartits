<?php

namespace Tests\Unit\Support;

use App\Support\CallTime;
use PHPUnit\Framework\TestCase;

class CallTimeTest extends TestCase
{
    public function test_default_is_one_hour_before_except_in_football_7(): void
    {
        $this->assertSame('10:15:00', CallTime::default('11:15'));
        $this->assertSame('10:15:00', CallTime::default('11:15', 'f11', 'home'));
        $this->assertSame('10:15:00', CallTime::default('11:15', 'f11', 'away'));
        $this->assertNull(CallTime::default(null, 'f7', 'home'));
    }

    public function test_football_7_is_45_minutes_before_at_home_and_one_hour_45_away(): void
    {
        $this->assertSame('10:30:00', CallTime::default('11:15', 'f7', 'home'));
        $this->assertSame('09:30:00', CallTime::default('11:15', 'f7', 'away'));
        $this->assertSame('00:00:00', CallTime::default('01:00', 'f7', 'away'));
    }

    public function test_the_default_follows_changes_of_time_and_of_home_or_away_but_a_custom_call_is_kept(): void
    {
        // Segueix el canvi d'hora.
        $this->assertSame('11:30:00', CallTime::follow('10:30:00', '11:15', '12:15', 'f7', 'home'));
        // Segueix el canvi de casa a fora (mateixa hora).
        $this->assertSame('09:30:00', CallTime::follow('10:30:00', '11:15', '11:15', 'f7', 'away', 'f7', 'home'));
        // Una convocatòria pròpia es respecta.
        $this->assertSame('10:00:00', CallTime::follow('10:00:00', '11:15', '12:15', 'f7', 'home'));
        // Sense convocatòria, es proposa la per defecte.
        $this->assertSame('09:30:00', CallTime::follow(null, null, '11:15', 'f7', 'away'));
    }
}
