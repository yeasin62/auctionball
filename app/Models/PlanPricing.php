<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Editable pricing + per-plan limits, sourced from the plan_pricing table.
 * Super admin edits these rows; everything else (Organization::limits(),
 * Landing.vue prices, MRR estimate) reads through here.
 */
class PlanPricing extends Model
{
    protected $table = 'plan_pricing';

    protected $fillable = [
        'slug', 'price_bdt',
        'seasons_limit', 'players_limit', 'teams_limit',
        'watermark', 'export_csv', 'export_pdf',
        'sort_order',
    ];

    protected $casts = [
        'price_bdt'     => 'integer',
        'seasons_limit' => 'integer',
        'players_limit' => 'integer',
        'teams_limit'   => 'integer',
        'watermark'     => 'boolean',
        'export_csv'    => 'boolean',
        'export_pdf'    => 'boolean',
        'sort_order'    => 'integer',
    ];

    public const UNLIMITED = 999_999_999;

    /** All plans keyed by slug, request-cached. */
    public static function all_keyed(): array
    {
        return Cache::store('array')->rememberForever('plan_pricing.all', function () {
            return self::orderBy('sort_order')->get()->keyBy('slug')->all();
        });
    }

    /** Limits array shaped like the legacy PLAN_LIMITS map. */
    public static function limitsFor(string $slug): array
    {
        $row = self::all_keyed()[$slug] ?? null;

        if (! $row) {
            // Fallback to legacy constant if DB row missing for some reason.
            return Organization::PLAN_LIMITS[$slug] ?? Organization::PLAN_LIMITS['free'];
        }

        return [
            'seasons'    => (int) $row->seasons_limit,
            'players'    => (int) $row->players_limit,
            'teams'      => (int) $row->teams_limit,
            'watermark'  => (bool) $row->watermark,
            'export_csv' => (bool) $row->export_csv,
            'export_pdf' => (bool) $row->export_pdf,
        ];
    }

    /** Map of slug → BDT price (used by Landing.vue + MRR estimate). */
    public static function priceMap(): array
    {
        return collect(self::all_keyed())->mapWithKeys(fn ($p, $slug) => [$slug => (int) $p->price_bdt])->all();
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::store('array')->forget('plan_pricing.all'));
        static::deleted(fn () => Cache::store('array')->forget('plan_pricing.all'));
    }
}
