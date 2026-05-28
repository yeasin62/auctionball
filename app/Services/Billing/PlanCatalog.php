<?php

namespace App\Services\Billing;

use App\Models\PlanPricing;

class PlanCatalog
{
    /**
     * Fallback prices in BDT. Runtime pricing comes from plan_pricing so the
     * landing page, billing panel, and checkout charge all stay in sync with
     * super-admin edits.
     */
    private const FALLBACK_PLANS = [
        'free'       => ['amount_bdt' => 0,      'amount_usd' => 0,    'cycle' => 'monthly'],
        'starter'    => ['amount_bdt' => 1999,   'amount_usd' => 18,   'cycle' => 'monthly'],
        'pro'        => ['amount_bdt' => 4999,   'amount_usd' => 45,   'cycle' => 'monthly'],
        'enterprise' => ['amount_bdt' => 5999,   'amount_usd' => 55,   'cycle' => 'monthly'],
    ];

    private const BDT_PER_USD = 110;

    public static function priceFor(string $plan, string $currency = 'BDT'): int
    {
        $row = self::plans()[$plan] ?? null;
        if (! $row) throw new \InvalidArgumentException("Unknown plan: {$plan}");
        return $currency === 'USD' ? $row['amount_usd'] : $row['amount_bdt'];
    }

    public static function allPaid(): array
    {
        return collect(self::plans())
            ->filter(fn ($p, $k) => $k !== 'free')
            ->map(fn ($p, $k) => array_merge(['plan' => $k], $p))
            ->values()->all();
    }

    private static function plans(): array
    {
        $rows = PlanPricing::orderBy('sort_order')->get();
        if ($rows->isEmpty()) {
            return self::FALLBACK_PLANS;
        }

        return $rows
            ->mapWithKeys(fn (PlanPricing $plan) => [
                $plan->slug => self::payload((int) $plan->price_bdt),
            ])
            ->all();
    }

    private static function payload(int $amountBdt): array
    {
        return [
            'amount_bdt' => $amountBdt,
            'amount_usd' => $amountBdt > 0
                ? max(1, (int) round($amountBdt / self::BDT_PER_USD))
                : 0,
            'cycle' => 'monthly',
        ];
    }
}
