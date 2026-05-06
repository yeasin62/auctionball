<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Singleton row of platform-wide editable settings.
 * Read via PlatformSettings::current(); cached in-request.
 * Write by saving the model — the saved hook busts the cache.
 */
class PlatformSettings extends Model
{
    protected $table = 'platform_settings';

    protected $fillable = [
        'app_domain',
        'app_logo_url',
        'bkash_merchant_number',
        'bkash_account_type',
        'bkash_instructions',
        'manual_review_hours',
    ];

    protected $casts = [
        'manual_review_hours' => 'integer',
    ];

    public static function current(): self
    {
        return Cache::store('array')->rememberForever('platform_settings.current', function () {
            return self::firstOrCreate([], [
                'app_domain'            => 'auctionball.com',
                'bkash_merchant_number' => '01XXXXXXXXX',
                'bkash_account_type'    => 'Personal',
                'manual_review_hours'   => 6,
            ]);
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::store('array')->forget('platform_settings.current'));
    }
}
