<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');

        // Team owners have one job — bid. Send them straight to the bid device
        // page so they don't land on a dashboard full of irrelevant admin tools.
        $role = $request->user()->organizations()->where('organizations.id', $org->id)->first()?->pivot->role;
        if ($role === 'team_owner') {
            return redirect()->route('bid.show');
        }

        $season = $org->activeSeason();

        $stats = [
            'players_total'  => $season ? $season->players()->count() : 0,
            'players_sold'   => $season ? $season->players()->where('auction_status', 'sold')->count() : 0,
            'players_unsold' => $season ? $season->players()->where('auction_status', 'unsold')->count() : 0,
            'players_queue'  => $season ? $season->players()->where('auction_status', 'queue')->count() : 0,
            'teams_total'    => $season ? $season->teams()->count() : 0,
            'bids_total'     => $season ? $season->bids()->count() : 0,
            'budget_total'   => $season ? (int) $season->teams()->sum('initial_budget')   : 0,
            'budget_left'    => $season ? (int) $season->teams()->sum('remaining_budget') : 0,
        ];

        $teams = $season
            ? $season->teams()->orderBy('name')->get()->map(fn ($t) => [
                'id'        => $t->id,
                'name'      => $t->name,
                'short'     => $t->short_code ?? mb_substr($t->name, 0, 3),
                'spent'     => $t->spent(),
                'initial'   => $t->initial_budget,
                'remaining' => $t->remaining_budget,
                'pct'       => $t->initial_budget > 0
                    ? (int) round(($t->spent() / $t->initial_budget) * 100)
                    : 0,
            ])
            : collect();

        $recentBids = $season
            ? $season->bids()->latest('placed_at')->with(['player:id,name', 'team:id,name'])->limit(8)->get()
                ->map(fn ($b) => [
                    'id'         => $b->id,
                    'amount'     => $b->amount,
                    'player'     => $b->player?->name,
                    'team'       => $b->team?->name,
                    'placed_at'  => $b->placed_at?->format('H:i:s'),
                ])
            : collect();

        return Inertia::render('Dashboard/Home', [
            'season'     => $season ? [
                'id'     => $season->id,
                'name'   => $season->name,
                'year'   => $season->year,
                'status' => $season->status,
            ] : null,
            'stats'      => $stats,
            'teams'      => $teams,
            'recentBids' => $recentBids,
        ]);
    }
}
