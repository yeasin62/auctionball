<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    /** No updated_at — audit rows are append-only by design. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'user_id', 'actor_name', 'event',
        'subject_type', 'subject_id', 'summary', 'payload',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
}
