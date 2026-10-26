@php
    /** @var \App\Domain\Imaging\PosterPage $page */
    /** @var \App\Domain\Imaging\Branding $branding */
    $compact = $page->mode === \App\Domain\Imaging\PosterPage::MODE_COMPACT;
    $row = $compact ? \App\Domain\Imaging\LayoutPlanner::COMPACT_ROW : \App\Domain\Imaging\LayoutPlanner::NORMAL_ROW;
@endphp
<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="utf-8">
@include('images.partials.styles')
<style>
    main { position: relative; padding: 0 56px; display: flex; flex-direction: column; gap: {{ \App\Domain\Imaging\LayoutPlanner::ROW_GAP }}px; }
    .row { height: {{ $row }}px; background: #fff; border-radius: 28px; display: flex; align-items: center; padding: 0 32px; gap: 28px; box-shadow: 0 10px 30px rgba(0,0,0,0.18); }
    .time { width: {{ $compact ? 150 : 180 }}px; flex-shrink: 0; text-align: center; }
    .time strong { display: block; font-size: {{ $compact ? 50 : 62 }}px; font-weight: 800; color: {{ $branding->primaryColor }}; font-variant-numeric: tabular-nums; line-height: 1; }
    .time span { display: block; font-size: {{ $compact ? 22 : 24 }}px; font-weight: 600; color: #52627a; margin-top: 8px; }
    .divider { width: 4px; align-self: stretch; margin: 22px 0; background: #e8f0fb; border-radius: 4px; }
    .match { flex: 1; min-width: 0; }
    .team { font-size: {{ $compact ? 36 : 42 }}px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .opponent { font-size: {{ $compact ? 30 : 34 }}px; font-weight: 500; color: #33445e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 4px; }
    .place { font-size: {{ $compact ? 22 : 25 }}px; font-weight: 500; color: #52627a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 6px; }
    .badge { flex-shrink: 0; width: {{ $compact ? 120 : 132 }}px; text-align: center; font-size: 22px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
    .badge svg { width: {{ $compact ? 50 : 60 }}px; height: {{ $compact ? 50 : 60 }}px; display: block; margin: 0 auto 6px; }
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
    @php $previousHome = null; @endphp
    @foreach ($page->entries as $entry)
        @if ($page->poster->grouped && ($loop->first || $entry->home !== $previousHome))
            <h2 class="group">{{ $entry->home ? 'A casa' : 'A fora' }}</h2>
        @endif
        @php $previousHome = $entry->home; @endphp
        <div class="row">
            <div class="time">
                <strong>{{ $entry->kickoff }}</strong>
                @if ($entry->callTime !== null)
                    <span>Conv. {{ $entry->callTime }}</span>
                @endif
            </div>
            <div class="divider"></div>
            <div class="match">
                <p class="team">{{ $entry->team }}</p>
                <p class="opponent">{{ $entry->home ? 'vs' : '@' }} {{ $entry->opponent }}</p>
                @if ($entry->place !== null)
                    <p class="place">{{ $entry->place }}</p>
                @endif
            </div>
            <div class="badge {{ $entry->home ? 'home' : 'away' }}">
                @if ($entry->home)
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    A casa
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6v6"/><path d="M15 6v6"/><path d="M2 12h19.6"/><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"/><circle cx="7" cy="18" r="2"/><path d="M9 18h5"/><circle cx="16" cy="18" r="2"/></svg>
                    A fora
                @endif
            </div>
        </div>
    @endforeach
</main>
@include('images.partials.footer', ['pageNumber' => $page->page, 'totalPages' => $page->totalPages])
</body>
</html>
