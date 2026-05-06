<?php

namespace App\Services;

use App\Events\AuctionStateUpdated;
use App\Models\AuctionState;
use App\Models\Bid;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AuctionService
{
    /**
     * Get-or-create the auction state row for a season.
     */
    public function stateFor(Season $season): AuctionState
    {
        return AuctionState::firstOrCreate(
            ['organization_id' => $season->organization_id, 'season_id' => $season->id],
            ['status' => 'idle', 'highest_bid' => 0, 'timer_duration_seconds' => 60]
        );
    }

    /**
     * Load a player into the live slot. Resets bid state.
     */
    public function setPlayer(Season $season, Player $player): AuctionState
    {
        $this->guardSamePlay($season, $player);

        $state = $this->stateFor($season);
        $state->update([
            'current_player_id'      => $player->id,
            'highest_bid'            => 0,
            'highest_bidder_team_id' => null,
            'status'                 => 'idle',
            'timer_end'              => null,
            'last_bid_at'            => null,
        ]);

        $this->broadcast($season, 'player.changed', ['player_id' => $player->id]);
        return $state->fresh();
    }

    /**
     * Start (or restart) the timer with a duration in seconds.
     */
    public function start(Season $season, int $durationSeconds): AuctionState
    {
        $state = $this->stateFor($season);

        if (! $state->current_player_id) {
            throw new RuntimeException('Pick a player before starting the auction.');
        }

        $duration = max(5, min(300, $durationSeconds));

        $state->update([
            'status'                 => 'running',
            'timer_duration_seconds' => $duration,
            'timer_end'              => now()->addSeconds($duration),
        ]);

        $this->broadcast($season, 'auction.started', ['duration' => $duration]);
        return $state->fresh();
    }

    public function pause(Season $season): AuctionState
    {
        $state = $this->stateFor($season);

        $remaining = $state->timer_end
            ? max(0, now()->diffInSeconds($state->timer_end, false))
            : $state->timer_duration_seconds;

        $state->update([
            'status'                 => 'paused',
            'timer_duration_seconds' => $remaining,
            'timer_end'              => null,
        ]);

        $this->broadcast($season, 'auction.paused');
        return $state->fresh();
    }

    public function resume(Season $season): AuctionState
    {
        $state = $this->stateFor($season);
        return $this->start($season, $state->timer_duration_seconds);
    }

    /**
     * Manually extend the running timer by N seconds. If the lot is paused, we
     * grow the remaining duration so the next resume() reflects the bonus time.
     * If running, we push timer_end forward; if it's already past 0, the
     * extension is from `now()` so the auctioneer doesn't have to chase a dead
     * clock.
     */
    public function extendTimer(Season $season, int $seconds): AuctionState
    {
        $state = $this->stateFor($season);
        if ($seconds <= 0 || ! $state->current_player_id) return $state;

        if ($state->status === 'paused') {
            $state->update([
                'timer_duration_seconds' => (int) $state->timer_duration_seconds + $seconds,
            ]);
        } else {
            $base = $state->timer_end && $state->timer_end->isFuture()
                ? $state->timer_end
                : now();
            $state->update([
                'timer_end'              => $base->copy()->addSeconds($seconds),
                'timer_duration_seconds' => max((int) $state->timer_duration_seconds, $seconds),
            ]);
        }

        $this->broadcast($season, 'timer.extended', ['seconds' => $seconds]);
        return $state->fresh();
    }

    /**
     * Place a bid for a team. Server-authoritative — increments only above the floor.
     */
    public function placeBid(Season $season, Team $team, int $amount): AuctionState
    {
        return DB::transaction(function () use ($season, $team, $amount) {
            $state = AuctionState::where('organization_id', $season->organization_id)
                ->where('season_id', $season->id)
                ->lockForUpdate()
                ->first();

            if (! $state || $state->status !== 'running') {
                throw new RuntimeException('Auction is not running.');
            }
            if (! $state->current_player_id) {
                throw new RuntimeException('No player on the block.');
            }
            if ($state->timer_end && now()->greaterThan($state->timer_end)) {
                throw new RuntimeException('Bid window closed.');
            }
            if ($team->organization_id !== $season->organization_id || $team->season_id !== $season->id) {
                throw new RuntimeException('Team does not belong to this auction.');
            }

            $player    = Player::find($state->current_player_id);
            $floor     = max((int) $state->highest_bid, (int) $player->base_price);

            // Per-currency step (no conversion). BDT-display orgs use bid_increment;
            // USD-display orgs use bid_increment_usd verbatim, scaled to BDT only at
            // validation time so internal storage stays canonical.
            $org  = $season->organization()->first();
            $rate = max(1, (int) ($org->bdt_per_usd ?: 110));
            $increment = $org->display_currency === 'USD'
                ? (int) ($season->bid_increment_usd ?: 10) * $rate
                : (int) ($season->bid_increment ?: 1000);

            // First bid (no highest_bid yet): only the floor (= base price) needs
            // to be matched. Subsequent bids must beat highest_bid by ≥ increment.
            $minRequired = (int) $state->highest_bid > 0
                ? $floor + $increment
                : $floor;

            if ($amount < $minRequired) {
                $display = Money::format($minRequired, app()->getLocale(), $org->display_currency, $rate);
                throw new RuntimeException("Bid must be at least {$display}.");
            }
            if ($amount > $team->remaining_budget) {
                throw new RuntimeException('Bid exceeds team budget.');
            }

            Bid::create([
                'organization_id' => $season->organization_id,
                'season_id'       => $season->id,
                'player_id'       => $state->current_player_id,
                'team_id'         => $team->id,
                'amount'          => $amount,
                'placed_at'       => now(),
            ]);

            // Anti-snipe: extend timer to at least 10s if less remains.
            $newTimerEnd = $state->timer_end;
            if ($state->timer_end && now()->diffInSeconds($state->timer_end, false) < 10) {
                $newTimerEnd = now()->addSeconds(10);
            }

            $state->update([
                'highest_bid'            => $amount,
                'highest_bidder_team_id' => $team->id,
                'last_bid_at'            => now(),
                'timer_end'              => $newTimerEnd,
            ]);

            $this->broadcast($season, 'bid.placed', [
                'team_id' => $team->id,
                'amount'  => $amount,
            ]);

            return $state->fresh();
        });
    }

    /**
     * Mark the current player SOLD. Deducts from team budget, assigns team to player.
     */
    public function sold(Season $season): AuctionState
    {
        return DB::transaction(function () use ($season) {
            $state = AuctionState::where('organization_id', $season->organization_id)
                ->where('season_id', $season->id)
                ->lockForUpdate()
                ->first();

            if (! $state?->current_player_id || ! $state->highest_bidder_team_id) {
                throw new RuntimeException('No bidder to sell to.');
            }

            $player = Player::find($state->current_player_id);
            $team   = Team::find($state->highest_bidder_team_id);
            $price  = (int) $state->highest_bid;

            if ($price > $team->remaining_budget) {
                throw new RuntimeException('Winning team budget insufficient (data drift?).');
            }

            $player->update([
                'auction_status' => 'sold',
                'sold_price'     => $price,
                'team_id'        => $team->id,
            ]);
            $team->decrement('remaining_budget', $price);

            $state->update([
                'status'    => 'sold',
                'timer_end' => null,
            ]);

            $this->broadcast($season, 'auction.sold', [
                'player_id' => $player->id,
                'team_id'   => $team->id,
                'price'     => $price,
            ]);

            // Always read fresh — relation cache on $season can be stale if the org
            // was edited mid-request (e.g. super-admin changed display_currency).
            $org = $season->organization()->first();
            $priceDisplay = Money::format($price, app()->getLocale(), $org->display_currency, (int) $org->bdt_per_usd);
            Audit::log(
                'auction.sold',
                "{$player->name} sold to {$team->name} for {$priceDisplay}",
                ['player_id' => $player->id, 'team_id' => $team->id, 'price' => $price],
                $player,
            );

            return $state->fresh();
        });
    }

    public function unsold(Season $season): AuctionState
    {
        return DB::transaction(function () use ($season) {
            $state = $this->stateFor($season);
            if (! $state->current_player_id) {
                throw new RuntimeException('No active player.');
            }

            $player = Player::find($state->current_player_id);
            $player?->update(['auction_status' => 'unsold']);
            $state->update([
                'status'    => 'unsold',
                'timer_end' => null,
            ]);

            $this->broadcast($season, 'auction.unsold', ['player_id' => $state->current_player_id]);
            Audit::log(
                'auction.unsold',
                "{$player?->name} marked unsold",
                ['player_id' => $state->current_player_id],
                $player,
            );
            return $state->fresh();
        });
    }

    /**
     * Reset the current player's bid history and return to queue.
     */
    public function reset(Season $season): AuctionState
    {
        return DB::transaction(function () use ($season) {
            $state = $this->stateFor($season);
            if (! $state->current_player_id) {
                throw new RuntimeException('No active player.');
            }

            $player = Player::find($state->current_player_id);
            $bidCount = Bid::where('player_id', $state->current_player_id)->count();
            Bid::where('player_id', $state->current_player_id)->delete();
            $player?->update([
                'auction_status' => 'queue',
                'sold_price'     => null,
                'team_id'        => null,
            ]);

            Audit::log(
                'auction.reset',
                "{$player?->name} reset (cleared {$bidCount} bids, returned to queue)",
                ['player_id' => $state->current_player_id, 'bids_cleared' => $bidCount],
                $player,
            );

            $state->update([
                'highest_bid'            => 0,
                'highest_bidder_team_id' => null,
                'status'                 => 'idle',
                'timer_end'              => null,
                'last_bid_at'            => null,
            ]);

            $this->broadcast($season, 'auction.reset', ['player_id' => $state->current_player_id]);
            return $state->fresh();
        });
    }

    private function broadcast(Season $season, string $reason, ?array $extra = null): void
    {
        broadcast(new AuctionStateUpdated(
            $season->organization_id,
            $season->id,
            $reason,
            $extra,
        ));
    }

    private function guardSamePlay(Season $season, Player $player): void
    {
        if ($player->organization_id !== $season->organization_id) {
            throw new RuntimeException('Player not in this organization.');
        }
        if ($player->season_id !== $season->id) {
            throw new RuntimeException('Player not in this season.');
        }
    }
}
