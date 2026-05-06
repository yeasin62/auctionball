<?php

namespace App\Services\Billing;

class PlanCatalog
{
    /** Prices in BDT (the smallest unit we store as integer = whole taka). */
    public const PLANS = [
        'free'       => ['amount_bdt' => 0,      'amount_usd' => 0,    'cycle' => 'monthly'],
        'starter'    => ['amount_bdt' => 1999,   'amount_usd' => 18,   'cycle' => 'monthly'],
        'pro'        => ['amount_bdt' => 4999,   'amount_usd' => 45,   'cycle' => 'monthly'],
        'enterprise' => ['amount_bdt' => 9999,   'amount_usd' => 90,   'cycle' => 'monthly'],
    ];

    public static function priceFor(string $plan, string $currency = 'BDT'): int
    {
        $row = self::PLANS[$plan] ?? null;
        if (! $row) throw new \InvalidArgumentException("Unknown plan: {$plan}");
        return $currency === 'USD' ? $row['amount_usd'] : $row['amount_bdt'];
    }

    public static function allPaid(): array
    {
        return collect(self::PLANS)
            ->filter(fn ($p, $k) => $k !== 'free')
            ->map(fn ($p, $k) => array_merge(['plan' => $k], $p))
            ->values()->all();
    }
}
