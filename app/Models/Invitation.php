<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'email', 'name', 'role',
        'team_id', 'token', 'invited_by_user_id',
        'expires_at', 'accepted_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public static function generateToken(): string
    {
        do { $t = Str::random(48); } while (self::where('token', $t)->exists());
        return $t;
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function team(): BelongsTo         { return $this->belongsTo(Team::class); }
    public function invitedBy(): BelongsTo    { return $this->belongsTo(User::class, 'invited_by_user_id'); }

    public function isExpired(): bool   { return $this->expires_at && now()->greaterThan($this->expires_at); }
    public function isAccepted(): bool  { return ! is_null($this->accepted_at); }
    public function isUsable(): bool    { return ! $this->isAccepted() && ! $this->isExpired(); }
}
