{{-- Shared CSS for all PDF exports. dompdf is conservative with CSS — keep it simple. --}}
@php
    $primary    = '#3b82f6';
    $primaryDk  = '#6366f1';
    $accent     = '#8b5cf6';
    $textBody   = '#0a0e27';
    $textMuted  = '#64748b';
    $borderSoft = '#e2e8f0';

    // Solaiman Lipi is the standard open Bangla typeface. We register it via @font-face
    // only if the file is present in storage/fonts/ — keeps the PDF pipeline working
    // out-of-the-box for English exports while letting Bengali users drop the TTF in
    // for proper glyph rendering. See storage/fonts/README.md.
    $solaimanPath     = storage_path('fonts/SolaimanLipi.ttf');
    $solaimanBoldPath = storage_path('fonts/SolaimanLipi-Bold.ttf');
    $hasSolaiman      = file_exists($solaimanPath);
    $hasSolaimanBold  = file_exists($solaimanBoldPath);
@endphp
<style>
    @if ($hasSolaiman)
    @font-face {
        font-family: 'Solaiman Lipi';
        font-style: normal;
        font-weight: 400;
        src: url('{{ $solaimanPath }}') format('truetype');
    }
    @endif
    @if ($hasSolaimanBold)
    @font-face {
        font-family: 'Solaiman Lipi';
        font-style: normal;
        font-weight: 700;
        src: url('{{ $solaimanBoldPath }}') format('truetype');
    }
    @endif

    * { box-sizing: border-box; }
    body {
        /* Solaiman Lipi handles Bangla glyphs; DejaVu Sans handles Latin + diacritics. */
        font-family: {{ $hasSolaiman ? "'Solaiman Lipi', " : '' }}'DejaVu Sans', sans-serif;
        color: {{ $textBody }}; font-size: 11px; line-height: 1.55; margin: 0; padding: 0;
    }
    .header { padding: 18px 22px; border-bottom: 2px solid {{ $primary }}; margin-bottom: 18px; }
    .header h1 { margin: 0 0 4px; font-size: 18px; }
    .header .meta { color: {{ $textMuted }}; font-size: 10.5px; }
    .badge { display: inline-block; padding: 1px 7px; border-radius: 999px; background: #e0f2fe; color: {{ $primary }}; font-size: 9.5px; font-weight: 600; }
    .pill-pro { background: #ede9fe; color: {{ $accent }}; }
    table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
    table.compact { font-size: 9.5px; }
    th { text-align: left; padding: 7px 8px; background: #f1f5f9; color: {{ $textMuted }}; font-weight: 600; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid {{ $borderSoft }}; }
    td { padding: 7px 8px; border-bottom: 1px solid {{ $borderSoft }}; vertical-align: top; }
    tr:nth-child(even) td { background: #fafafa; }
    /* Numbers/refs benefit from a monospace; keep DejaVu Mono — Bangla numerals fall through to body font. */
    .num { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
    .muted { color: {{ $textMuted }}; }
    .small { font-size: 9.5px; }
    .totals { display: table; width: 100%; margin: 12px 0 18px; }
    .totals .cell { display: table-cell; padding: 10px 14px; border: 1px solid {{ $borderSoft }}; text-align: center; }
    .totals .label { font-size: 9px; color: {{ $textMuted }}; text-transform: uppercase; letter-spacing: 0.06em; }
    .totals .value { font-size: 16px; font-weight: 700; margin-top: 2px; }
    .footer { position: fixed; bottom: 12px; left: 22px; right: 22px; color: {{ $textMuted }}; font-size: 9px; border-top: 1px solid {{ $borderSoft }}; padding-top: 6px; display: flex; justify-content: space-between; }
    .page { padding: 22px; }
    .status-sold   { color: #047857; font-weight: 600; }
    .status-unsold { color: #475569; }
    .status-pending{ color: #b45309; }
    .status-queue  { color: {{ $primary }}; }
    h2 { font-size: 13px; margin: 18px 0 8px; }
</style>

@if (! $hasSolaiman && app()->getLocale() === 'bn')
    {{-- Tiny watermark to make the missing font obvious in dev — won't show in EN exports. --}}
    <div style="position:fixed;top:6px;right:22px;font-size:8px;color:#dc2626;">
        ⚠ Solaiman Lipi font missing — Bangla glyphs may not render
    </div>
@endif
