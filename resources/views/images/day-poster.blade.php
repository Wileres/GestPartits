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
<style>
    @font-face { font-family: 'Inter'; font-weight: 100 900; src: url(data:font/woff2;base64,{{ $fonts['latin'] }}) format('woff2'); unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
    @font-face { font-family: 'Inter'; font-weight: 100 900; src: url(data:font/woff2;base64,{{ $fonts['latin_ext'] }}) format('woff2'); unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { width: 1080px; height: 1920px; overflow: hidden; }
    body {
        font-family: 'Inter', 'DejaVu Sans', sans-serif;
        color: #0f1f38;
        background: linear-gradient(170deg, {{ $branding->primaryColor }} 0%, {{ $branding->primaryDarkColor }} 100%);
        -webkit-font-smoothing: antialiased;
        position: relative;
    }
    .stripes { position: absolute; inset: 0; background: repeating-linear-gradient(115deg, rgba(255,255,255,0.05) 0 60px, transparent 60px 120px); }
    header { position: relative; height: {{ \App\Domain\Imaging\LayoutPlanner::HEADER }}px; padding: 70px 72px 0; color: #fff; display: flex; gap: 36px; align-items: flex-start; }
    .logo { width: 150px; height: 170px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
    .logo img { max-width: 150px; max-height: 170px; }
    .club { font-size: 30px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; opacity: 0.9; }
    h1 { font-size: 104px; font-weight: 900; line-height: 1; margin-top: 10px; text-transform: uppercase; letter-spacing: -0.01em; }
    .date { font-size: 48px; font-weight: 700; margin-top: 12px; }
    .subtitle { font-size: 30px; font-weight: 500; margin-top: 10px; opacity: 0.85; }
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
    footer { position: absolute; left: 0; right: 0; bottom: 0; height: {{ \App\Domain\Imaging\LayoutPlanner::FOOTER }}px; display: flex; align-items: center; justify-content: space-between; padding: 0 72px; color: #fff; font-size: 34px; font-weight: 700; }
    footer .page { font-size: 28px; font-weight: 600; opacity: 0.85; }
</style>
</head>
<body>
<div class="stripes"></div>
<header>
    <div class="logo">
        @if ($logo !== null)
            <img src="data:image/png;base64,{{ $logo }}" alt="">
        @else
            <svg viewBox="0 0 48 56" width="150" height="170" aria-hidden="true"><path d="M24 2 4 9v17c0 13.5 8.6 23.7 20 28 11.4-4.3 20-14.5 20-28V9L24 2Z" fill="#ffffff"/><path d="M24 7 9 12v14c0 10.6 6.5 18.8 15 22.6V7Z" fill="{{ $branding->primaryColor }}"/><text x="24" y="33" text-anchor="middle" font-family="Inter, sans-serif" font-size="11" font-weight="900" fill="{{ $branding->primaryDarkColor }}" stroke="#ffffff" stroke-width="0.6" paint-order="stroke">SPM</text></svg>
        @endif
    </div>
    <div>
        <p class="club">{{ $branding->clubName }}</p>
        <h1>{{ $page->poster->title }}</h1>
        <p class="date">{{ $page->poster->dateLabel }}</p>
        @if ($page->poster->subtitle !== '')
            <p class="subtitle">{{ $page->poster->subtitle }}</p>
        @endif
    </div>
</header>
<main>
    @foreach ($page->entries as $entry)
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
<footer>
    <span>{{ \App\Domain\Imaging\LayoutPlanner::FOOTER_LEFT }}</span>
    @if ($page->totalPages > 1)
        <span class="page">{{ $page->page }}/{{ $page->totalPages }}</span>
    @endif
    <span>{{ \App\Domain\Imaging\LayoutPlanner::FOOTER_RIGHT }}</span>
</footer>
</body>
</html>
