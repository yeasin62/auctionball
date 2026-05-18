<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    /**
     * `plan` is intentionally NOT in $fillable. It can only be set via explicit
     * assignment in trusted paths: registration (always 'free'), super-admin
     * `setPlan` action, and the post-payment plan flip in BillingController.
     * This prevents accidental mass-assignment in any future endpoint that
     * does `Organization::update($request->all())`.
     */
    protected $fillable = [
        'name', 'slug', 'logo_url', 'timezone', 'rules',
        'display_currency', 'bdt_per_usd',
        'custom_domain', 'custom_domain_verification_token', 'custom_domain_verified_at',
    ];

    protected $casts = [
        'bdt_per_usd'               => 'integer',
        'custom_domain_verified_at' => 'datetime',
    ];

    /** Plans allowed to use a custom (white-label) domain. */
    public const WHITE_LABEL_PLANS = ['pro', 'enterprise'];

    /**
     * White-label is active when:
     *   · plan is Pro/Enterprise, AND
     *   · a custom domain is configured AND has been DNS-verified.
     * Frontend uses this flag to drop "Powered by AuctionBall" branding.
     */
    public function isWhiteLabel(): bool
    {
        return in_array($this->plan, self::WHITE_LABEL_PLANS, true)
            && ! empty($this->custom_domain)
            && ! is_null($this->custom_domain_verified_at);
    }

    public const PLANS = ['free', 'starter', 'pro', 'enterprise'];

    /**
     * Hardcoded fallback if the plan_pricing table is empty (fresh install
     * before migration runs). Production reads PlanPricing::limitsFor() so
     * super-admin tweaks via /admin/plans take effect immediately.
     */
    public const PLAN_LIMITS = [
        'free'       => ['seasons' => 1,         'players' => 44,        'teams' => 4,        'watermark' => true,  'export_csv' => false, 'export_pdf' => false],
        'starter'    => ['seasons' => 3,         'players' => 100,       'teams' => 6,        'watermark' => false, 'export_csv' => true,  'export_pdf' => false],
        'pro'        => ['seasons' => PHP_INT_MAX, 'players' => PHP_INT_MAX, 'teams' => 10,           'watermark' => false, 'export_csv' => true, 'export_pdf' => true],
        'enterprise' => ['seasons' => PHP_INT_MAX, 'players' => PHP_INT_MAX, 'teams' => PHP_INT_MAX, 'watermark' => false, 'export_csv' => true, 'export_pdf' => true],
    ];

    public function limits(): array
    {
        return PlanPricing::limitsFor($this->plan);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'team_id', 'last_active_at'])
            ->withTimestamps();
    }

    public function seasons(): HasMany       { return $this->hasMany(Season::class); }
    public function teams(): HasMany         { return $this->hasMany(Team::class); }
    public function players(): HasMany       { return $this->hasMany(Player::class); }
    public function bids(): HasMany          { return $this->hasMany(Bid::class); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }

    public function activeSeason(): ?Season
    {
        return $this->seasons()->where('is_active', true)->first();
    }
}
