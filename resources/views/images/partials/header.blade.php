<header>
    <div class="logo">
        @if ($logo !== null)
            <img src="data:image/png;base64,{{ $logo }}" alt="">
        @else
            <svg viewBox="0 0 48 56" aria-hidden="true"><path d="M24 2 4 9v17c0 13.5 8.6 23.7 20 28 11.4-4.3 20-14.5 20-28V9L24 2Z" fill="#ffffff"/><path d="M24 7 9 12v14c0 10.6 6.5 18.8 15 22.6V7Z" fill="{{ $branding->primaryColor }}"/><text x="24" y="33" text-anchor="middle" font-family="Inter, sans-serif" font-size="11" font-weight="900" fill="{{ $branding->primaryDarkColor }}" stroke="#ffffff" stroke-width="0.6" paint-order="stroke">SPM</text></svg>
        @endif
    </div>
    <div>
        <p class="club">{{ $branding->clubName }}</p>
        <h1>{{ $title }}</h1>
        <p class="date">{{ $dateLabel }}</p>
        @if ($subtitle !== '')
            <p class="subtitle">{{ $subtitle }}</p>
        @endif
    </div>
</header>
