<?php

namespace Tests\Unit\Domain\Imaging;

use App\Domain\Imaging\Branding;
use App\Domain\Imaging\DayPoster;
use App\Domain\Imaging\LayoutPlan;
use App\Domain\Imaging\LayoutPlanner;
use App\Domain\Imaging\PosterEntry;
use App\Domain\Imaging\PosterPage;
use App\Domain\Imaging\ResultEntry;
use App\Domain\Imaging\ResultsPage;
use App\Domain\Imaging\ResultsPoster;
use App\Infrastructure\Imaging\FakeRenderer;
use App\Infrastructure\Imaging\GdRenderer;
use PHPUnit\Framework\TestCase;

class LayoutPlannerTest extends TestCase
{
    private function poster(int $count, string $team = 'Aleví A', bool $grouped = false): DayPoster
    {
        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entries[] = new PosterEntry(sprintf('%02d:00', 9 + $i % 12), null, $team, 'CF Olot', $i % 2 === 0, $i % 2 === 0 ? null : 'Figueres');
        }

        return new DayPoster('2026-10-17', 'Dissabte', '17/10/2026', '', $entries, $grouped);
    }

    private function results(int $count, string $team = 'Aleví A'): ResultsPoster
    {
        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entries[] = new ResultEntry($team, 'CF Olot', $i % 2 === 0, $i % 5, $i % 3);
        }

        return new ResultsPoster('Resultats', '17/10/2026 – 18/10/2026', '', $entries);
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

    public function test_grouping_adds_a_small_heading_per_group_and_uses_the_real_height(): void
    {
        // Sense agrupar, 8 partits caben en el disseny normal; amb els dos títols de grup ja no hi caben.
        $this->assertSame(LayoutPlan::class, (new LayoutPlanner)->plan($this->poster(8))::class);
        $this->assertSame(PosterPage::MODE_NORMAL, (new LayoutPlanner)->plan($this->poster(8))->pages[0]->mode);

        // 4 a casa i 4 a fora, agrupats: 2 títols més (2 × (52 + 12) = 128 px) i no hi ha prou alçada.
        $entries = [...array_map(fn ($i) => new PosterEntry('10:00', null, 'A', 'B', true, null), range(1, 4)), ...array_map(fn ($i) => new PosterEntry('11:00', null, 'C', 'D', false, 'Olot'), range(1, 4))];
        $grouped = new DayPoster('2026-10-17', 'Dissabte', '17/10/2026', '', $entries, true);

        $this->assertSame(8 * (LayoutPlanner::NORMAL_ROW + LayoutPlanner::ROW_GAP) - LayoutPlanner::ROW_GAP + 2 * (LayoutPlanner::HEADING + LayoutPlanner::ROW_GAP), LayoutPlanner::heightOf($entries, PosterPage::MODE_NORMAL, true));
        $plan = (new LayoutPlanner)->plan($grouped);
        $this->assertCount(1, $plan->pages);
        $this->assertSame(PosterPage::MODE_COMPACT, $plan->pages[0]->mode);
        $this->assertSame(LayoutPlan::WARNING_COMPACT, $plan->warnings[0]['code']);
    }

    public function test_grouped_pages_never_exceed_the_available_height(): void
    {
        $plan = (new LayoutPlanner)->plan($this->poster(23, 'Aleví A', true));

        $this->assertGreaterThan(1, count($plan->pages));
        foreach ($plan->pages as $page) {
            $this->assertLessThanOrEqual(LayoutPlanner::available(), LayoutPlanner::heightOf($page->entries, $page->mode, true));
        }
        $this->assertSame(23, array_sum(array_map(fn ($page) => count($page->entries), $plan->pages)));
    }

    public function test_results_fit_in_a_single_image_with_growing_density(): void
    {
        $planner = new LayoutPlanner;

        $this->assertSame(ResultsPage::MODE_NORMAL, $planner->planResults($this->results(8))->pages[0]->mode);
        $this->assertSame(ResultsPage::MODE_COMPACT, $planner->planResults($this->results(LayoutPlanner::resultCapacity(ResultsPage::MODE_NORMAL) + 1))->pages[0]->mode);
        $dense = $planner->planResults($this->results(LayoutPlanner::resultCapacity(ResultsPage::MODE_DENSE)));
        $this->assertCount(1, $dense->pages);
        $this->assertSame(ResultsPage::MODE_DENSE, $dense->pages[0]->mode);
        $this->assertGreaterThanOrEqual(17, LayoutPlanner::resultCapacity(ResultsPage::MODE_DENSE));
    }

    public function test_too_many_results_are_paginated_and_long_names_reported(): void
    {
        $capacity = LayoutPlanner::resultCapacity(ResultsPage::MODE_DENSE);
        $plan = (new LayoutPlanner)->planResults($this->results($capacity + 3, 'Club Esportiu Joventut Sant Pere Màrtir B'));

        $this->assertCount(2, $plan->pages);
        $this->assertSame([LayoutPlan::WARNING_PAGINATED, LayoutPlan::WARNING_LONG_NAMES], array_column($plan->warnings, 'code'));
    }

    public function test_results_are_rendered_at_1080x1920_by_every_renderer(): void
    {
        $page = (new LayoutPlanner)->planResults($this->results(12))->pages[0];
        $branding = new Branding('Joventut Sant Pere Màrtir', '#0B4EA2', '#082F66');

        $renderers = [new FakeRenderer];
        if (is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')) {
            $renderers[] = new GdRenderer('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');
        }

        foreach ($renderers as $renderer) {
            $size = getimagesizefromstring($renderer->renderResults($page, $branding));
            $this->assertSame([1080, 1920, 'image/png'], [$size[0], $size[1], $size['mime']], $renderer->name());
        }
    }
}
