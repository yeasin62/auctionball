<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $season->name }} — {{ __('messages.pdf.h_team_leaderboard') }}</title>
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
        <h1>{{ $season->name }} <span class="muted small">— {{ $org->name }}</span></h1>
        <div class="meta">{!! __('messages.pdf.season_subtitle', ['datetime' => now()->format('Y-m-d H:i')]) !!}</div>
    </div>

    <div class="page">
        <div class="totals">
            <div class="cell">
                <div class="label">{{ __('messages.pdf.kpi_total_spent') }}</div>
                <div class="value">{{ Money::format((int) $totals['spent'], $loc, $cur, $rate) }}</div>
            </div>
            <div class="cell">
                <div class="label">{{ __('messages.pdf.kpi_players_sold') }}</div>
                <div class="value">{{ Money::number((int) $totals['sold']) }}</div>
            </div>
            <div class="cell">
                <div class="label">{{ __('messages.pdf.kpi_unsold') }}</div>
                <div class="value">{{ Money::number((int) $totals['unsold']) }}</div>
            </div>
            <div class="cell">
                <div class="label">{{ __('messages.pdf.kpi_total_bids') }}</div>
                <div class="value">{{ Money::number((int) $totals['bids']) }}</div>
            </div>
        </div>

        <h2>{{ __('messages.pdf.h_team_leaderboard') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('messages.pdf.col_index') }}</th>
                    <th>{{ __('messages.pdf.col_team') }}</th>
                    <th>{{ __('messages.pdf.col_players') }}</th>
                    <th>{{ __('messages.pdf.col_spent') }}</th>
                    <th>{{ __('messages.pdf.col_remaining') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($teams as $i => $t)
                <tr>
                    <td class="num muted">{{ $i + 1 }}</td>
                    <td><strong>{{ $t->name }}</strong> @if($t->short_code)<span class="muted small">· {{ $t->short_code }}</span>@endif</td>
                    <td class="num">{{ Money::number((int) $t->players_bought) }}</td>
                    <td class="num">{{ Money::format((int) $t->spent, $loc, $cur, $rate) }}</td>
                    <td class="num">{{ Money::format((int) $t->remaining_budget, $loc, $cur, $rate) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <h2>{{ __('messages.pdf.h_top_sold') }}</h2>
        <table class="compact">
            <thead>
                <tr>
                    <th>{{ __('messages.pdf.col_index') }}</th>
                    <th>{{ __('messages.pdf.col_name') }}</th>
                    <th>{{ __('messages.pdf.col_category') }}</th>
                    <th>{{ __('messages.pdf.col_team') }}</th>
                    <th>{{ __('messages.pdf.col_sold') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topPlayers as $i => $p)
                <tr>
                    <td class="num muted">{{ $i + 1 }}</td>
                    <td><strong>{{ $p->name }}</strong></td>
                    <td>{{ $p->category }}</td>
                    <td>{{ $p->team?->name ?? '—' }}</td>
                    <td class="num">{{ Money::format((int) $p->sold_price, $loc, $cur, $rate) }}</td>
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
