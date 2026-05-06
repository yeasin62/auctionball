<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSubscriptionRenewalReminders extends Command
{
    protected $signature = 'subscriptions:send-renewal-reminders
                            {--days=7,3,1 : Comma-separated thresholds (in days before period_end)}
                            {--dry-run    : Preview without sending}';

    protected $description = 'Email pre-renewal reminders at configured day thresholds before period_end.';

    public function handle(): int
    {
        $thresholds = collect(explode(',', $this->option('days')))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0)
            ->sortDesc()
            ->values();

        if ($thresholds->isEmpty()) {
            $this->error('No valid day thresholds provided.');
            return self::FAILURE;
        }

        $dry  = (bool) $this->option('dry-run');
        $sent = 0;

        // For each threshold (largest first), find subs whose period_end falls in that window
        // and which haven't yet had a reminder for this or a tighter threshold.
        foreach ($thresholds as $days) {
            $windowStart = now()->addDays($days)->startOfDay();
            $windowEnd   = now()->addDays($days)->endOfDay();

            $subs = Subscription::with('organization')
                ->whereIn('status', ['active', 'past_due'])
                ->whereBetween('current_period_end', [$windowStart, $windowEnd])
                ->where(function ($q) use ($days) {
                    // Skip if we already sent THIS threshold or a tighter one
                    $q->whereNull('last_reminder_days_before')
                      ->orWhere('last_reminder_days_before', '>', $days);
                })
                ->get();

            foreach ($subs as $sub) {
                $admin = $sub->organization->users()->wherePivot('role', 'org_admin')->first();
                if (! $admin) {
                    $this->line("  · skip #{$sub->id} (no admin email)");
                    continue;
                }

                if ($dry) {
                    $this->line("  · DRY  T-{$days}d  → {$admin->email}  ({$sub->organization->name} / {$sub->plan})");
                    continue;
                }

                try {
                    Mail::to($admin->email)->send(new SubscriptionRenewalReminderMail($sub, $days));
                    $sub->update([
                        'last_reminder_sent_at'     => now(),
                        'last_reminder_days_before' => $days,
                    ]);
                    $this->line("  · SENT T-{$days}d  → {$admin->email}  ({$sub->organization->name} / {$sub->plan})");
                    $sent++;
                } catch (Throwable $e) {
                    $this->warn("  · FAIL T-{$days}d  → {$admin->email}: {$e->getMessage()}");
                }
            }
        }

        $this->info($dry
            ? "Dry run complete (no mail sent)."
            : "Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
