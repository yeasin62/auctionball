<?php

namespace App\Support;

use NumberFormatter;

/**
 * Money formatting that respects locale (numeral system + grouping) and the
 * org's chosen display currency.
 *
 * Internal amounts are always stored in BDT integers. This helper handles:
 *   · Bengali digits (০-৯) when locale=bn
 *   · Indian-style grouping for both EN and BN (1,25,000 — not 125,000)
 *   · USD display by converting through bdt_per_usd at view time
 *
 * Fast: NumberFormatter is created on demand and the intl extension is already loaded.
 */
class Money
{
    /**
     * @param int    $amountBdt  Amount in BDT (the canonical storage unit, integer).
     * @param string $locale     'en' | 'bn'
     * @param string $currency   'BDT' | 'USD'
     * @param int    $bdtPerUsd  Conversion rate (default 110)
     */
    public static function format(
        int $amountBdt,
        ?string $locale = null,
        ?string $currency = null,
        ?int $bdtPerUsd = null,
    ): string {
        $locale     ??= app()->getLocale();
        $currency   ??= 'BDT';
        $bdtPerUsd  ??= 110;

        // Indian-style grouping (lakh, crore) for both BDT and USD displays —
        // Bangladeshi accountants are used to seeing 1,25,000, not 125,000.
        $numLocale = $locale === 'bn' ? 'bn-IN' : 'en-IN';

        if ($currency === 'USD') {
            $usd = (int) round($amountBdt / max(1, $bdtPerUsd));
            return '$' . self::number($usd, $numLocale);
        }

        return '৳' . self::number($amountBdt, $numLocale);
    }

    /** Plain number with locale-aware digits + grouping. */
    public static function number(int $value, ?string $numLocale = null): string
    {
        $numLocale ??= app()->getLocale() === 'bn' ? 'bn-IN' : 'en-IN';
        $f = new NumberFormatter($numLocale, NumberFormatter::DECIMAL);
        return $f->format($value);
    }

    /** Just the symbol — useful for headers. */
    public static function symbol(string $currency = 'BDT'): string
    {
        return $currency === 'USD' ? '$' : '৳';
    }
}
