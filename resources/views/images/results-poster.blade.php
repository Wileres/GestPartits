@php
    /** @var \App\Domain\Imaging\ResultsPage $page */
    /** @var \App\Domain\Imaging\Branding $branding */
    $format = $page->poster->format;
    $m = \App\Domain\Imaging\RowMetrics::result($page->rowHeight);
    $gap = \App\Domain\Imaging\LayoutPlanner::RESULT_GAP;
@endphp
<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="utf-8">
@include('images.partials.styles')
<style>
    main { position: relative; padding: 0 56px; display: flex; flex-direction: column; gap: {{ $gap }}px; }
    .result { height: {{ $m->row }}px; background: #fff; border-radius: {{ $m->radius }}px; display: flex; align-items: center; padding: 0 {{ $m->row < 80 ? 24 : 32 }}px; gap: 18px; box-shadow: 0 8px 24px rgba(0,0,0,0.16); }
    .result .team, .result .opponent { flex: 1; min-width: 0; font-size: {{ $m->team }}px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .result .team { font-weight: 500; color: #33445e; text-align: left; }
    .result .opponent { font-weight: 500; color: #33445e; text-align: right; }
    .result .club { font-weight: 800; color: #0b1b33; }
    .score { flex-shrink: 0; min-width: {{ $m->timeWidth }}px; padding: {{ max(0, (int) round($m->row * 0.04)) }}px 16px; border-radius: 999px; background: {{ $branding->primaryColor }}; color: #fff; text-align: center; font-size: {{ $m->time }}px; font-weight: 900; font-variant-numeric: tabular-nums; line-height: 1.2; }
    .venue { flex-shrink: 0; width: {{ $m->badgeWidth }}px; text-align: center; font-size: {{ $m->badgeFont }}px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
    .venue.home { color: {{ $branding->primaryColor }}; }
    .venue.away { color: #52627a; }
</style>
</head>
<body>
<div class="stripes"></div>
@include('images.partials.header', ['title' => $page->poster->title, 'dateLabel' => $page->poster->dateLabel, 'subtitle' => $page->poster->subtitle])
<main>
    @foreach ($page->entries as $entry)
        <div class="result">
            <span class="team {{ $entry->clubOnLeft() ? 'club' : '' }}">{{ $entry->leftName() }}</span>
            <span class="score">{{ $entry->leftScore() }} - {{ $entry->rightScore() }}</span>
            <span class="opponent {{ $entry->clubOnLeft() ? '' : 'club' }}">{{ $entry->rightName() }}</span>
        </div>
    @endforeach
</main>
@include('images.partials.footer', ['pageNumber' => $page->page, 'totalPages' => $page->totalPages])
</body>
</html>
