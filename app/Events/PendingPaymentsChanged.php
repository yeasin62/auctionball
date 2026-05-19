<?php

namespace App\Events;

use App\Models\PaymentTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pings the super-admin sidebar badge with the fresh pending-bKash count.
 * Fired when a customer submits a manual payment, or when a super admin
 * approves / rejects one. `ShouldBroadcastNow` mirrors AuctionStateUpdated —
 * synchronous so no queue worker is required for the badge to stay live.
 */
class PendingPaymentsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): array
    {
        return [new PrivateChannel('super-admin')];
    }

    public function broadcastAs(): string
    {
        return 'pending-payments.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'count' => PaymentTransaction::where('provider', 'bkash')
                ->where('status', 'pending')
                ->count(),
        ];
    }
}
