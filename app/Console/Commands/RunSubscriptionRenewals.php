<?php

namespace App\Console\Commands;

use App\Jobs\RenewSubscriptionJob;
use App\Models\Subscription;
use Illuminate\Console\Command;

class RunSubscriptionRenewals extends Command
{
    protected $signature = 'subscriptions:run-renewals
                            {--id=    : Run only for this subscription id}
                            {--force  : Ignore the period_end / next_attempt_at gate (use with --id only)}
                            {--sync   : Run inline instead of dispatching to the queue}';

    protected $description = 'Find subscriptions due for renewal and dispatch RenewSubscriptionJob for each.';

    public function handle(): int
    {
        $query = Subscription::query()->where('auto_renew', true);

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        } else {
            $query->whereIn('status', ['active', 'past_due'])
                ->where('current_period_end', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                })
                ->where('renewal_attempts', '<', Subscription::MAX_ATTEMPTS);
        }

        $subs = $query->get();

        if ($subs->isEmpty()) {
            $this->line('Nothing due. ✓');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $sync  = (bool) $this->option('sync');

        $this->info(sprintf(
            "Found %d subscription(s) due. Mode: %s",
            $subs->count(),
            $sync ? 'sync (run now)' : 'queue (dispatch)'
        ));

        foreach ($subs as $sub) {
            if (! $force && ! $sub->isDueForRenewal()) {
                $this->line("  · skip #{$sub->id} (not due)");
                continue;
            }

            if ($force) {
                // Pretend the period just ended so isDueForRenewal() returns true inside the job
                $sub->forceFill(['current_period_end' => now()->subSecond(), 'next_attempt_at' => null])->save();
            }

            if ($sync) {
                (new RenewSubscriptionJob($sub->id))->handle(app(\App\Services\Billing\PaymentService::class));
            } else {
                RenewSubscriptionJob::dispatch($sub->id);
            }

            $this->line("  · #{$sub->id} ({$sub->organization?->name}) — {$sub->plan} via {$sub->provider}");
        }

        return self::SUCCESS;
    }
}
