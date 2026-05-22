<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Services\AuctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AuctionController extends Controller
{
    public function __construct(private readonly AuctionService $svc) {}

    public function control(Request $request): Response
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();

        $payload = ['season' => null, 'players' => [], 'teams' => [], 'state' => null, 'public_bigscreen_url' => null, 'reverb' => $this->reverbConfig()];

        if ($season) {
            $state = $this->svc->stateFor($season);
            $payload['season']  = [
                'id'            => $season->id,
                'name'          => $season->name,
                'org_id'        => $org->id,
                'auto_finalize' => (bool) ($season->auto_finalize ?? true),
            ];
            $payload['players'] = $season->players()->orderBy('auction_status')->orderBy('name')
                ->get(['id','name','photo_url','category','position','base_price','auction_status','sold_price','team_id']);
            $payload['teams'] = $season->teams()->orderBy('name')
                ->get(['id','name','short_code','remaining_budget','initial_budget']);
            $payload['state'] = $this->serializeState($state);
            $payload['public_bigscreen_url'] = route('public.live', [
                'organization' => $org->slug,
                'season' => $season->id,
            ]);
        }

        return Inertia::render('Dashboard/Auction/Control', $payload);
    }

    public function bigscreen(Request $request): Response
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();

        return Inertia::render('Dashboard/Auction/Bigscreen', $this->bigscreenPayload($org, $season));
    }

    public function publicBigscreen(Request $request, Organization $organization, ?Season $season = null): Response
    {
        if ($season) {
            abort_unless((int) $season->organization_id === (int) $organization->id, 404);
        } else {
            $season = $organization->activeSeason();
        }

        return $this->renderPublicBigscreen($request, $organization, $season);
    }

    public function publicBigscreenForDomain(Request $request): Response
    {
        $organization = $request->attributes->get('current_organization');
        abort_unless($organization instanceof Organization, 404);

        return $this->renderPublicBigscreen($request, $organization, $organization->activeSeason());
    }

    private function renderPublicBigscreen(Request $request, Organization $organization, ?Season $season): Response
    {
        if ($season) {
            $request->session()->put('public_bigscreen_org_id', $organization->id);
            $request->session()->put('public_bigscreen_season_id', $season->id);
        } else {
            $request->session()->forget(['public_bigscreen_org_id', 'public_bigscreen_season_id']);
        }

        $request->attributes->set('current_organization', $organization);

        return Inertia::render('Dashboard/Auction/Bigscreen', $this->bigscreenPayload($organization, $season));
    }

    private function bigscreenPayload(Organization $org, ?Season $season): array
    {
        $payload = [
            'org'    => [
                'name' => $org->name,
                'slug' => $org->slug,
                'plan' => $org->plan,
                'logo_url' => $org->logo_url,
                'is_white_label' => $org->isWhiteLabel(),
            ],
            'season' => null, 'state' => null, 'teams' => [], 'reverb' => $this->reverbConfig(),
        ];

        if ($season) {
            $state = $this->svc->stateFor($season);
            $payload['season'] = ['id' => $season->id, 'name' => $season->name, 'sport' => $season->sport ?? 'cricket', 'org_id' => $org->id];
            $payload['state']  = $this->serializeState($state);
            $payload['teams']  = $season->teams()->orderBy('name')
                ->get(['id','name','short_code','remaining_budget','initial_budget']);
        }

        return $payload;
    }

    /**
     * Roster board — every team with their sold-player cards underneath.
     * Spectator-friendly view; refreshes itself on auction.sold via the same
     * private auction channel the Bigscreen subscribes to.
     */
    public function rosters(Request $request): Response
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();

        $payload = [
            'org'    => ['name' => $org->name, 'slug' => $org->slug, 'plan' => $org->plan, 'logo_url' => $org->logo_url],
            'season' => null,
            'teams'  => [],
            'unsold_count' => 0,
            'reverb' => $this->reverbConfig(),
        ];

        if ($season) {
            $payload['season'] = ['id' => $season->id, 'name' => $season->name, 'sport' => $season->sport ?? 'cricket', 'org_id' => $org->id];

            $teams = $season->teams()
                ->with(['players' => function ($q) {
                    $q->where('auction_status', 'sold')->orderByDesc('sold_price');
                }])
                ->orderBy('name')
                ->get();

            $payload['teams'] = $teams->map(fn ($t) => [
                'id'              => $t->id,
                'name'            => $t->name,
                'short_code'      => $t->short_code,
                'initial_budget'  => (int) $t->initial_budget,
                'remaining_budget'=> (int) $t->remaining_budget,
                'players'         => $t->players->map(fn ($p) => [
                    'id'                => $p->id,
                    'name'              => $p->name,
                    'photo_url'         => $p->photo_url,
                    'position'          => $p->position,
                    'category'          => $p->category,
                    'jersey_no'         => $p->jersey_no,
                    'profession'        => $p->profession,
                    'batting_style'     => $p->batting_style,
                    'bowling_style'     => $p->bowling_style,
                    // Org-defined custom fields (district, contact, etc.) — render as
                    // "Label: Value" meta lines on the card.
                    'registration_data' => $p->registration_data,
                    'sold_price'        => (int) $p->sold_price,
                ]),
            ]);

            $payload['unsold_count'] = $season->players()->where('auction_status', 'sold')->whereNull('team_id')->count()
                + $season->players()->where('auction_status', 'unsold')->count();
        }

        return Inertia::render('Dashboard/Auction/Rosters', $payload);
    }

    public function setPlayer(Request $request): RedirectResponse|JsonResponse
    {
        $season = $this->season($request);
        $player = Player::findOrFail($request->validate(['player_id' => 'required|integer'])['player_id']);
        $this->svc->setPlayer($season, $player);
        return back();
    }

    public function start(Request $request): RedirectResponse
    {
        $data   = $request->validate(['duration' => 'sometimes|integer|min:5|max:300']);
        $season = $this->season($request);
        try { $this->svc->start($season, $data['duration'] ?? 60); }
        catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back();
    }

    public function pause(Request $request): RedirectResponse
    {
        $this->svc->pause($this->season($request));
        return back();
    }

    public function resume(Request $request): RedirectResponse
    {
        try { $this->svc->resume($this->season($request)); }
        catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back();
    }

    public function sold(Request $request): RedirectResponse
    {
        try { $this->svc->sold($this->season($request)); }
        catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back();
    }

    public function unsold(Request $request): RedirectResponse
    {
        try { $this->svc->unsold($this->season($request)); }
        catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back();
    }

    public function reset(Request $request): RedirectResponse
    {
        try { $this->svc->reset($this->season($request)); }
        catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back();
    }

    public function extendTimer(Request $request): RedirectResponse
    {
        $data = $request->validate(['seconds' => 'required|integer|min:1|max:600']);
        try { $this->svc->extendTimer($this->season($request), $data['seconds']); }
        catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }
        return back();
    }

    /**
     * Idempotent auto-finalize: when the timer expires and the season's
     * `auto_finalize` flag is on, any client (typically the control panel)
     * fires this. Server checks state; if still running and timer passed,
     * it marks SOLD (highest_bidder set) or UNSOLD (no bids). Repeated calls
     * are no-ops because `sold()`/`unsold()` reset the state.
     */
    public function autoFinalize(Request $request): RedirectResponse
    {
        $season = $this->season($request);
        $state  = $this->svc->stateFor($season);

        // Defence in depth — only act if flag is on, lot is running, and timer
        // has actually elapsed. Last-second bid extensions push timer_end out;
        // re-checking here prevents racing with extension events.
        if (! ($season->auto_finalize ?? true)) return back();
        if ($state->status !== 'running')      return back();
        if ($state->timer_end && $state->timer_end->isFuture()) return back();

        try {
            if ($state->highest_bidder_team_id) {
                $this->svc->sold($season);
            } else {
                $this->svc->unsold($season);
            }
        } catch (RuntimeException $e) {
            // Most likely cause: another client raced us — already finalized.
            return back();
        }
        return back();
    }

    public function setAutoFinalize(Request $request): RedirectResponse
    {
        $season = $this->season($request);
        $data   = $request->validate(['auto_finalize' => 'required|boolean']);
        $season->update(['auto_finalize' => $data['auto_finalize']]);
        return back();
    }

    public function placeBid(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => 'required|integer',
            'amount'  => 'required|integer|min:1',
        ]);
        $season = $this->season($request);
        $team   = Team::where('id', $data['team_id'])
            ->where('organization_id', $season->organization_id)
            ->where('season_id', $season->id)
            ->firstOrFail();

        try { $this->svc->placeBid($season, $team, (int) $data['amount']); }
        catch (RuntimeException $e) { return back()->with('error', $e->getMessage()); }

        return back();
    }

    private function season(Request $request)
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();
        abort_unless($season, 422, 'No active season.');
        return $season;
    }

    private function serializeState($state): array
    {
        $state->loadMissing(['currentPlayer', 'highestBidder']);
        return [
            'status'                  => $state->status,
            'highest_bid'             => (int) $state->highest_bid,
            'highest_bidder'          => $state->highestBidder ? [
                'id' => $state->highestBidder->id,
                'name' => $state->highestBidder->name,
                'short' => $state->highestBidder->short_code,
            ] : null,
            'timer_end'               => $state->timer_end?->toIso8601String(),
            'timer_duration_seconds'  => (int) $state->timer_duration_seconds,
            'server_now'              => now()->toIso8601String(),
            'player'                  => $state->currentPlayer ? [
                'id'         => $state->currentPlayer->id,
                'name'       => $state->currentPlayer->name,
                'photo_url'   => $state->currentPlayer->photo_url,
                'category'    => $state->currentPlayer->category,
                'position'    => $state->currentPlayer->position,
                'player_type' => $state->currentPlayer->player_type,
                'base_price'  => (int) $state->currentPlayer->base_price,
                'jersey_no'   => $state->currentPlayer->jersey_no,
                'profession'  => $state->currentPlayer->profession,
                'batting_style' => $state->currentPlayer->batting_style,
                'bowling_style' => $state->currentPlayer->bowling_style,
                'auction_status' => $state->currentPlayer->auction_status,
                'sold_price' => $state->currentPlayer->sold_price,
                'team_id'    => $state->currentPlayer->team_id,
            ] : null,
        ];
    }

    private function reverbConfig(): array
    {
        return [
            'key'     => config('broadcasting.connections.reverb.key'),
            'host'    => config('reverb.servers.reverb.host', env('REVERB_HOST')),
            'port'    => (int) config('reverb.servers.reverb.port', env('REVERB_PORT')),
            'scheme'  => env('REVERB_SCHEME', 'http'),
        ];
    }
}
