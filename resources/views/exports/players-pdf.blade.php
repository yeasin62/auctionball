<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('messages.pdf.players_title') }} — {{ $season->name }}</title>
    @include('exports._pdf-base')
</head>
<body>
    @php
        use App\Support\Money;
        $cur  = $org->display_currency;
        $rate = (int) $org->bdt_per_usd;
        $loc  = app()->getLocale();
    @endphp

    <div class="header">
        <h1>{{ $org->name }} <span class="muted small">— {{ $season->name }} ({{ $season->year }})</span></h1>
        <div class="meta">
            {{ __('messages.pdf.players_title') }} ·
            {!! __('messages.pdf.players_subtitle', ['count' => $players->count(), 'datetime' => now()->format('Y-m-d H:i')]) !!}
        </div>
    </div>

    <div class="page">
        <table>
            <thead>
                <tr>
                    <th>{{ __('messages.pdf.col_index') }}</th>
                    <th>{{ __('messages.pdf.col_name') }}</th>
                    <th>{{ __('messages.pdf.col_category') }}</th>
                    <th>{{ __('messages.pdf.col_type') }}</th>
                    <th>{{ __('messages.pdf.col_base') }}</th>
                    <th>{{ __('messages.pdf.col_status') }}</th>
                    <th>{{ __('messages.pdf.col_sold') }}</th>
                    <th>{{ __('messages.pdf.col_team') }}</th>
                    <th>{{ __('messages.pdf.col_batting') }}</th>
                    <th>{{ __('messages.pdf.col_bowling') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($players as $i => $p)
                <tr>
                    <td class="num muted">{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ $p->name }}</strong>
                        @if($p->jersey_no)<span class="muted small"> · #{{ $p->jersey_no }}</span>@endif
                    </td>
                    <td>{{ $p->category }}</td>
                    <td>{{ $p->player_type }}</td>
                    <td class="num">{{ Money::format((int) $p->base_price, $loc, $cur, $rate) }}</td>
                    <td><span class="status-{{ $p->auction_status }}">{{ strtoupper($p->auction_status) }}</span></td>
                    <td class="num">{{ $p->sold_price ? Money::format((int) $p->sold_price, $loc, $cur, $rate) : '—' }}</td>
                    <td>{{ $p->team?->name ?? '—' }}</td>
                    <td class="small muted">{{ $p->batting_style ?? '—' }}</td>
                    <td class="small muted">{{ $p->bowling_style ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <span>{{ $org->name }} · {{ \App\Models\PlatformSettings::current()->app_domain }}</span>
        <span>{{ __('messages.pdf.page') }} <span class="pagenum"></span></span>
    </div>
</body>
</html>
