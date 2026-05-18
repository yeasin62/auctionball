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
        'registration_form_schema', 'player_categories',
        'team_registration_open', 'team_registration_token', 'team_registration_fee', 'team_registration_instructions',
    ];

    public const SPORTS = ['cricket', 'football'];

    public const DEFAULT_PLAYER_CATEGORIES = [
        ['name' => 'Elite',   'base_price' => 50000],
        ['name' => 'Regular', 'base_price' => 25000],
        ['name' => 'New',     'base_price' => 10000],
    ];

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
        'player_categories'        => 'array',
    ];

    /** Normalized list of {name, base_price}; falls back to defaults. */
    public function categoryList(): array
    {
        $raw = $this->player_categories;
        if (! is_array($raw) || empty($raw)) {
            return self::DEFAULT_PLAYER_CATEGORIES;
        }
        return $raw;
    }

    /** Just the names, used for Rule::in() validation and dropdowns. */
    public function categoryNames(): array
    {
        return array_values(array_filter(array_map(
            fn ($c) => is_array($c) ? ($c['name'] ?? null) : null,
            $this->categoryList(),
        )));
    }

    /** Default base price for a given category, or null if not configured. */
    public function basePriceForCategory(string $name): ?int
    {
        foreach ($this->categoryList() as $c) {
            if (($c['name'] ?? null) === $name) {
                return isset($c['base_price']) ? (int) $c['base_price'] : null;
            }
        }
        return null;
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function teams(): HasMany          { return $this->hasMany(Team::class); }
    public function players(): HasMany        { return $this->hasMany(Player::class); }
    public function bids(): HasMany           { return $this->hasMany(Bid::class); }
    public function auctionState(): HasOne    { return $this->hasOne(AuctionState::class); }
}
