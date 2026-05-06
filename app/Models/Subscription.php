<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'plan', 'status',
        'provider', 'provider_subscription_id',
        'amount', 'currency', 'billing_cycle',
        'auto_renew', 'is_recurring', 'renewal_attempts',
        'last_attempt_at', 'next_attempt_at', 'grace_until', 'last_failure_reason',
        'last_reminder_sent_at', 'last_reminder_days_before',
        'current_period_start', 'current_period_end', 'canceled_at',
    ];

    protected $casts = [
        'amount'                    => 'integer',
        'auto_renew'                => 'boolean',
        'is_recurring'              => 'boolean',
        'renewal_attempts'          => 'integer',
        'last_reminder_days_before' => 'integer',
        'current_period_start'      => 'datetime',
        'current_period_end'        => 'datetime',
        'last_attempt_at'           => 'datetime',
        'next_attempt_at'           => 'datetime',
        'grace_until'               => 'datetime',
        'last_reminder_sent_at'     => 'datetime',
        'canceled_at'               => 'datetime',
    ];

    /** Subs in this state are considered "providing service". */
    public const ACTIVE_STATUSES = ['active', 'past_due'];

    /** Max consecutive renewal failures before we downgrade. */
    public const MAX_ATTEMPTS = 3;

    /** Days of grace after a failed renewal — service stays on, we keep retrying. */
    public const GRACE_DAYS = 7;

    public function organization(): BelongsTo  { return $this->belongsTo(Organization::class); }
    public function transactions(): HasMany    { return $this->hasMany(PaymentTransaction::class); }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true)
            && (! $this->current_period_end || now()->lessThan($this->current_period_end->copy()->addDays(self::GRACE_DAYS)));
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->current_period_end && now()->greaterThan($this->current_period_end->copy()->addDays(self::GRACE_DAYS)));
    }

    /** Eligible for renewal: active/past_due, auto_renew on, and within retry window or expired period. */
    public function isDueForRenewal(\DateTimeInterface $now = null): bool
    {
        $now = $now ?? now();
        if (! $this->auto_renew) return false;
        if (! in_array($this->status, ['active', 'past_due'], true)) return false;
        if (! $this->current_period_end) return false;

        // Period ended, in grace, retry not in cooldown
        $periodOver = $now >= $this->current_period_end;
        $retryReady = ! $this->next_attempt_at || $now >= $this->next_attempt_at;

        return $periodOver && $retryReady && $this->renewal_attempts < self::MAX_ATTEMPTS;
    }
}
