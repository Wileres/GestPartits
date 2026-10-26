<?php

namespace Tests\Unit\Domain\Imaging;

use App\Domain\Imaging\Branding;
use App\Domain\Imaging\DayPoster;
use App\Domain\Imaging\LayoutPlan;
use App\Domain\Imaging\LayoutPlanner;
use App\Domain\Imaging\PosterEntry;
use App\Domain\Imaging\PosterFormat;
use App\Domain\Imaging\PosterPage;
use App\Domain\Imaging\ResultEntry;
use App\Domain\Imaging\ResultsPoster;
use App\Domain\Imaging\RowMetrics;
use App\Infrastructure\Imaging\FakeRenderer;
use App\Infrastructure\Imaging\GdRenderer;
use PHPUnit\Framework\TestCase;

class LayoutPlannerTest extends TestCase
{
    private function poster(int $count, string $team = 'Aleví A', bool $grouped = false, bool $compress = false, PosterFormat $format = PosterFormat::Story): DayPoster
    {
        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entries[] = new PosterEntry(sprintf('%02d:00', 9 + $i % 12), null, $team, 'CF Olot', $i % 2 === 0, $i % 2 === 0 ? null : 'Figueres');
        }

        return new DayPoster('2026-10-17', 'Dissabte', '17/10/2026', '', $entries, $grouped, $compress, $format);
    }

    private function results(int $count, string $team = 'Aleví A', PosterFormat $format = PosterFormat::Post): ResultsPoster
    {
        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entries[] = new ResultEntry($team, 'CF Olot', $i % 2 === 0, $i % 5, $i % 3);
        }

        return new ResultsPoster('Resultats', '17/10/2026 – 18/10/2026', '', $entries, $format);
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

        // 12 a casa (10 + 2) i 11 a fora (10 + 1): mai es barregen a la mateixa imatge.
        $this->assertCount(4, $plan->pages);
        $this->assertSame([10, 2, 10, 1], array_map(fn (PosterPage $p) => count($p->entries), $plan->pages));
        $this->assertSame([1, 2, 3, 4], array_map(fn (PosterPage $p) => $p->page, $plan->pages));
        $this->assertSame(['code' => LayoutPlan::WARNING_PAGINATED, 'params' => ['count' => 23, 'pages' => 4]], $plan->warnings[0]);
    }

    public function test_when_they_do_not_fit_home_and_away_matches_go_to_different_images(): void
    {
        $entries = [...array_map(fn ($i) => new PosterEntry('10:00', null, 'A', 'B', $i % 2 === 0, null), range(1, 12))];
        $plan = (new LayoutPlanner)->plan(new DayPoster('2026-10-17', 'Dissabte', '17/10/2026', '', $entries));

        $this->assertCount(2, $plan->pages);
        $this->assertSame([true, false], array_map(fn (PosterPage $p) => $p->entries[0]->home, $plan->pages));
        foreach ($plan->pages as $page) {
            $this->assertCount(1, array_unique(array_map(fn ($e) => $e->home, $page->entries)));
        }
    }

    public function test_when_they_fit_in_one_image_home_and_away_stay_together(): void
    {
        $plan = (new LayoutPlanner)->plan($this->poster(8));

        $this->assertCount(1, $plan->pages);
    }

    public function test_results_in_story_format_stretch_to_the_taller_image(): void
    {
        $planner = new LayoutPlanner;

        $this->assertSame(LayoutPlanner::RESULT_MAX_ROW_STORY, $planner->resultRow(3, PosterFormat::Story));
        $this->assertSame(LayoutPlanner::RESULT_MAX_ROW, $planner->resultRow(3, PosterFormat::Post));
        $plan = $planner->planResults($this->results(13, format: PosterFormat::Story));
        $this->assertCount(1, $plan->pages);
        $this->assertSame(PosterFormat::Story, $plan->pages[0]->poster->format);
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

    public function test_results_rows_stretch_to_fill_the_whole_instagram_post(): void
    {
        $planner = new LayoutPlanner;
        $available = PosterFormat::Post->available();

        // De 5 a 13 resultats: les files omplen l'alçada disponible (a menys d'un píxel per fila d'arrodoniment).
        foreach ([5, 8, 10, 13] as $count) {
            $plan = $planner->planResults($this->results($count));
            $this->assertCount(1, $plan->pages, "{$count} resultats");
            $row = $plan->pages[0]->rowHeight;
            $used = $count * $row + ($count - 1) * LayoutPlanner::RESULT_GAP;
            $this->assertLessThanOrEqual($available, $used);
            if ($row < LayoutPlanner::RESULT_MAX_ROW) {
                $this->assertGreaterThan($available - $count - LayoutPlanner::RESULT_GAP, $used, "{$count} resultats: espai sense aprofitar");
            }
        }

        // Amb més partits les files són més baixes: proporcional al nombre de resultats.
        $rows = array_map(fn (int $count) => $planner->planResults($this->results($count))->pages[0]->rowHeight, [8, 10, 13]);
        $this->assertGreaterThan($rows[1], $rows[0]);
        $this->assertGreaterThan($rows[2], $rows[1]);
    }

    public function test_few_results_do_not_grow_into_huge_boxes(): void
    {
        $plan = (new LayoutPlanner)->planResults($this->results(2));

        $this->assertSame(LayoutPlanner::RESULT_MAX_ROW, $plan->pages[0]->rowHeight);
    }

    public function test_too_many_results_are_paginated_in_balanced_pages_and_long_names_reported(): void
    {
        $capacity = LayoutPlanner::resultCapacity(PosterFormat::Post);
        $plan = (new LayoutPlanner)->planResults($this->results($capacity + 3, 'Club Esportiu Joventut Sant Pere Màrtir B'));

        $this->assertCount(2, $plan->pages);
        // Pàgines equilibrades, no una de plena i una amb 3 files.
        $counts = array_map(fn ($page) => count($page->entries), $plan->pages);
        $this->assertLessThanOrEqual(1, abs($counts[0] - $counts[1]));
        $this->assertSame([LayoutPlan::WARNING_PAGINATED, LayoutPlan::WARNING_LONG_NAMES], array_column($plan->warnings, 'code'));
    }

    public function test_the_story_format_still_holds_more_results_in_one_image(): void
    {
        $this->assertGreaterThan(LayoutPlanner::resultCapacity(PosterFormat::Post), LayoutPlanner::resultCapacity(PosterFormat::Story));
    }

    public function test_instagram_post_has_fewer_rows_per_image(): void
    {
        $this->assertSame(['story' => 1920, 'post' => 1350], ['story' => PosterFormat::Story->height(), 'post' => PosterFormat::Post->height()]);
        $this->assertSame(1080, PosterFormat::Post->width());
        $this->assertLessThan(LayoutPlanner::capacity(PosterPage::MODE_NORMAL), LayoutPlanner::capacity(PosterPage::MODE_NORMAL, PosterFormat::Post));

        $plan = (new LayoutPlanner)->plan($this->poster(8, format: PosterFormat::Post));
        $this->assertCount(2, $plan->pages);
        foreach ($plan->pages as $page) {
            $this->assertLessThanOrEqual(PosterFormat::Post->available(), LayoutPlanner::heightOf($page->entries, $page->mode, false));
        }
    }

    public function test_compress_puts_every_match_of_the_day_in_one_image_with_boxes_proportional_to_the_count(): void
    {
        $planner = new LayoutPlanner;
        $rows = [];

        foreach ([3, 5, 8, 12] as $count) {
            $plan = $planner->plan($this->poster($count, compress: true, format: PosterFormat::Post));
            $this->assertCount(1, $plan->pages, "{$count} partits");
            $page = $plan->pages[0];
            $this->assertSame(PosterPage::MODE_FILL, $page->mode);
            $rows[$count] = $page->rowHeight;
            // Hi caben i, si no han arribat al màxim, no sobra espai.
            $height = LayoutPlanner::heightWithRow($page->entries, $page->rowHeight, false);
            $this->assertLessThanOrEqual(PosterFormat::Post->available(), $height);
            if ($page->rowHeight < LayoutPlanner::MAX_FILL_ROW) {
                $this->assertGreaterThan(PosterFormat::Post->available() - $count - LayoutPlanner::ROW_GAP, $height, "{$count} partits");
            }
        }

        // Més partits, caixes més baixes; i mai per sota del mínim.
        $this->assertGreaterThan($rows[5], $rows[3]);
        $this->assertGreaterThan($rows[8], $rows[5]);
        $this->assertGreaterThan($rows[12], $rows[8]);
        $this->assertGreaterThanOrEqual(LayoutPlanner::MIN_FILL_ROW, $rows[12]);
    }

    public function test_compress_only_paginates_when_matches_do_not_fit_at_the_minimum_height(): void
    {
        $planner = new LayoutPlanner;

        // En una publicació, 12 caben a l'alçada mínima; 13 ja no.
        $this->assertCount(1, $planner->plan($this->poster(12, compress: true, format: PosterFormat::Post))->pages);
        $plan = $planner->plan($this->poster(13, compress: true, format: PosterFormat::Post));
        $this->assertCount(2, $plan->pages);
        $this->assertSame(LayoutPlan::WARNING_PAGINATED, $plan->warnings[0]['code']);

        // En una història, el mateix dia sí que hi cap en una sola imatge.
        $this->assertCount(1, $planner->plan($this->poster(13, compress: true))->pages);
    }

    public function test_compress_accounts_for_the_group_headings(): void
    {
        $entries = [...array_map(fn ($i) => new PosterEntry('10:00', null, 'A', 'B', true, null), range(1, 6)), ...array_map(fn ($i) => new PosterEntry('11:00', null, 'C', 'D', false, 'Olot'), range(1, 4))];
        $poster = new DayPoster('2026-10-17', 'Dissabte', '17/10/2026', '', $entries, true, true, PosterFormat::Post);

        $page = (new LayoutPlanner)->plan($poster)->pages[0];

        $this->assertSame(2, LayoutPlanner::headings($entries, true));
        $this->assertLessThanOrEqual(PosterFormat::Post->available(), LayoutPlanner::heightWithRow($entries, $page->rowHeight, true));
        // Amb els títols, les caixes són més baixes que sense agrupar.
        $plain = (new LayoutPlanner)->plan($this->poster(10, compress: true, format: PosterFormat::Post))->pages[0];
        $this->assertLessThan($plain->rowHeight, $page->rowHeight);
    }

    public function test_row_metrics_reduce_the_content_as_the_boxes_shrink(): void
    {
        $this->assertSame(RowMetrics::FULL, RowMetrics::day(150)->density);
        $this->assertSame(RowMetrics::FULL, RowMetrics::day(118)->density);
        $this->assertSame(RowMetrics::MEDIUM, RowMetrics::day(100)->density);
        $this->assertSame(RowMetrics::TIGHT, RowMetrics::day(70)->density);
        // El text creix amb la caixa però no baixa de mides llegibles.
        $this->assertGreaterThan(RowMetrics::day(118)->team, RowMetrics::day(190)->team);
        $this->assertGreaterThanOrEqual(24, RowMetrics::day(LayoutPlanner::MIN_FILL_ROW)->team);
        $this->assertGreaterThanOrEqual(22, RowMetrics::result(LayoutPlanner::RESULT_MIN_ROW)->team);
    }

    public function test_results_are_rendered_as_an_instagram_post_by_every_renderer(): void
    {
        $page = (new LayoutPlanner)->planResults($this->results(12))->pages[0];
        $branding = new Branding('Joventut Sant Pere Màrtir', '#0B4EA2', '#082F66');

        $renderers = [new FakeRenderer];
        if (is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')) {
            $renderers[] = new GdRenderer('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');
        }

        foreach ($renderers as $renderer) {
            $size = getimagesizefromstring($renderer->renderResults($page, $branding));
            $this->assertSame([1080, 1350, 'image/png'], [$size[0], $size[1], $size['mime']], $renderer->name());
        }
    }

    public function test_every_renderer_draws_the_compressed_instagram_post(): void
    {
        $page = (new LayoutPlanner)->plan($this->poster(10, compress: true, format: PosterFormat::Post))->pages[0];
        $branding = new Branding('Joventut Sant Pere Màrtir', '#0B4EA2', '#082F66');

        $renderers = [new FakeRenderer];
        if (is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')) {
            $renderers[] = new GdRenderer('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');
        }

        foreach ($renderers as $renderer) {
            $size = getimagesizefromstring($renderer->render($page, $branding));
            $this->assertSame([1080, 1350], [$size[0], $size[1]], $renderer->name());
        }
    }
}
