<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('messages.pdf.teams_title', ['season' => $season->name]) }}</title>
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
        <h1>{{ $org->name }} <span class="muted small">— {{ __('messages.pdf.teams_title', ['season' => $season->name]) }}</span></h1>
        <div class="meta">{!! __('messages.pdf.teams_subtitle', ['count' => $teams->count(), 'datetime' => now()->format('Y-m-d H:i')]) !!}</div>
    </div>

    <div class="page">
        <table>
            <thead>
                <tr>
                    <th>{{ __('messages.pdf.col_team') }}</th>
                    <th>{{ __('messages.pdf.col_short') }}</th>
                    <th>{{ __('messages.pdf.col_owner') }}</th>
                    <th>{{ __('messages.pdf.col_players_bought') }}</th>
                    <th>{{ __('messages.pdf.col_initial') }}</th>
                    <th>{{ __('messages.pdf.col_spent') }}</th>
                    <th>{{ __('messages.pdf.col_remaining') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($teams as $t)
                <tr>
                    <td><strong>{{ $t->name }}</strong></td>
                    <td>{{ $t->short_code ?? '—' }}</td>
                    <td>{{ $t->owner?->name ?? '—' }}</td>
                    <td class="num">{{ Money::number((int) $t->players_bought) }}</td>
                    <td class="num">{{ Money::format((int) $t->initial_budget, $loc, $cur, $rate) }}</td>
                    <td class="num">{{ Money::format((int) $t->spent, $loc, $cur, $rate) }}</td>
                    <td class="num">{{ Money::format((int) $t->remaining_budget, $loc, $cur, $rate) }}</td>
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
