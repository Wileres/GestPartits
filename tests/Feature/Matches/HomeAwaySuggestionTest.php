<?php

namespace Tests\Feature\Matches;

use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\Matchday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsClub;
use Tests\TestCase;

class HomeAwaySuggestionTest extends TestCase
{
    use RefreshDatabase, SeedsClub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClub();
    }

    private function played(Matchday $matchday, string $team, HomeAway $homeAway, MatchStatus $status = MatchStatus::Confirmed): void
    {
        GameMatch::factory()->create([
            'matchday_id' => $matchday->id,
            'team_id' => $this->team($team)->id,
            'home_away' => $homeAway,
            'status' => $status,
        ]);
    }

    private function suggestions(Matchday $matchday): array
    {
        return $this->actingAs($this->manager(), 'web')->spa()
            ->getJson("/api/matchdays/{$matchday->id}/home-away-suggestions")
            ->assertOk()
            ->json('data');
    }

    public function test_suggests_the_opposite_of_the_last_match(): void
    {
        $previous = $this->weekend('2026-10-10');
        $this->played($previous, 'alevi-a', HomeAway::Home);
        $this->played($previous, 'cadet-a', HomeAway::Away);

        $suggestions = $this->suggestions($this->weekend('2026-10-17'));

        $this->assertSame('away', $suggestions[$this->team('alevi-a')->id]['suggestion']);
        $this->assertSame('home', $suggestions[$this->team('cadet-a')->id]['suggestion']);
        $this->assertNull($suggestions[$this->team('juvenil-a')->id]['suggestion']);
    }

    public function test_ignores_rest_and_postponed_weekends(): void
    {
        $this->played($this->weekend('2026-10-03'), 'alevi-a', HomeAway::Home);
        $this->played($this->weekend('2026-10-10'), 'alevi-a', HomeAway::Away, MatchStatus::Postponed);

        $suggestions = $this->suggestions($this->weekend('2026-10-17'));

        $this->assertSame('away', $suggestions[$this->team('alevi-a')->id]['suggestion']);
    }

    public function test_does_not_assume_strict_alternation(): void
    {
        // Dos partits seguits a casa: la suggeriment es basa només en l'últim.
        $this->played($this->weekend('2026-10-03'), 'alevi-a', HomeAway::Home);
        $this->played($this->weekend('2026-10-10'), 'alevi-a', HomeAway::Home);

        $this->assertSame('away', $this->suggestions($this->weekend('2026-10-17'))[$this->team('alevi-a')->id]['suggestion']);
    }
}
