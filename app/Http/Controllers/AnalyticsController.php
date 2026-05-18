<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();

        $payload = [
            'season'  => null,
            'summary' => null,
            'plan'    => $org->plan,
            'gated'   => ! in_array($org->plan, ['pro', 'enterprise']),
        ];

        if (! $season) {
            return Inertia::render('Dashboard/Analytics/Index', $payload);
        }

        $payload['season'] = ['id' => $season->id, 'name' => $season->name];

        // Cache aggregate queries for 60s — under heavy refresh from big screen, this is the difference
        // between "DB on fire" and "DB chilled out". Cache key includes org+season+last bid id.
        $latestBidId = (int) $season->bids()->max('id');
        $cacheKey    = "analytics:{$org->id}:{$season->id}:{$latestBidId}";

        $payload['summary'] = cache()->remember($cacheKey, 60, fn () => $this->compute($season));

        return Inertia::render('Dashboard/Analytics/Index', $payload);
    }

    private function compute($season): array
    {
        $totalSold     = $season->players()->where('auction_status', 'sold')->count();
        $totalUnsold   = $season->players()->where('auction_status', 'unsold')->count();
        $totalQueue    = $season->players()->where('auction_status', 'queue')->count();
        $totalPlayers  = $totalSold + $totalUnsold + $totalQueue;
        $totalSpent    = (int) $season->players()->where('auction_status', 'sold')->sum('sold_price');
        $totalBudget   = (int) $season->teams()->sum('initial_budget');
        $remainingPool = (int) $season->teams()->sum('remaining_budget');
        $bidsTotal     = (int) $season->bids()->count();

        // Top 10 sold players
        $topPlayers = $season->players()
            ->where('auction_status', 'sold')
            ->with('team:id,name,short_code')
            ->orderByDesc('sold_price')
            ->limit(10)
            ->get(['id', 'name', 'category', 'sold_price', 'team_id'])
            ->map(fn ($p) => [
                'name'       => $p->name,
                'category'   => $p->category,
                'sold_price' => (int) $p->sold_price,
                'team'       => $p->team?->short_code ?? $p->team?->name ?? '—',
            ])->values();

        // Spend by team
        $spendByTeam = $season->teams()->orderBy('name')->get()
            ->map(fn ($t) => [
                'team'       => $t->short_code ?? $t->name,
                'name'       => $t->name,
                'spent'      => (int) ($t->initial_budget - $t->remaining_budget),
                'initial'    => (int) $t->initial_budget,
                'players'    => (int) $season->players()->where('team_id', $t->id)->where('auction_status','sold')->count(),
            ])->values();

        // Spend by category — iterate the season's configured categories so a
        // renamed/added category shows up immediately. Players whose category
        // was nulled out by a category removal won't appear here (intentional;
        // re-assign them on the Players page).
        $spendByCategory = collect($season->categoryNames())->map(function ($c) use ($season) {
            $rows = $season->players()->where('category', $c)->where('auction_status', 'sold');
            return [
                'category' => $c,
                'count'    => (int) (clone $rows)->count(),
                'spent'    => (int) (clone $rows)->sum('sold_price'),
                'avg'      => (int) (clone $rows)->avg('sold_price'),
            ];
        })->values();

        // Player status pie
        $statusBreakdown = [
            ['status' => 'sold',   'count' => $totalSold,   'pct' => $totalPlayers ? round($totalSold   / $totalPlayers * 100, 1) : 0],
            ['status' => 'unsold', 'count' => $totalUnsold, 'pct' => $totalPlayers ? round($totalUnsold / $totalPlayers * 100, 1) : 0],
            ['status' => 'queue',  'count' => $totalQueue,  'pct' => $totalPlayers ? round($totalQueue  / $totalPlayers * 100, 1) : 0],
        ];

        // Bid activity timeline — bids per hour. Hour-bucketing varies by driver:
        // SQLite uses strftime, MySQL/MariaDB use DATE_FORMAT, Postgres uses to_char.
        // Bucket in PHP instead so the query is driver-agnostic and migrates cleanly
        // when deploying to MySQL/Postgres in production.
        $bidTimeline = DB::table('bids')
            ->select('placed_at', 'amount')
            ->where('season_id', $season->id)
            ->orderBy('placed_at')
            ->get()
            ->groupBy(fn ($r) => \Carbon\Carbon::parse($r->placed_at)->format('Y-m-d H:00'))
            ->map(fn ($rows, $bucket) => [
                'bucket' => $bucket,
                'bids'   => $rows->count(),
                'spend'  => (int) $rows->sum('amount'),
            ])
            ->values();

        return [
            'totals' => [
                'players'        => $totalPlayers,
                'sold'           => $totalSold,
                'unsold'         => $totalUnsold,
                'queue'          => $totalQueue,
                'spent'          => $totalSpent,
                'remaining_pool' => $remainingPool,
                'budget_total'   => $totalBudget,
                'bids_total'     => $bidsTotal,
                'avg_sold_price' => $totalSold ? (int) round($totalSpent / $totalSold) : 0,
            ],
            'top_players'     => $topPlayers,
            'spend_by_team'   => $spendByTeam,
            'spend_by_category' => $spendByCategory,
            'status_breakdown'  => $statusBreakdown,
            'bid_timeline'      => $bidTimeline,
        ];
    }
}
