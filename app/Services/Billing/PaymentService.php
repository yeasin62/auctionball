<?php

namespace App\Services\Billing;

use InvalidArgumentException;

class PaymentService
{
    /**
     * Resolve a provider implementation by key — and silently fall back to dev simulator
     * if real credentials aren't configured. That keeps `npm run dev` clickable end-to-end
     * without needing PayPal/bKash sandbox accounts.
     */
    public function for(string $provider): PaymentProvider
    {
        return match ($provider) {
            'paypal' => $this->hasPaypalCreds()
                ? new PayPalProvider()
                : new DevSimulateProvider(),
            'bkash'  => $this->hasBkashCreds()
                ? new BkashProvider()
                : new DevSimulateProvider(),
            'dev'    => new DevSimulateProvider(),
            default  => throw new InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }

    public function hasPaypalCreds(): bool
    {
        return ! empty(env('PAYPAL_CLIENT_ID')) && ! empty(env('PAYPAL_SECRET'));
    }

    public function hasBkashCreds(): bool
    {
        return ! empty(env('BKASH_USERNAME')) && ! empty(env('BKASH_APP_KEY'));
    }

    public function availability(): array
    {
        return [
            'paypal' => $this->hasPaypalCreds(),
            'bkash'  => $this->hasBkashCreds(),
        ];
    }
}
