<?php

namespace App\Events;

use App\Models\AuctionState;
use App\Models\Bid;
use App\Models\Player;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * `ShouldBroadcastNow` (not the queued `ShouldBroadcast`) because every
 * millisecond matters in a live auction. Queue-backed broadcasting would
 * silently break real-time updates whenever the queue worker isn't running,
 * which is the most common cause of "live auction not updating" reports.
 * Synchronous broadcast adds ~5-15ms latency per event but guarantees delivery
 * without an extra background process.
 */
class AuctionStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $orgId,
        public int $seasonId,
        public string $reason = 'state.updated',
        public ?array $extra = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("auction.{$this->orgId}.{$this->seasonId}")];
    }

    public function broadcastAs(): string
    {
        // Single named event; client switches on payload.reason for fine-grained UI.
        return 'auction.event';
    }

    public function broadcastWith(): array
    {
        $state = AuctionState::with([
            'currentPlayer:id,name,photo_url,category,position,player_type,base_price,jersey_no,profession,batting_style,bowling_style,team_id,sold_price,auction_status',
            'highestBidder:id,name,short_code',
        ])->where('organization_id', $this->orgId)
          ->where('season_id', $this->seasonId)
          ->first();

        $bids = Bid::with('team:id,name,short_code')
            ->where('organization_id', $this->orgId)
            ->where('season_id', $this->seasonId)
            ->when($state?->current_player_id, fn ($q) => $q->where('player_id', $state->current_player_id))
            ->latest('placed_at')->limit(10)->get()
            ->map(fn ($b) => [
                'id'        => $b->id,
                'team'      => $b->team?->short_code ?? $b->team?->name,
                'amount'    => (int) $b->amount,
                'placed_at' => $b->placed_at?->format('H:i:s'),
            ])->values();

        return [
            'reason'    => $this->reason,
            'extra'     => $this->extra,
            'state'     => $state ? [
                'status'                  => $state->status,
                'highest_bid'             => (int) $state->highest_bid,
                'highest_bidder'          => $state->highestBidder ? [
                    'id'    => $state->highestBidder->id,
                    'name'  => $state->highestBidder->name,
                    'short' => $state->highestBidder->short_code,
                ] : null,
                'timer_end'               => $state->timer_end?->toIso8601String(),
                'timer_duration_seconds'  => (int) $state->timer_duration_seconds,
                'last_bid_at'             => $state->last_bid_at?->toIso8601String(),
                'server_now'              => now()->toIso8601String(),
            ] : null,
            'player'    => $state?->currentPlayer ? [
                'id'             => $state->currentPlayer->id,
                'name'           => $state->currentPlayer->name,
                'photo_url'      => $state->currentPlayer->photo_url,
                'category'       => $state->currentPlayer->category,
                'position'       => $state->currentPlayer->position,
                'player_type'    => $state->currentPlayer->player_type,
                'base_price'     => (int) $state->currentPlayer->base_price,
                'jersey_no'      => $state->currentPlayer->jersey_no,
                'profession'     => $state->currentPlayer->profession,
                'batting_style'  => $state->currentPlayer->batting_style,
                'bowling_style'  => $state->currentPlayer->bowling_style,
                'auction_status' => $state->currentPlayer->auction_status,
                'sold_price'     => $state->currentPlayer->sold_price ? (int) $state->currentPlayer->sold_price : null,
                'team_id'        => $state->currentPlayer->team_id,
            ] : null,
            'bids'      => $bids,
            'broadcast_at' => now()->toIso8601String(),
        ];
    }
}
