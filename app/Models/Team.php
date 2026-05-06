<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'season_id', 'name', 'short_code',
        'owner_user_id', 'logo_url', 'owner_name',
        'registration_status', 'registration_txn_id',
        'initial_budget', 'remaining_budget',
        'device_token', 'registered_at',
    ];

    protected $casts = [
        'initial_budget'   => 'integer',
        'remaining_budget' => 'integer',
        'registered_at'    => 'datetime',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function season(): BelongsTo       { return $this->belongsTo(Season::class); }
    public function owner(): BelongsTo        { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function players(): HasMany        { return $this->hasMany(Player::class); }
    public function bids(): HasMany           { return $this->hasMany(Bid::class); }

    public function spent(): int
    {
        return (int) ($this->initial_budget - $this->remaining_budget);
    }
}
