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
    footer { position: absolute; left: 0; right: 0; bottom: 0; height: {{ \App\Domain\Imaging\LayoutPlanner::FOOTER }}px; display: flex; align-items: center; justify-content: space-between; padding: 0 72px; color: #fff; font-size: 34px; font-weight: 700; }
    footer .page { font-size: 28px; font-weight: 600; opacity: 0.85; }
</style>
