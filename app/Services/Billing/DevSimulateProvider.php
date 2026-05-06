<?php

namespace App\Services\Billing;

use App\Models\PaymentTransaction;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * In-memory "provider" used in dev when no real keys are set.
 * Lets us click through the entire flow without a sandbox account.
 *
 * Also implements SupportsRecurring so the renewal scheduler has something
 * to exercise end-to-end without real payment credentials.
 */
class DevSimulateProvider implements PaymentProvider, SupportsRecurring
{
    public function key(): string { return 'dev'; }

    public function createCheckout(PaymentTransaction $txn): array
    {
        return [
            'redirect_url'     => route('billing.callback', ['provider' => 'dev', 'ref' => $txn->local_ref]) . '?simulate=ok',
            'provider_txn_id'  => 'DEV-' . $txn->local_ref,
            'raw'              => ['note' => 'Dev simulator — instant success'],
        ];
    }

    public function verifyCallback(PaymentTransaction $txn, Request $request): array
    {
        return [
            'ok'              => $request->query('simulate') !== 'fail',
            'provider_txn_id' => 'DEV-' . $txn->local_ref,
            'raw'             => $request->query(),
            'error'           => $request->query('simulate') === 'fail' ? 'Simulated failure' : null,
        ];
    }

    public function verifyWebhook(Request $request): array
    {
        return ['ok' => true, 'provider_txn_id' => $request->input('provider_txn_id'), 'raw' => $request->all()];
    }

    public function chargeRecurring(Subscription $subscription): array
    {
        return [
            'ok'              => true,
            'provider_txn_id' => 'DEV-RENEW-' . Str::upper(Str::random(8)),
            'raw'             => ['note' => 'Dev simulator — auto-renew always succeeds'],
            'error'           => null,
        ];
    }
}
