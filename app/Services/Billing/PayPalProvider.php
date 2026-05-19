<?php

namespace App\Services\Billing;

use App\Models\PaymentTransaction;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayPal — supports both one-off Orders v2 and recurring Subscriptions.
 *
 * Required env (always):
 *   PAYPAL_CLIENT_ID, PAYPAL_SECRET, PAYPAL_MODE=sandbox|live
 *
 * Required env (only for recurring billing — pre-create plan resources in PayPal):
 *   PAYPAL_PLAN_STARTER, PAYPAL_PLAN_PRO, PAYPAL_PLAN_ENTERPRISE  (PayPal billing-plan ids)
 *
 * Currency: PayPal does not accept BDT, so amounts use USD equivalents from PlanCatalog.
 */
class PayPalProvider implements PaymentProvider, SupportsRecurring
{
    public function key(): string { return 'paypal'; }

    private function base(): string
    {
        return config('services.paypal.mode', 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function accessToken(): string
    {
        return Cache::remember('paypal:access_token', 8 * 60, function () {
            $resp = Http::withBasicAuth(
                config('services.paypal.client_id', ''),
                config('services.paypal.secret', '')
            )
                ->asForm()
                ->post("{$this->base()}/v1/oauth2/token", ['grant_type' => 'client_credentials'])
                ->throw();
            return $resp->json('access_token');
        });
    }

    public function createCheckout(PaymentTransaction $txn): array
    {
        // Recurring setup uses Subscriptions API, one-offs use Orders v2.
        return $txn->is_recurring_setup
            ? $this->createSubscription($txn)
            : $this->createOrder($txn);
    }

    private function createOrder(PaymentTransaction $txn): array
    {
        $resp = Http::withToken($this->accessToken())
            ->post("{$this->base()}/v2/checkout/orders", [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $txn->local_ref,
                    'description'  => "AuctionBall {$txn->plan} plan ({$txn->billing_cycle})",
                    'amount' => [
                        'currency_code' => $txn->currency,
                        'value'         => number_format($txn->amount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => route('billing.callback', ['provider' => 'paypal', 'ref' => $txn->local_ref]),
                    'cancel_url' => route('dashboard.billing.index') . '?cancelled=1',
                    'brand_name' => 'AuctionBall',
                    'user_action'=> 'PAY_NOW',
                ],
            ])
            ->throw();

        $body = $resp->json();
        $approve = collect($body['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'redirect_url'    => $approve,
            'provider_txn_id' => $body['id'] ?? null,
            'raw'             => $body,
        ];
    }

    /**
     * Create a PayPal Subscription against a pre-configured Plan. The customer approves once;
     * PayPal then auto-charges every month and notifies us via the BILLING.SUBSCRIPTION.PAYMENT.SUCCEEDED webhook.
     */
    private function createSubscription(PaymentTransaction $txn): array
    {
        $planId = config("services.paypal.plans.{$txn->plan}");
        if (! $planId) {
            throw new \RuntimeException("PayPal plan id missing — set PAYPAL_PLAN_" . strtoupper($txn->plan) . " in .env after creating the plan in PayPal.");
        }

        $resp = Http::withToken($this->accessToken())
            ->post("{$this->base()}/v1/billing/subscriptions", [
                'plan_id' => $planId,
                'custom_id' => $txn->local_ref,
                'application_context' => [
                    'brand_name'  => 'AuctionBall',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'return_url'  => route('billing.callback', ['provider' => 'paypal', 'ref' => $txn->local_ref]),
                    'cancel_url'  => route('dashboard.billing.index') . '?cancelled=1',
                    'shipping_preference' => 'NO_SHIPPING',
                ],
            ])
            ->throw();

        $body = $resp->json();
        $approve = collect($body['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'redirect_url'    => $approve,
            'provider_txn_id' => $body['id'] ?? null,   // PayPal Subscription id, e.g. I-BW452GLLEP1G
            'raw'             => $body,
        ];
    }

    public function verifyCallback(PaymentTransaction $txn, Request $request): array
    {
        if ($txn->is_recurring_setup) {
            return $this->verifySubscriptionApproval($txn, $request);
        }

        $orderId = $request->query('token') ?? $txn->provider_txn_id;
        if (! $orderId) return ['ok' => false, 'provider_txn_id' => null, 'raw' => [], 'error' => 'Missing PayPal order id'];

        try {
            $resp = Http::withToken($this->accessToken())
                ->post("{$this->base()}/v2/checkout/orders/{$orderId}/capture")
                ->throw();
            $body = $resp->json();
            $status = $body['status'] ?? '';
            return [
                'ok'              => $status === 'COMPLETED',
                'provider_txn_id' => $orderId,
                'raw'             => $body,
                'error'           => $status === 'COMPLETED' ? null : "PayPal status: {$status}",
            ];
        } catch (\Throwable $e) {
            Log::error('PayPal capture failed', ['order' => $orderId, 'error' => $e->getMessage()]);
            return ['ok' => false, 'provider_txn_id' => $orderId, 'raw' => [], 'error' => $e->getMessage()];
        }
    }

    private function verifySubscriptionApproval(PaymentTransaction $txn, Request $request): array
    {
        // PayPal returns ?subscription_id=I-XXXX after approval. We confirm status=ACTIVE.
        $subId = $request->query('subscription_id') ?? $txn->provider_txn_id;
        if (! $subId) return ['ok' => false, 'provider_txn_id' => null, 'raw' => [], 'error' => 'Missing subscription id'];

        try {
            $resp = Http::withToken($this->accessToken())
                ->get("{$this->base()}/v1/billing/subscriptions/{$subId}")->throw();
            $body = $resp->json();
            $status = $body['status'] ?? '';
            return [
                'ok'              => in_array($status, ['ACTIVE', 'APPROVED'], true),
                'provider_txn_id' => $subId,
                'raw'             => $body,
                'error'           => in_array($status, ['ACTIVE', 'APPROVED'], true) ? null : "Subscription status: {$status}",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'provider_txn_id' => $subId, 'raw' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * For PayPal Subscriptions, PayPal does the actual charging on its own schedule.
     * This implementation polls the subscription's last billing date — if it's within the
     * current cycle window, we treat the renewal as successful and just extend our period_end.
     */
    public function chargeRecurring(Subscription $subscription): array
    {
        $subId = $subscription->provider_subscription_id;
        if (! $subId) {
            return ['ok' => false, 'provider_txn_id' => null, 'raw' => [], 'error' => 'No PayPal subscription id stored.'];
        }

        try {
            $resp = Http::withToken($this->accessToken())
                ->get("{$this->base()}/v1/billing/subscriptions/{$subId}")->throw();
            $body = $resp->json();
            $status = $body['status'] ?? '';

            if ($status !== 'ACTIVE') {
                return ['ok' => false, 'provider_txn_id' => $subId, 'raw' => $body, 'error' => "PayPal subscription status: {$status}"];
            }

            // Confirm a recent successful billing landed for this period.
            $lastTimeStr = $body['billing_info']['last_payment']['time'] ?? null;
            if ($lastTimeStr) {
                $lastTime = Carbon::parse($lastTimeStr);
                if ($lastTime->greaterThanOrEqualTo($subscription->current_period_end)) {
                    return ['ok' => true, 'provider_txn_id' => $subId, 'raw' => $body, 'error' => null];
                }
            }

            return ['ok' => false, 'provider_txn_id' => $subId, 'raw' => $body, 'error' => 'No new payment recorded yet — webhook may be delayed.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'provider_txn_id' => $subId, 'raw' => [], 'error' => $e->getMessage()];
        }
    }

    public function verifyWebhook(Request $request): array
    {
        if (! $this->verifyWebhookSignature($request)) {
            return ['ok' => false, 'provider_txn_id' => null, 'raw' => $request->all()];
        }

        $resource = $request->input('resource', []);

        // Recurring renewal payments arrive as PAYMENT.SALE.COMPLETED with billing_agreement_id (= subscription id)
        $providerTxnId = $resource['billing_agreement_id']
            ?? $resource['supplementary_data']['related_ids']['order_id']
            ?? $resource['id']
            ?? null;

        return [
            'ok'              => (bool) $providerTxnId,
            'provider_txn_id' => $providerTxnId,
            'raw'             => $request->all(),
        ];
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = config('services.paypal.webhook_id');
        if (! $webhookId) {
            Log::warning('PayPal webhook rejected: PAYPAL_WEBHOOK_ID is not configured');
            return false;
        }

        try {
            $resp = Http::withToken($this->accessToken())
                ->post("{$this->base()}/v1/notifications/verify-webhook-signature", [
                    'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
                    'cert_url'          => $request->header('PAYPAL-CERT-URL'),
                    'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
                    'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
                    'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                    'webhook_id'        => $webhookId,
                    'webhook_event'     => $request->all(),
                ])
                ->throw();

            return $resp->json('verification_status') === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::warning('PayPal webhook signature verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
