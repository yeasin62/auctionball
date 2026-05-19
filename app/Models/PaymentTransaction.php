<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'subscription_id', 'initiated_by_user_id',
        'provider', 'provider_txn_id', 'sender_bkash_number', 'local_ref',
        'plan', 'billing_cycle', 'is_recurring_setup',
        'amount', 'currency', 'status',
        'raw_payload', 'completed_at',
    ];

    protected $casts = [
        'amount'              => 'integer',
        'is_recurring_setup'  => 'boolean',
        'raw_payload'         => 'array',
        'completed_at'        => 'datetime',
    ];

    public static function generateLocalRef(): string
    {
        do { $r = 'AB-' . strtoupper(Str::random(12)); } while (self::where('local_ref', $r)->exists());
        return $r;
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }

    public function isCompleted(): bool { return $this->status === 'completed'; }
}
