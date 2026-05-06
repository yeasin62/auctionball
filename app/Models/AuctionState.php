<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionState extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'season_id',
        'current_player_id', 'highest_bid', 'highest_bidder_team_id',
        'status', 'timer_end', 'last_bid_at', 'timer_duration_seconds',
    ];

    protected $casts = [
        'highest_bid'             => 'integer',
        'timer_end'               => 'datetime',
        'last_bid_at'             => 'datetime',
        'timer_duration_seconds'  => 'integer',
    ];

    public const STATUSES = ['idle', 'running', 'paused', 'sold', 'unsold'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function season(): BelongsTo       { return $this->belongsTo(Season::class); }
    public function currentPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'current_player_id'); }
    public function highestBidder(): BelongsTo { return $this->belongsTo(Team::class, 'highest_bidder_team_id'); }
}
