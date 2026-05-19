<?php

namespace App\Http\Controllers;

use App\Events\PendingPaymentsChanged;
use App\Models\Organization;
use App\Models\PaymentTransaction;
use App\Models\PlatformSettings;
use App\Models\Subscription;
use App\Services\Billing\PaymentService;
use App\Services\Billing\PlanCatalog;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class BillingController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): InertiaResponse
    {
        /** @var Organization $org */
        $org    = $request->attributes->get('current_organization');
        $season = $org->activeSeason();

        $sub = Subscription::where('organization_id', $org->id)
            ->whereIn('status', ['active', 'past_due'])
            ->latest()->first()
            ?? Subscription::where('organization_id', $org->id)->latest()->first();

        // Pending manual bKash submission — shown as a banner so the customer
        // knows their TrxID is in the queue (otherwise they'd think nothing happened).
        $pendingBkash = PaymentTransaction::where('organization_id', $org->id)
            ->where('provider', 'bkash')
            ->where('status', 'pending')
            ->latest()->first();

        $txns = PaymentTransaction::where('organization_id', $org->id)
            ->latest()->limit(10)->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'local_ref'  => $t->local_ref,
                'provider'   => $t->provider,
                'plan'       => $t->plan,
                'amount'     => $t->amount,
                'currency'   => $t->currency,
                'status'     => $t->status,
                'created_at' => $t->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Dashboard/Billing/Index', [
            'org'   => ['name' => $org->name, 'plan' => $org->plan, 'limits' => $org->limits()],
            'usage' => [
                'seasons' => $org->seasons()->count(),
                'players' => $season ? $season->players()->count() : 0,
                'teams'   => $season ? $season->teams()->count()   : 0,
            ],
            'plans'           => PlanCatalog::allPaid(),
            'subscription'    => $sub ? [
                'id'                => $sub->id,
                'plan'              => $sub->plan,
                'status'            => $sub->status,
                'provider'          => $sub->provider,
                'until'             => $sub->current_period_end?->format('Y-m-d'),
                'auto_renew'        => $sub->auto_renew,
                'renewal_attempts'  => $sub->renewal_attempts,
                'last_attempt_at'   => $sub->last_attempt_at?->format('Y-m-d H:i'),
                'next_attempt_at'   => $sub->next_attempt_at?->format('Y-m-d H:i'),
                'grace_until'       => $sub->grace_until?->format('Y-m-d'),
                'last_failure'      => $sub->last_failure_reason,
            ] : null,
            'transactions'    => $txns,
            'pending_bkash'   => $pendingBkash ? [
                'plan'        => $pendingBkash->plan,
                'amount'      => $pendingBkash->amount,
                'trx_id'      => $pendingBkash->provider_txn_id,
                'submitted_at'=> $pendingBkash->created_at?->format('Y-m-d H:i'),
            ] : null,
            'providers'       => $this->payments->availability(),
            'bkash_manual'    => [
                'merchant_number'     => PlatformSettings::current()->bkash_merchant_number,
                'account_type'        => PlatformSettings::current()->bkash_account_type,
                'instructions'        => PlatformSettings::current()->bkash_instructions,
                'manual_review_hours' => PlatformSettings::current()->manual_review_hours,
            ],
        ]);
    }

    /**
     * Manual bKash flow: customer sends money to our merchant number, then
     * pastes the TrxID here. We record a *pending* transaction; super admin
     * verifies and approves on /admin/payments. Plan only activates after approval.
     */
    public function bkashManualSubmit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan'          => ['required', Rule::in(['starter', 'pro', 'enterprise'])],
            'trx_id'        => 'required|string|min:6|max:32',
            // Bangladeshi mobile numbers are 11 digits ("01XXXXXXXXX") but accept
            // a few common formats — bare digits, with country code, or with
            // dashes/spaces. Strip non-digits before storing.
            'sender_number' => 'required|string|max:32|regex:/^[0-9+\-\s]+$/',
        ], [
            'sender_number.regex' => 'Enter a valid phone number (digits only, optional + and spaces).',
        ]);

        // Normalize: strip non-digits and drop a leading "88" country code if
        // present, so admin comparison against bKash records is one canonical form.
        $senderDigits = preg_replace('/\D+/', '', $data['sender_number']);
        if (str_starts_with($senderDigits, '88') && strlen($senderDigits) === 13) {
            $senderDigits = substr($senderDigits, 2);
        }

        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');

        // Reject duplicate TrxIDs — bKash IDs are globally unique.
        if (PaymentTransaction::where('provider', 'bkash')->where('provider_txn_id', $data['trx_id'])->exists()) {
            return back()->with('error', 'This bKash transaction ID has already been submitted.');
        }

        $txn = PaymentTransaction::create([
            'organization_id'      => $org->id,
            'initiated_by_user_id' => Auth::id(),
            'provider'             => 'bkash',
            'local_ref'            => PaymentTransaction::generateLocalRef(),
            'provider_txn_id'      => $data['trx_id'],
            'sender_bkash_number'  => $senderDigits,
            'plan'                 => $data['plan'],
            'billing_cycle'        => 'monthly',
            'is_recurring_setup'   => false,
            'amount'               => PlanCatalog::priceFor($data['plan'], 'BDT'),
            'currency'             => 'BDT',
            'status'               => 'pending',
            'raw_payload'          => ['manual' => true, 'trx_id' => $data['trx_id'], 'sender' => $senderDigits],
        ]);

        Audit::log(
            'payment.manual_submitted',
            "bKash manual payment submitted: ৳{$txn->amount} for {$txn->plan} (TrxID {$data['trx_id']}, sender {$senderDigits})",
            ['plan' => $txn->plan, 'amount' => $txn->amount, 'trx_id' => $data['trx_id'], 'sender' => $senderDigits],
            $txn,
        );

        // Wake any super admin currently on a dashboard page so their sidebar
        // badge bumps without waiting for a navigation.
        broadcast(new PendingPaymentsChanged());

        $hours = PlatformSettings::current()->manual_review_hours;
        return redirect()->route('dashboard.billing.index')
            ->with('success', "Thank you! We've received your bKash payment. Your account will be activated within {$hours} hours after we verify the transaction.");
    }

    /** Step 1: org admin picks plan + provider → create txn → redirect to provider. */
    public function checkout(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $data = $request->validate([
            'plan'       => ['required', Rule::in(['starter', 'pro', 'enterprise'])],
            'provider'   => ['required', Rule::in(['paypal'])],   // bKash routes through bkashManualSubmit
            'auto_renew' => 'sometimes|boolean',
        ]);

        /** @var Organization $org */
        $org       = $request->attributes->get('current_organization');
        $currency  = $data['provider'] === 'paypal' ? 'USD' : 'BDT';
        $requestedAutoRenew = ! array_key_exists('auto_renew', $data) || (bool) $data['auto_renew'];
        $autoRenew = $requestedAutoRenew && filled(config("services.paypal.plans.{$data['plan']}"));

        $txn = PaymentTransaction::create([
            'organization_id'      => $org->id,
            'initiated_by_user_id' => Auth::id(),
            'provider'             => $data['provider'],
            'local_ref'            => PaymentTransaction::generateLocalRef(),
            'plan'                 => $data['plan'],
            'billing_cycle'        => 'monthly',
            'is_recurring_setup'   => $autoRenew,
            'amount'               => PlanCatalog::priceFor($data['plan'], $currency),
            'currency'             => $currency,
            'status'               => 'pending',
        ]);

        try {
            $providerImpl = $this->payments->for($data['provider']);
            $result = $providerImpl->createCheckout($txn);
        } catch (\Throwable $e) {
            $txn->update(['status' => 'failed', 'raw_payload' => ['error' => $e->getMessage()]]);
            return redirect()->route('dashboard.billing.index')
                ->with('error', "Payment provider error: {$e->getMessage()}");
        }

        $txn->update([
            'provider_txn_id' => $result['provider_txn_id'] ?? null,
            'raw_payload'     => $result['raw'] ?? null,
        ]);

        if (empty($result['redirect_url'])) {
            return redirect()->route('dashboard.billing.index')->with('error', 'Provider did not return a redirect URL.');
        }

        return Inertia::location($result['redirect_url']);
    }

    /** Step 2: provider redirects user back here. Verify + activate plan. */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $ref = $request->query('ref');
        $txn = PaymentTransaction::where('local_ref', $ref)->where('provider', $provider)->first();

        if (! $txn) {
            return redirect()->route('login')->with('error', 'Payment session not found.');
        }

        // Idempotent: if already completed, just send to billing page
        if ($txn->isCompleted()) {
            return redirect()->route('dashboard.billing.index')->with('success', 'Payment already processed.');
        }

        try {
            $result = $this->payments->for($provider)->verifyCallback($txn, $request);
        } catch (\Throwable $e) {
            $txn->update(['status' => 'failed', 'raw_payload' => ['error' => $e->getMessage()]]);
            return redirect()->route('dashboard.billing.index')->with('error', "Verification failed: {$e->getMessage()}");
        }

        if (! $result['ok']) {
            $txn->update(['status' => 'failed', 'raw_payload' => $result['raw'] ?? null]);
            return redirect()->route('dashboard.billing.index')->with('error', $result['error'] ?? 'Payment was not completed.');
        }

        DB::transaction(function () use ($txn, $result) {
            $txn->update([
                'status'          => 'completed',
                'provider_txn_id' => $result['provider_txn_id'] ?? $txn->provider_txn_id,
                'raw_payload'     => $result['raw'] ?? null,
                'completed_at'    => now(),
            ]);

            $sub = Subscription::create([
                'organization_id'          => $txn->organization_id,
                'plan'                     => $txn->plan,
                'status'                   => 'active',
                'provider'                 => $txn->provider,
                'provider_subscription_id' => $txn->provider_txn_id,
                'is_recurring'             => (bool) $txn->is_recurring_setup,
                'auto_renew'               => (bool) $txn->is_recurring_setup,
                'amount'                   => $txn->amount,
                'currency'                 => $txn->currency,
                'billing_cycle'            => $txn->billing_cycle,
                'current_period_start'     => now(),
                'current_period_end'       => now()->addMonth(),
            ]);

            $txn->update(['subscription_id' => $sub->id]);
            // `plan` is not in $fillable (security) — forceFill on trusted path.
            $txn->organization->forceFill(['plan' => $txn->plan])->save();
        });

        Audit::log(
            'payment.completed',
            "Plan upgraded to {$txn->plan} via " . ucfirst($txn->provider) . " for {$txn->currency} " . number_format($txn->amount),
            ['provider' => $txn->provider, 'plan' => $txn->plan, 'amount' => $txn->amount, 'currency' => $txn->currency, 'ref' => $txn->local_ref],
            $txn,
        );

        return redirect()->route('dashboard.billing.index')
            ->with('success', "Plan upgraded to {$txn->plan}. Payment ref: {$txn->local_ref}");
    }

    /** Toggle auto-renew on the org's active subscription. */
    public function toggleAutoRenew(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        $sub = Subscription::where('organization_id', $org->id)
            ->whereIn('status', ['active', 'past_due'])
            ->latest()->first();

        if (! $sub) return back()->with('error', 'No active subscription to update.');

        $sub->update(['auto_renew' => ! $sub->auto_renew]);
        return back()->with('success', $sub->auto_renew ? 'Auto-renew turned ON.' : 'Auto-renew turned OFF — you can renew manually before period end.');
    }

    /** Cancel the subscription — stays active till period_end then expires. */
    public function cancel(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        $sub = Subscription::where('organization_id', $org->id)
            ->whereIn('status', ['active', 'past_due'])
            ->latest()->first();

        if (! $sub) return back()->with('error', 'No active subscription to cancel.');

        $sub->update([
            'auto_renew'  => false,
            'canceled_at' => now(),
        ]);

        return back()->with('success', "Subscription canceled. You'll keep {$sub->plan} access until {$sub->current_period_end?->format('F j, Y')}.");
    }

    /** Manually trigger a renewal attempt now (useful when auto-renew failed and user updated their payment method). */
    public function renewNow(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        $sub = Subscription::where('organization_id', $org->id)
            ->whereIn('status', ['active', 'past_due'])
            ->latest()->first();

        if (! $sub) return back()->with('error', 'No subscription to renew.');

        // Reset cooldown so the job will pick it up
        $sub->update(['next_attempt_at' => null]);
        \App\Jobs\RenewSubscriptionJob::dispatch($sub->id);

        return back()->with('success', 'Renewal queued — check back in a moment.');
    }

    /** Step 3 (optional): server-to-server webhook. Idempotent. */
    public function webhook(Request $request, string $provider): Response
    {
        try {
            $result = $this->payments->for($provider)->verifyWebhook($request);
        } catch (\Throwable $e) {
            return response('error', 500);
        }

        if (! $result['ok'] || empty($result['provider_txn_id'])) {
            return response('ignored', 200);
        }

        $txn = PaymentTransaction::where('provider', $provider)
            ->where('provider_txn_id', $result['provider_txn_id'])
            ->first();
        if (! $txn || $txn->isCompleted()) return response('ok', 200);

        DB::transaction(function () use ($txn, $result) {
            $txn->update([
                'status'       => 'completed',
                'raw_payload'  => $result['raw'] ?? null,
                'completed_at' => now(),
            ]);
            $txn->organization->forceFill(['plan' => $txn->plan])->save();
        });

        return response('ok', 200);
    }
}
