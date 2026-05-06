<?php

namespace App\Jobs;

use App\Mail\SubscriptionDowngradedMail;
use App\Mail\SubscriptionRenewalDueMail;
use App\Mail\SubscriptionRenewalFailedMail;
use App\Mail\SubscriptionRenewedMail;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Services\Billing\PaymentService;
use App\Services\Billing\SupportsRecurring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Attempts to renew a single subscription. Designed to be:
 *  - idempotent (lock prevents concurrent runs for same sub)
 *  - safe to retry (each attempt creates its own PaymentTransaction row)
 *  - graceful in dunning (3 attempts, exponential cooldown, then downgrade)
 *
 * Dispatch from RunSubscriptionRenewals (the daily command) or manually
 * via the super-admin panel.
 */
class RenewSubscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Provider returns within ~30s normally; allow some headroom. */
    public int $timeout = 90;

    /** Job-level retries are 1; we manage attempts at the subscription level. */
    public int $tries = 1;

    public function __construct(public int $subscriptionId) {}

    public function handle(PaymentService $payments): void
    {
        // Lock so two concurrent workers don't double-charge the same sub.
        Cache::lock("renewal:sub:{$this->subscriptionId}", 120)->block(0, function () use ($payments) {
            /** @var Subscription|null $sub */
            $sub = Subscription::with('organization')->find($this->subscriptionId);
            if (! $sub) return;
            if (! $sub->isDueForRenewal()) {
                Log::info('renew.skip not_due', ['sub' => $sub->id, 'status' => $sub->status]);
                return;
            }

            $provider = $payments->for($sub->provider);
            $supportsRecurring = $provider instanceof SupportsRecurring;

            // Two cases land here in the manual-renewal path:
            //  1. The provider can't ever auto-charge (no SupportsRecurring impl)
            //  2. The original checkout was a one-off — no agreement was stored, so even a
            //     recurring-capable provider has nothing to charge against.
            if (! $supportsRecurring || ! $sub->is_recurring) {
                $this->sendRenewalDue($sub);
                $sub->update([
                    'last_attempt_at'      => now(),
                    'next_attempt_at'      => now()->addDays(2),
                    'last_failure_reason'  => 'provider_does_not_support_recurring',
                    'status'               => 'past_due',
                    'grace_until'          => $sub->grace_until ?? now()->addDays(Subscription::GRACE_DAYS),
                ]);
                return;
            }

            // Create the txn row up-front so callers can see it in the audit log even if the provider call hangs.
            $txn = PaymentTransaction::create([
                'organization_id'      => $sub->organization_id,
                'subscription_id'      => $sub->id,
                'initiated_by_user_id' => null,           // system
                'provider'             => $sub->provider,
                'local_ref'            => PaymentTransaction::generateLocalRef(),
                'plan'                 => $sub->plan,
                'billing_cycle'        => $sub->billing_cycle,
                'amount'               => $sub->amount,
                'currency'             => $sub->currency,
                'status'               => 'pending',
            ]);

            try {
                $result = $provider->chargeRecurring($sub);
            } catch (Throwable $e) {
                Log::error('renew.provider_threw', ['sub' => $sub->id, 'error' => $e->getMessage()]);
                $result = ['ok' => false, 'provider_txn_id' => null, 'raw' => [], 'error' => $e->getMessage()];
            }

            if ($result['ok']) {
                $this->markSuccess($sub, $txn, $result);
            } else {
                $this->markFailure($sub, $txn, $result['error'] ?? 'unknown');
            }
        });
    }

    private function markSuccess(Subscription $sub, PaymentTransaction $txn, array $result): void
    {
        DB::transaction(function () use ($sub, $txn, $result) {
            $cycleDays = $sub->billing_cycle === 'yearly' ? 365 : 30;

            // Anchor renewals to the previous period_end, not now() — so a delayed
            // dunning attempt doesn't shift the customer's billing date.
            $newStart = $sub->current_period_end ?? now();
            $newEnd   = $newStart->copy()->addDays($cycleDays);

            $txn->update([
                'status'          => 'completed',
                'provider_txn_id' => $result['provider_txn_id'] ?? $txn->provider_txn_id,
                'raw_payload'     => $result['raw'] ?? null,
                'completed_at'    => now(),
            ]);

            $sub->update([
                'status'                 => 'active',
                'current_period_start'   => $newStart,
                'current_period_end'     => $newEnd,
                'renewal_attempts'       => 0,
                'last_attempt_at'        => now(),
                'next_attempt_at'        => null,
                'grace_until'            => null,
                'last_failure_reason'    => null,
            ]);

            // Org plan stays the same — but ensure it's correct in case of drift.
            // `plan` isn't in $fillable (security), so use forceFill explicitly.
            $sub->organization->forceFill(['plan' => $sub->plan])->save();
        });

        $this->safeMail($sub, new SubscriptionRenewedMail($sub->fresh()));
        Log::info('renew.success', ['sub' => $sub->id, 'period_end' => $sub->fresh()->current_period_end?->toIso8601String()]);
    }

    private function markFailure(Subscription $sub, PaymentTransaction $txn, string $reason): void
    {
        $attempt = $sub->renewal_attempts + 1;

        $txn->update([
            'status'      => 'failed',
            'raw_payload' => ['error' => $reason],
        ]);

        if ($attempt >= Subscription::MAX_ATTEMPTS) {
            $this->downgrade($sub, $reason);
            return;
        }

        // Exponential cooldown: 1d, 2d, 4d
        $cooldownDays = 2 ** ($attempt - 1);

        $sub->update([
            'status'              => 'past_due',
            'renewal_attempts'    => $attempt,
            'last_attempt_at'     => now(),
            'next_attempt_at'     => now()->addDays($cooldownDays),
            'grace_until'         => $sub->grace_until ?? now()->addDays(Subscription::GRACE_DAYS),
            'last_failure_reason' => $reason,
        ]);

        $this->safeMail($sub, new SubscriptionRenewalFailedMail($sub->fresh(), $reason, $attempt));
        Log::warning('renew.failed', ['sub' => $sub->id, 'attempt' => $attempt, 'reason' => $reason]);
    }

    private function downgrade(Subscription $sub, string $reason): void
    {
        DB::transaction(function () use ($sub, $reason) {
            $sub->update([
                'status'              => 'expired',
                'renewal_attempts'    => Subscription::MAX_ATTEMPTS,
                'last_attempt_at'     => now(),
                'next_attempt_at'     => null,
                'last_failure_reason' => $reason,
            ]);
            $sub->organization->forceFill(['plan' => 'free'])->save();
        });

        $this->safeMail($sub, new SubscriptionDowngradedMail($sub->fresh(), $reason));
        Log::warning('renew.downgraded', ['sub' => $sub->id, 'reason' => $reason]);
    }

    private function sendRenewalDue(Subscription $sub): void
    {
        $this->safeMail($sub, new SubscriptionRenewalDueMail($sub));
    }

    private function safeMail(Subscription $sub, $mailable): void
    {
        $admin = $sub->organization->users()->wherePivot('role', 'org_admin')->first();
        if (! $admin) return;
        try {
            Mail::to($admin->email)->send($mailable);
        } catch (Throwable $e) {
            Log::warning('renew.mail_failed', ['sub' => $sub->id, 'error' => $e->getMessage()]);
        }
    }
}
