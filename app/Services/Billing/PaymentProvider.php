<?php

namespace App\Services\Billing;

use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

interface PaymentProvider
{
    public function key(): string;

    /**
     * Create the provider-side order/payment for a pending transaction.
     * Returns: ['redirect_url' => string, 'provider_txn_id' => ?string, 'raw' => array]
     */
    public function createCheckout(PaymentTransaction $txn): array;

    /**
     * Verify a callback (user-return URL hit) and capture funds if needed.
     * Returns: ['ok' => bool, 'provider_txn_id' => ?string, 'raw' => array, 'error' => ?string]
     */
    public function verifyCallback(PaymentTransaction $txn, Request $request): array;

    /**
     * Verify a server-to-server webhook (idempotent — may be called multiple times for the same event).
     * Returns: ['ok' => bool, 'provider_txn_id' => ?string, 'raw' => array]
     */
    public function verifyWebhook(Request $request): array;
}
