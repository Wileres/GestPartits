<?php

namespace Tests\Unit\Support;

use App\Support\CatalanDate;
use App\Support\Time;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase
{
    public function test_converts_between_times_and_minutes(): void
    {
        $this->assertSame(555, Time::toMinutes('09:15'));
        $this->assertSame(555, Time::toMinutes('09:15:00'));
        $this->assertSame('17:05', Time::fromMinutes(1025));
        $this->assertSame('12:30', Time::hm('12:30:00'));
        $this->assertNull(Time::hm(null));
    }

    public function test_quarter_hour_grid(): void
    {
        $this->assertTrue(Time::isOnGrid('10:45', 15));
        $this->assertFalse(Time::isOnGrid('10:10', 15));
    }

    public function test_rejects_invalid_times(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Time::toMinutes('9h');
    }

    public function test_catalan_dates_and_matchday_labels(): void
    {
        $this->assertSame('Jornada del 17/10/2026 al 18/10/2026', CatalanDate::matchdayLabel(CarbonImmutable::parse('2026-10-17'), CarbonImmutable::parse('2026-10-18')));
        $this->assertSame('Jornada del 12/10/2026', CatalanDate::matchdayLabel(CarbonImmutable::parse('2026-10-12'), CarbonImmutable::parse('2026-10-12')));
        $this->assertSame('01/11/2026', CatalanDate::date(CarbonImmutable::parse('2026-11-01')));
        $this->assertSame('dissabte', CatalanDate::weekday(CarbonImmutable::parse('2026-10-17')));
    }
}
