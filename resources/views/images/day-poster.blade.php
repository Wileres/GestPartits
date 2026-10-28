@php
    /** @var \App\Domain\Imaging\PosterPage $page */
    /** @var \App\Domain\Imaging\Branding $branding */
    $format = $page->poster->format;
    $m = \App\Domain\Imaging\RowMetrics::day($page->rowHeight);
    $full = $m->density === \App\Domain\Imaging\RowMetrics::FULL;
    $tight = $m->density === \App\Domain\Imaging\RowMetrics::TIGHT;
    $gap = \App\Domain\Imaging\LayoutPlanner::ROW_GAP;
@endphp
<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="utf-8">
@include('images.partials.styles')
<style>
    main { position: relative; padding: 0 56px; display: flex; flex-direction: column; gap: {{ $gap }}px; }
    .row { height: {{ $m->row }}px; background: #fff; border-radius: {{ $m->radius }}px; display: flex; align-items: center; padding: 0 {{ $tight ? 24 : 32 }}px; gap: {{ $tight ? 18 : 28 }}px; box-shadow: 0 10px 30px rgba(0,0,0,0.18); }
    .time { width: {{ $m->timeWidth }}px; flex-shrink: 0; text-align: center; }
    .time strong { display: block; font-size: {{ $m->time }}px; font-weight: 800; color: {{ $branding->primaryColor }}; font-variant-numeric: tabular-nums; line-height: 1; }
    .time span { display: block; font-size: {{ $m->call }}px; font-weight: 600; color: #52627a; margin-top: 8px; }
    .divider { width: 4px; align-self: stretch; margin: {{ $tight ? 12 : 22 }}px 0; background: #e8f0fb; border-radius: 4px; }
    .match { flex: 1; min-width: 0; {{ $tight ? 'display: flex; align-items: baseline; gap: 14px; white-space: nowrap;' : '' }} }
    .team { font-size: {{ $m->team }}px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; {{ $tight ? 'flex-shrink: 0; max-width: 55%;' : '' }} }
    .opponent { font-size: {{ $m->opponent }}px; font-weight: 500; color: #33445e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; {{ $tight ? 'min-width: 0;' : 'margin-top: 4px;' }} }
    .place { font-size: {{ $m->place }}px; font-weight: 500; color: #52627a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 6px; }
    .badge { flex-shrink: 0; width: {{ $m->badgeWidth }}px; text-align: center; font-size: {{ $m->badgeFont }}px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
    .badge svg { width: {{ $m->icon }}px; height: {{ $m->icon }}px; display: block; margin: 0 auto 6px; }
    .home { color: {{ $branding->primaryColor }}; }
    .away { color: #52627a; }
    .group { height: {{ \App\Domain\Imaging\LayoutPlanner::HEADING }}px; display: flex; align-items: center; gap: 18px; padding: 0 12px; color: #fff; font-size: 26px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }
    .group::after { content: ''; flex: 1; height: 2px; background: rgba(255,255,255,0.4); }
</style>
</head>
<body>
<div class="stripes"></div>
@include('images.partials.header', ['title' => $page->poster->title, 'dateLabel' => $page->poster->dateLabel, 'subtitle' => $page->poster->subtitle])
<main>
    @php $previous = null; @endphp
    @foreach ($page->entries as $entry)
        @php $heading = \App\Domain\Imaging\LayoutPlanner::headingLabel($entry, $previous, $page->poster->grouped); $previous = $entry; @endphp
        @if ($heading !== null)
            <h2 class="group">{{ $heading }}</h2>
        @endif
        <div class="row">
            <div class="time">
                <strong>{{ $entry->kickoff }}</strong>
            </div>
            <div class="divider"></div>
            <div class="match">
                <p class="team">{{ $entry->team }}</p>
                <p class="opponent">vs {{ $entry->opponent }}</p>
                @if ($full && $entry->place !== null)
                    <p class="place">{{ $entry->place }}</p>
                @endif
            </div>
            <div class="badge {{ $entry->home ? 'home' : 'away' }}">
                @if ($full)
                    @if ($entry->home)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6v6"/><path d="M15 6v6"/><path d="M2 12h19.6"/><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"/><circle cx="7" cy="18" r="2"/><path d="M9 18h5"/><circle cx="16" cy="18" r="2"/></svg>
                    @endif
                @endif
                {{ $entry->home ? \App\Domain\Imaging\LayoutPlanner::HOME_LABEL : \App\Domain\Imaging\LayoutPlanner::AWAY_LABEL }}
            </div>
        </div>
    @endforeach
</main>
@include('images.partials.footer', ['pageNumber' => $page->page, 'totalPages' => $page->totalPages])
</body>
</html>
