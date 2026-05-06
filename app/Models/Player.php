<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'season_id',
        'name', 'photo_url',
        'category', 'player_type', 'position',
        'base_price', 'is_old_player',
        'auction_status', 'sold_price', 'team_id',
        'jersey_no', 'batting_style', 'bowling_style',
        'profession', 'registration_txn_id', 'registered_at',
        'registration_data',
    ];

    protected $casts = [
        'base_price'        => 'integer',
        'sold_price'        => 'integer',
        'is_old_player'     => 'boolean',
        'registered_at'     => 'datetime',
        'registration_data' => 'array',
    ];

    public const CATEGORIES = ['Elite', 'Regular', 'New'];
    public const TYPES      = ['Old', 'New'];
    public const STATUSES   = ['pending', 'queue', 'live', 'sold', 'unsold'];

    public const POSITIONS_CRICKET  = ['Batter', 'Bowler', 'All-rounder', 'WK/Batter', 'Wicket-keeper'];
    public const POSITIONS_FOOTBALL = ['Goalkeeper', 'Defender', 'Midfielder', 'Striker'];

    public static function positionsFor(string $sport): array
    {
        return $sport === 'football' ? self::POSITIONS_FOOTBALL : self::POSITIONS_CRICKET;
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function season(): BelongsTo       { return $this->belongsTo(Season::class); }
    public function team(): BelongsTo         { return $this->belongsTo(Team::class); }
    public function bids(): HasMany           { return $this->hasMany(Bid::class); }
}
