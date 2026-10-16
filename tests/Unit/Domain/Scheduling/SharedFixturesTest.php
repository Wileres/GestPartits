<?php

namespace Tests\Unit\Domain\Scheduling;

use App\Domain\Scheduling\Conflicts\Conflict;
use App\Domain\Scheduling\Conflicts\ConflictDetector;
use App\Domain\Scheduling\Model\ScheduleInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les mateixes fixtures les executa Vitest contra el validador TypeScript del calendari.
 */
class SharedFixturesTest extends TestCase
{
    public static function fixtures(): array
    {
        $cases = [];
        foreach (glob(__DIR__.'/../../../fixtures/scheduling/*.json') as $file) {
            $cases[basename($file, '.json')] = [json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR)];
        }

        return $cases;
    }

    #[DataProvider('fixtures')]
    public function test_conflicts_match_the_fixture(array $fixture): void
    {
        $input = ScheduleInput::fromArray($fixture['input']);

        $conflicts = array_map(fn (Conflict $c) => [
            'code' => $c->code->value,
            'severity' => $c->severity->value,
            'match_ids' => $c->matchIds,
        ], (new ConflictDetector)->detect($input, $fixture['for_confirmation']));

        $this->assertSame($fixture['expected'], $conflicts, $fixture['description']);
    }

    #[DataProvider('fixtures')]
    public function test_input_round_trips_through_arrays(array $fixture): void
    {
        $input = ScheduleInput::fromArray($fixture['input']);

        $this->assertEquals($input, ScheduleInput::fromArray($input->toArray()));
    }
}
