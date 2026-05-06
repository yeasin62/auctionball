<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Season;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Players CSV — streams row-by-row, scales to large rosters. */
    public function playersCsv(Request $request): StreamedResponse
    {
        $this->guardCsv($request);
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $this->season($request);

        $loc      = App::getLocale();
        $currency = $org->display_currency;
        $rate     = (int) $org->bdt_per_usd;

        $filename = 'players-' . str()->slug($season->name) . '.csv';

        return response()->streamDownload(function () use ($season, $loc, $currency, $rate) {
            $h = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens Bengali column headers without garbling.
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, [
                __('messages.pdf.col_name'),
                __('messages.pdf.col_category'),
                __('messages.pdf.col_type'),
                __('messages.pdf.col_base'),
                __('messages.pdf.col_status'),
                __('messages.pdf.col_sold'),
                __('messages.pdf.col_team'),
                'Jersey', 'Batting', 'Bowling', 'Profession',
            ]);
            $season->players()->with('team:id,name')->orderBy('name')->chunk(500, function ($chunk) use ($h, $loc, $currency, $rate) {
                foreach ($chunk as $p) {
                    fputcsv($h, [
                        $p->name, $p->category, $p->player_type,
                        Money::format((int) $p->base_price, $loc, $currency, $rate),
                        $p->auction_status,
                        $p->sold_price ? Money::format((int) $p->sold_price, $loc, $currency, $rate) : '',
                        $p->team?->name,
                        $p->jersey_no, $p->batting_style, $p->bowling_style, $p->profession,
                    ]);
                }
            });
            fclose($h);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function teamsCsv(Request $request): StreamedResponse
    {
        $this->guardCsv($request);
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $this->season($request);

        $loc      = App::getLocale();
        $currency = $org->display_currency;
        $rate     = (int) $org->bdt_per_usd;

        $filename = 'teams-' . str()->slug($season->name) . '.csv';

        return response()->streamDownload(function () use ($season, $loc, $currency, $rate) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, [
                __('messages.pdf.col_team'),
                __('messages.pdf.col_short'),
                __('messages.pdf.col_owner'),
                __('messages.pdf.col_initial'),
                __('messages.pdf.col_spent'),
                __('messages.pdf.col_remaining'),
                __('messages.pdf.col_players_bought'),
            ]);
            foreach ($season->teams()->with('owner:id,name')->orderBy('name')->get() as $t) {
                fputcsv($h, [
                    $t->name, $t->short_code, $t->owner?->name,
                    Money::format((int) $t->initial_budget, $loc, $currency, $rate),
                    Money::format((int) ($t->initial_budget - $t->remaining_budget), $loc, $currency, $rate),
                    Money::format((int) $t->remaining_budget, $loc, $currency, $rate),
                    Money::number((int) $season->players()->where('team_id', $t->id)->where('auction_status', 'sold')->count()),
                ]);
            }
            fclose($h);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Players PDF — landscape A4 for wide table. */
    public function playersPdf(Request $request): Response
    {
        $this->guardPdf($request);
        $org    = $request->attributes->get('current_organization');
        $season = $this->season($request);

        $players = $season->players()->with('team:id,name,short_code')->orderBy('name')->get();

        $pdf = Pdf::loadView('exports.players-pdf', compact('org', 'season', 'players'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('players-' . str()->slug($season->name) . '.pdf');
    }

    public function teamsPdf(Request $request): Response
    {
        $this->guardPdf($request);
        $org    = $request->attributes->get('current_organization');
        $season = $this->season($request);

        $teams = $season->teams()->with('owner:id,name')->orderBy('name')->get()
            ->map(function ($t) use ($season) {
                $t->players_bought = $season->players()->where('team_id', $t->id)->where('auction_status', 'sold')->count();
                $t->spent          = $t->initial_budget - $t->remaining_budget;
                return $t;
            });

        $pdf = Pdf::loadView('exports.teams-pdf', compact('org', 'season', 'teams'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('teams-' . str()->slug($season->name) . '.pdf');
    }

    /** Full season summary — leaderboard + sold list + totals. */
    public function seasonSummaryPdf(Request $request, Season $season): Response
    {
        $this->guardPdf($request);
        $org = $request->attributes->get('current_organization');
        abort_if($season->organization_id !== $org->id, 404);

        $teams = $season->teams()->orderBy('name')->get()
            ->map(function ($t) use ($season) {
                $t->players_bought = $season->players()->where('team_id', $t->id)->where('auction_status', 'sold')->count();
                $t->spent          = $t->initial_budget - $t->remaining_budget;
                return $t;
            })
            ->sortByDesc('spent')->values();

        $topPlayers = $season->players()
            ->where('auction_status', 'sold')
            ->with('team:id,name,short_code')
            ->orderByDesc('sold_price')->limit(20)->get();

        $totals = [
            'sold'      => $season->players()->where('auction_status', 'sold')->count(),
            'unsold'    => $season->players()->where('auction_status', 'unsold')->count(),
            'queue'     => $season->players()->where('auction_status', 'queue')->count(),
            'spent'     => (int) $season->players()->where('auction_status', 'sold')->sum('sold_price'),
            'bids'      => (int) $season->bids()->count(),
        ];

        $pdf = Pdf::loadView('exports.season-summary-pdf', compact('org', 'season', 'teams', 'topPlayers', 'totals'))
            ->setPaper('a4', 'portrait');

        // stream() renders inline so the new tab (`target="_blank"`) actually
        // shows the PDF in the browser; download() forces a save dialog and
        // closes the tab immediately, which made the link feel broken.
        return $pdf->stream('season-' . str()->slug($season->name) . '.pdf');
    }

    private function season(Request $request): Season
    {
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();
        abort_unless($season, 422, 'No active season.');
        return $season;
    }

    private function guardCsv(Request $request): void
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        if (! $org->limits()['export_csv']) {
            abort(403, "CSV export is on the Starter plan and above. You are on {$org->plan}.");
        }
    }

    private function guardPdf(Request $request): void
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        if (! $org->limits()['export_pdf']) {
            abort(403, "PDF export is on the Pro plan. You are on {$org->plan}.");
        }

        // Bengali users get warned (in app log) once per request when the Bangla font is missing.
        if (App::getLocale() === 'bn' && ! file_exists(storage_path('fonts/SolaimanLipi.ttf'))) {
            Log::warning('PDF: Solaiman Lipi font missing — Bangla glyphs will not render. See storage/fonts/README.md');
        }
    }
}
