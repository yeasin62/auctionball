<?php

namespace App\Services\Billing;

use App\Models\Subscription;

/**
 * Optional capability — a provider implements this if it can server-charge
 * the customer's saved payment method (PayPal Subscriptions, bKash Agreement,
 * or in dev: the simulator). Without it, renewals fall back to "send the
 * customer a renewal-due email" mode.
 */
interface SupportsRecurring
{
    /**
     * Charge the customer for the next billing cycle using a previously-stored token/agreement.
     * Returns: ['ok' => bool, 'provider_txn_id' => ?string, 'raw' => array, 'error' => ?string]
     */
    public function chargeRecurring(Subscription $subscription): array;
}
