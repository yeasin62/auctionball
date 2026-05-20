<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Team;
use App\Services\AuctionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TeamDeviceController extends Controller
{
    public function __construct(private readonly AuctionService $svc) {}

    /** Token-based entry — share link, kiosk, captain without an account. */
    public function show(Request $request, string $token): Response
    {
        $team = Team::where('device_token', $token)->firstOrFail();

        // Bind device session — also used by the channel auth callback in routes/channels.php
        session([
            'team_device_team_id'         => $team->id,
            'team_device_organization_id' => $team->organization_id,
            'team_device_season_id'       => $team->season_id,
        ]);

        return $this->renderShow($team, signedIn: false);
    }

    /** Logged-in entry — captain signs in with their AuctionBall account. */
    public function forCurrentUser(Request $request): Response|RedirectResponse
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $user   = $request->user();
        $season = $org->activeSeason();

        if (! $season) {
            return back()->with('error', 'No active season in this organization yet.');
        }

        // Pull the team the user is bound to via the organization_user pivot
        $pivot = $user->organizations()->where('organizations.id', $org->id)->first()?->pivot;
        $teamId = $pivot?->team_id;

        if (! $teamId) {
            return Inertia::render('TeamDevice/NotAssigned', [
                'org'  => ['name' => $org->name],
                'role' => $pivot?->role,
            ]);
        }

        $team = Team::where('id', $teamId)
            ->where('organization_id', $org->id)
            ->where('season_id', $season->id)
            ->first();

        if (! $team) {
            return Inertia::render('TeamDevice/NotAssigned', [
                'org'  => ['name' => $org->name],
                'role' => $pivot->role,
                'reason' => 'team_not_in_active_season',
            ]);
        }

        return $this->renderShow($team, signedIn: true);
    }

    /** POST bid endpoint for token-based devices. */
    public function bid(Request $request, string $token): RedirectResponse
    {
        $team = Team::where('device_token', $token)->firstOrFail();
        return $this->placeBid($team, $request);
    }

    /** POST bid endpoint for logged-in users — resolves team via pivot. */
    public function bidAsUser(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org  = $request->attributes->get('current_organization');
        $user = $request->user();

        $teamId = $user->organizations()->where('organizations.id', $org->id)->first()?->pivot->team_id;
        $team   = $teamId
            ? Team::where('id', $teamId)->where('organization_id', $org->id)->first()
            : null;

        if (! $team) return back()->with('error', 'You are not assigned to a team in this organization.');

        return $this->placeBid($team, $request);
    }

    /** Shared render — both modes hit the same Vue page. */
    private function renderShow(Team $team, bool $signedIn): Response
    {
        $season = $team->season;
        $org    = $team->organization;
        $state  = $this->svc->stateFor($season);

        return Inertia::render('TeamDevice/Show', [
            'org'     => ['name' => $org->name, 'slug' => $org->slug, 'logo_url' => $org->logo_url],
            'season'  => [
                'id'                => $season->id,
                'name'              => $season->name,
                'org_id'            => $org->id,
                'bid_increment'     => (int) ($season->bid_increment ?: 1000),
                'bid_increment_usd' => (int) ($season->bid_increment_usd ?: 10),
            ],
            'team'    => [
                'id'               => $team->id,
                'name'             => $team->name,
                'short_code'       => $team->short_code,
                'remaining_budget' => (int) $team->remaining_budget,
                'initial_budget'   => (int) $team->initial_budget,
                'device_token'     => $team->device_token,
            ],
            'state'   => $this->serializeState($state),
            'reverb'  => [
                'key'    => config('broadcasting.connections.reverb.key'),
                'host'   => env('REVERB_HOST'),
                'port'   => (int) env('REVERB_PORT'),
                'scheme' => env('REVERB_SCHEME', 'http'),
            ],
            'increments' => [5000, 10000, 25000, 50000],
            'signed_in'  => $signedIn,
        ]);
    }

    /** Shared placeBid path — used by both token + logged-in routes. */
    private function placeBid(Team $team, Request $request): RedirectResponse
    {
        $data = $request->validate(['amount' => 'required|integer|min:1']);

        try {
            $this->svc->placeBid($team->season, $team, (int) $data['amount']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    private function serializeState($state): array
    {
        $state->loadMissing(['currentPlayer', 'highestBidder']);
        return [
            'status'      => $state->status,
            'highest_bid' => (int) $state->highest_bid,
            'highest_bidder' => $state->highestBidder ? [
                'id'    => $state->highestBidder->id,
                'short' => $state->highestBidder->short_code,
                'name'  => $state->highestBidder->name,
            ] : null,
            'timer_end' => $state->timer_end?->toIso8601String(),
            'server_now' => now()->toIso8601String(),
            'player'    => $state->currentPlayer ? [
                'id'             => $state->currentPlayer->id,
                'name'           => $state->currentPlayer->name,
                'photo_url'      => $state->currentPlayer->photo_url,
                'category'       => $state->currentPlayer->category,
                'position'       => $state->currentPlayer->position,
                'base_price'     => (int) $state->currentPlayer->base_price,
            ] : null,
        ];
    }
}
