@php
    /** @var \App\Domain\Imaging\ResultsPage $page */
    /** @var \App\Domain\Imaging\Branding $branding */
    $mode = $page->mode;
    $row = \App\Domain\Imaging\LayoutPlanner::resultRow($mode);
    $dense = $mode === \App\Domain\Imaging\ResultsPage::MODE_DENSE;
    $compact = $mode === \App\Domain\Imaging\ResultsPage::MODE_COMPACT;
    $name = $dense ? 28 : ($compact ? 32 : 36);
    $score = $dense ? 40 : ($compact ? 46 : 54);
@endphp
<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="utf-8">
@include('images.partials.styles')
<style>
    main { position: relative; padding: 0 56px; display: flex; flex-direction: column; gap: {{ \App\Domain\Imaging\LayoutPlanner::RESULT_GAP }}px; }
    .result { height: {{ $row }}px; background: #fff; border-radius: {{ $dense ? 18 : 26 }}px; display: flex; align-items: center; padding: 0 {{ $dense ? 24 : 32 }}px; gap: 18px; box-shadow: 0 8px 24px rgba(0,0,0,0.16); }
    .result .team, .result .opponent { flex: 1; min-width: 0; font-size: {{ $name }}px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .result .team { font-weight: 800; text-align: left; }
    .result .opponent { font-weight: 500; color: #33445e; text-align: right; }
    .score { flex-shrink: 0; min-width: {{ $dense ? 150 : 190 }}px; padding: {{ $dense ? 2 : 6 }}px 16px; border-radius: 999px; background: {{ $branding->primaryColor }}; color: #fff; text-align: center; font-size: {{ $score }}px; font-weight: 900; font-variant-numeric: tabular-nums; line-height: 1.2; }
    .venue { flex-shrink: 0; width: {{ $dense ? 70 : 90 }}px; text-align: center; font-size: {{ $dense ? 17 : 20 }}px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
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
            <span class="venue {{ $entry->home ? 'home' : 'away' }}">{{ $entry->home ? 'Casa' : 'Fora' }}</span>
            <span class="team">{{ $entry->team }}</span>
            <span class="score">{{ $entry->scoreTeam }} - {{ $entry->scoreOpponent }}</span>
            <span class="opponent">{{ $entry->opponent }}</span>
        </div>
    @endforeach
</main>
@include('images.partials.footer', ['pageNumber' => $page->page, 'totalPages' => $page->totalPages])
</body>
</html>
