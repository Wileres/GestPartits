<?php

namespace Tests\Unit\Support;

use App\Support\TeamName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TeamNameTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function names(): array
    {
        return [
            'majúscula a cada paraula' => ['santa eugenia', 'Santa Eugenia'],
            'de en minúscula' => ['Joventut DE Olot', 'Joventut de Olot'],
            'del en minúscula' => ['UE DEL Pla', 'UE del Pla'],
            'fue en majúscula' => ['fue girona', 'FUE Girona'],
            'lletra solta' => ['Prebenjamí s8 b', 'Prebenjamí S8 B'],
            'lletra solta al final' => ['olot a', 'Olot A'],
            'espais repetits' => ['  sant   pere ', 'Sant Pere'],
            'no toca la resta' => ['CF Olot', 'CF Olot'],
            'accents' => ['école del barça', 'École del Barça'],
        ];
    }

    #[DataProvider('names')]
    public function test_names_are_normalized(string $input, string $expected): void
    {
        $this->assertSame($expected, TeamName::normalize($input));
    }

    public function test_null_stays_null(): void
    {
        $this->assertNull(TeamName::normalize(null));
    }
}
