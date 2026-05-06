<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'season_id', 'player_id', 'team_id',
        'amount', 'placed_at',
    ];

    protected $casts = [
        'amount'    => 'integer',
        'placed_at' => 'datetime',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function season(): BelongsTo       { return $this->belongsTo(Season::class); }
    public function player(): BelongsTo       { return $this->belongsTo(Player::class); }
    public function team(): BelongsTo         { return $this->belongsTo(Team::class); }
}
