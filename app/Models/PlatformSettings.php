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
        'landing_payment_methods',
        'head_scripts',
        'body_start_scripts',
        'body_end_scripts',
    ];

    protected $casts = [
        'manual_review_hours'     => 'integer',
        'landing_payment_methods' => 'array',
    ];

    /**
     * Canonical list of methods that may appear on the public landing page.
     * Order here drives display order on the landing page.
     */
    public const LANDING_PAYMENT_METHODS = [
        'bkash',
        'nagad',
        'rocket',
        'sslcommerz',
        'paypal',
        'visa_mastercard',
        'bank_transfer',
    ];

    public static function current(): self
    {
        return Cache::store('array')->rememberForever('platform_settings.current', function () {
            return self::firstOrCreate([], [
                'app_domain'              => 'auctionball.com',
                'bkash_merchant_number'   => '01XXXXXXXXX',
                'bkash_account_type'      => 'Personal',
                'manual_review_hours'     => 6,
                'landing_payment_methods' => ['bkash'],
            ]);
        });
    }

    /**
     * Enabled landing-page payment methods in canonical order. Falls back
     * to the full list if the column is null (older rows).
     */
    public function enabledLandingPaymentMethods(): array
    {
        $enabled = $this->landing_payment_methods;
        if (! is_array($enabled)) {
            return self::LANDING_PAYMENT_METHODS;
        }
        return array_values(array_intersect(self::LANDING_PAYMENT_METHODS, $enabled));
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::store('array')->forget('platform_settings.current'));
    }
}
