<?php

namespace Tests\Unit\Domain\Imaging;

use App\Domain\Imaging\Branding;
use App\Domain\Imaging\DayPoster;
use App\Domain\Imaging\LayoutPlan;
use App\Domain\Imaging\LayoutPlanner;
use App\Domain\Imaging\PosterEntry;
use App\Domain\Imaging\PosterPage;
use App\Infrastructure\Imaging\FakeRenderer;
use App\Infrastructure\Imaging\GdRenderer;
use PHPUnit\Framework\TestCase;

class LayoutPlannerTest extends TestCase
{
    private function poster(int $count, string $team = 'Aleví A'): DayPoster
    {
        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entries[] = new PosterEntry(sprintf('%02d:00', 9 + $i % 12), null, $team, 'CF Olot', $i % 2 === 0, $i % 2 === 0 ? null : 'Figueres');
        }

        return new DayPoster('2026-10-17', 'Dissabte', '17/10/2026', '', $entries);
    }

    public function test_up_to_eight_matches_use_the_normal_design(): void
    {
        $plan = (new LayoutPlanner)->plan($this->poster(8));

        $this->assertCount(1, $plan->pages);
        $this->assertSame(PosterPage::MODE_NORMAL, $plan->pages[0]->mode);
        $this->assertSame([], $plan->warnings);
    }

    public function test_nine_or_ten_matches_use_the_compact_design_with_a_warning(): void
    {
        $plan = (new LayoutPlanner)->plan($this->poster(10));

        $this->assertCount(1, $plan->pages);
        $this->assertSame(PosterPage::MODE_COMPACT, $plan->pages[0]->mode);
        $this->assertSame(LayoutPlan::WARNING_COMPACT, $plan->warnings[0]['code']);
    }

    public function test_more_matches_are_split_into_pages_without_shrinking_text(): void
    {
        $plan = (new LayoutPlanner)->plan($this->poster(23));

        $this->assertCount(3, $plan->pages);
        $this->assertSame([10, 10, 3], array_map(fn (PosterPage $p) => count($p->entries), $plan->pages));
        $this->assertSame([1, 2, 3], array_map(fn (PosterPage $p) => $p->page, $plan->pages));
        $this->assertSame(['code' => LayoutPlan::WARNING_PAGINATED, 'params' => ['count' => 23, 'pages' => 3]], $plan->warnings[0]);
    }

    public function test_rows_fit_inside_the_image(): void
    {
        foreach ([PosterPage::MODE_NORMAL => LayoutPlanner::NORMAL_ROW, PosterPage::MODE_COMPACT => LayoutPlanner::COMPACT_ROW] as $mode => $row) {
            $used = LayoutPlanner::HEADER + LayoutPlanner::capacity($mode) * ($row + LayoutPlanner::ROW_GAP) + LayoutPlanner::FOOTER;
            $this->assertLessThanOrEqual(LayoutPlanner::HEIGHT + LayoutPlanner::ROW_GAP, $used, $mode);
        }
    }

    public function test_long_names_are_reported(): void
    {
        $plan = (new LayoutPlanner)->plan($this->poster(2, 'Club Esportiu Joventut Sant Pere Màrtir B'));

        $this->assertSame(LayoutPlan::WARNING_LONG_NAMES, $plan->warnings[0]['code']);
    }

    public function test_fake_and_gd_renderers_produce_1080x1920_pngs(): void
    {
        $page = (new LayoutPlanner)->plan($this->poster(3))->pages[0];
        $branding = new Branding('Joventut Sant Pere Màrtir', '#0B4EA2', '#082F66');

        $renderers = [new FakeRenderer];
        if (is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')) {
            $renderers[] = new GdRenderer('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');
        }

        foreach ($renderers as $renderer) {
            $size = getimagesizefromstring($renderer->render($page, $branding));
            $this->assertSame([1080, 1920, 'image/png'], [$size[0], $size[1], $size['mime']], $renderer->name());
        }
    }
}
