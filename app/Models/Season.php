<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'name', 'year', 'sport', 'status',
        'budget_per_team', 'bid_increment', 'bid_increment_usd', 'auto_finalize', 'start_date', 'end_date', 'is_active',
        'registration_open', 'registration_token', 'registration_fee', 'registration_instructions',
        'registration_form_schema',
        'team_registration_open', 'team_registration_token', 'team_registration_fee', 'team_registration_instructions',
    ];

    public const SPORTS = ['cricket', 'football'];

    protected $casts = [
        'start_date'               => 'date',
        'end_date'                 => 'date',
        'is_active'                => 'boolean',
        'auto_finalize'            => 'boolean',
        'registration_open'        => 'boolean',
        'team_registration_open'   => 'boolean',
        'budget_per_team'          => 'integer',
        'bid_increment'            => 'integer',
        'bid_increment_usd'        => 'integer',
        'registration_fee'         => 'integer',
        'team_registration_fee'    => 'integer',
        'year'                     => 'integer',
        'registration_form_schema' => 'array',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function teams(): HasMany          { return $this->hasMany(Team::class); }
    public function players(): HasMany        { return $this->hasMany(Player::class); }
    public function bids(): HasMany           { return $this->hasMany(Bid::class); }
    public function auctionState(): HasOne    { return $this->hasOne(AuctionState::class); }
}
