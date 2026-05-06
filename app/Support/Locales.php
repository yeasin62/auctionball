<?php

namespace App\Support;

class Locales
{
    /** Display labels for the language toggle. Keys are locale codes Laravel + Vue i18n use. */
    public const SUPPORTED = [
        'en' => ['name' => 'English', 'native' => 'English', 'flag' => '🇬🇧'],
        'bn' => ['name' => 'Bengali', 'native' => 'বাংলা',   'flag' => '🇧🇩'],
    ];

    public static function isValid(string $locale): bool
    {
        return array_key_exists($locale, self::SUPPORTED);
    }

    public static function default(): string
    {
        return 'en';
    }

    /** A flat list shipped to the frontend for the toggle UI. */
    public static function forFrontend(): array
    {
        return collect(self::SUPPORTED)
            ->map(fn ($v, $k) => ['code' => $k] + $v)
            ->values()->all();
    }
}
