<?php

namespace App\Services\Billing;

use App\Models\PaymentTransaction;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * bKash Tokenized Checkout — supports one-off and Agreement-based recurring billing.
 *
 * Required env: BKASH_USERNAME, BKASH_PASSWORD, BKASH_APP_KEY, BKASH_APP_SECRET, BKASH_MODE=sandbox|live
 *
 * Mode codes:
 *   0011 — one-off URL-based checkout (`createCheckout` default path)
 *   0000 — Agreement creation (customer approves once → permanent agreementID stored → we charge later)
 */
class BkashProvider implements PaymentProvider, SupportsRecurring
{
    public function key(): string { return 'bkash'; }

    private function base(): string
    {
        return env('BKASH_MODE', 'sandbox') === 'live'
            ? 'https://tokenized.pay.bka.sh/v1.2.0-beta'
            : 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';
    }

    private function idToken(): string
    {
        return Cache::remember('bkash:id_token', 50 * 60, function () {
            $resp = Http::withHeaders([
                'username'     => env('BKASH_USERNAME', ''),
                'password'     => env('BKASH_PASSWORD', ''),
                'Content-Type' => 'application/json',
            ])->post("{$this->base()}/tokenized/checkout/token/grant", [
                'app_key'    => env('BKASH_APP_KEY', ''),
                'app_secret' => env('BKASH_APP_SECRET', ''),
            ])->throw();

            return $resp->json('id_token');
        });
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => $this->idToken(),
            'X-APP-Key'     => env('BKASH_APP_KEY', ''),
            'Content-Type'  => 'application/json',
        ];
    }

    public function createCheckout(PaymentTransaction $txn): array
    {
        return $txn->is_recurring_setup
            ? $this->createAgreement($txn)
            : $this->createOneOff($txn);
    }

    private function createOneOff(PaymentTransaction $txn): array
    {
        $resp = Http::withHeaders($this->authHeaders())
            ->post("{$this->base()}/tokenized/checkout/create", [
                'mode'                  => '0011',
                'payerReference'        => (string) $txn->organization_id,
                'callbackURL'           => route('billing.callback', ['provider' => 'bkash', 'ref' => $txn->local_ref]),
                'amount'                => (string) $txn->amount,
                'currency'              => 'BDT',
                'intent'                => 'sale',
                'merchantInvoiceNumber' => $txn->local_ref,
            ])->throw();

        $body = $resp->json();
        return [
            'redirect_url'    => $body['bkashURL'] ?? null,
            'provider_txn_id' => $body['paymentID'] ?? null,
            'raw'             => $body,
        ];
    }

    /**
     * Step 1 of recurring setup: customer approves the agreement once.
     * We get an `agreementID` we can charge later without further user interaction.
     */
    private function createAgreement(PaymentTransaction $txn): array
    {
        $resp = Http::withHeaders($this->authHeaders())
            ->post("{$this->base()}/tokenized/checkout/create", [
                'mode'                  => '0000',          // agreement creation
                'payerReference'        => (string) $txn->organization_id,
                'callbackURL'           => route('billing.callback', ['provider' => 'bkash', 'ref' => $txn->local_ref]),
                'amount'                => (string) $txn->amount,
                'currency'              => 'BDT',
                'intent'                => 'authorization',
                'merchantInvoiceNumber' => $txn->local_ref,
            ])->throw();

        $body = $resp->json();
        return [
            'redirect_url'    => $body['bkashURL'] ?? null,
            'provider_txn_id' => $body['paymentID'] ?? null,    // becomes agreementID after execute
            'raw'             => $body,
        ];
    }

    public function verifyCallback(PaymentTransaction $txn, Request $request): array
    {
        $paymentId = $request->query('paymentID') ?? $txn->provider_txn_id;
        $status    = $request->query('status');

        if ($status !== 'success' || ! $paymentId) {
            return ['ok' => false, 'provider_txn_id' => $paymentId, 'raw' => $request->query(), 'error' => "bKash status: {$status}"];
        }

        try {
            $endpoint = $txn->is_recurring_setup
                ? '/tokenized/checkout/agreement/execute'
                : '/tokenized/checkout/execute';

            $resp = Http::withHeaders($this->authHeaders())
                ->post("{$this->base()}{$endpoint}", ['paymentID' => $paymentId])->throw();

            $body = $resp->json();
            $ok = $txn->is_recurring_setup
                ? (! empty($body['agreementID']) && ($body['agreementStatus'] ?? '') === 'Completed')
                : (($body['transactionStatus'] ?? '') === 'Completed');

            // For recurring setup the stored id is agreementID, not the one-off paymentID
            $providerTxnId = $txn->is_recurring_setup
                ? ($body['agreementID'] ?? $paymentId)
                : ($body['trxID'] ?? $paymentId);

            return [
                'ok'              => $ok,
                'provider_txn_id' => $providerTxnId,
                'raw'             => $body,
                'error'           => $ok ? null : "bKash exec status: " . ($body['transactionStatus'] ?? $body['agreementStatus'] ?? 'unknown'),
            ];
        } catch (\Throwable $e) {
            Log::error('bKash execute failed', ['paymentId' => $paymentId, 'error' => $e->getMessage()]);
            return ['ok' => false, 'provider_txn_id' => $paymentId, 'raw' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Renewal: use the stored agreementID to debit the customer without redirecting them.
     * Two-step like the one-off flow: agreement-payment/create → agreement-payment/execute.
     */
    public function chargeRecurring(Subscription $subscription): array
    {
        $agreementId = $subscription->provider_subscription_id;
        if (! $agreementId) {
            return ['ok' => false, 'provider_txn_id' => null, 'raw' => [], 'error' => 'No bKash agreement id stored.'];
        }

        try {
            $createResp = Http::withHeaders($this->authHeaders())
                ->post("{$this->base()}/tokenized/checkout/agreement-payment/create", [
                    'agreementID'           => $agreementId,
                    'payerReference'        => (string) $subscription->organization_id,
                    'amount'                => (string) $subscription->amount,
                    'currency'              => $subscription->currency,
                    'intent'                => 'sale',
                    'merchantInvoiceNumber' => 'RENEW-' . $subscription->id . '-' . now()->format('Ymd'),
                ])->throw();

            $createBody = $createResp->json();
            $paymentId  = $createBody['paymentID'] ?? null;
            if (! $paymentId) {
                return ['ok' => false, 'provider_txn_id' => $agreementId, 'raw' => $createBody, 'error' => 'No paymentID returned'];
            }

            $execResp = Http::withHeaders($this->authHeaders())
                ->post("{$this->base()}/tokenized/checkout/agreement-payment/execute", ['paymentID' => $paymentId])
                ->throw();

            $execBody = $execResp->json();
            $ok = ($execBody['transactionStatus'] ?? '') === 'Completed';

            return [
                'ok'              => $ok,
                'provider_txn_id' => $execBody['trxID'] ?? $paymentId,
                'raw'             => $execBody,
                'error'           => $ok ? null : 'bKash exec status: ' . ($execBody['transactionStatus'] ?? 'unknown'),
            ];
        } catch (\Throwable $e) {
            Log::error('bKash recurring charge failed', ['agreement' => $agreementId, 'error' => $e->getMessage()]);
            return ['ok' => false, 'provider_txn_id' => $agreementId, 'raw' => [], 'error' => $e->getMessage()];
        }
    }

    public function verifyWebhook(Request $request): array
    {
        return [
            'ok'              => $request->input('transactionStatus') === 'Completed',
            'provider_txn_id' => $request->input('trxID') ?? $request->input('paymentID'),
            'raw'             => $request->all(),
        ];
    }
}
