<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the request locale in this order:
     *   1. ?lang= query (one-shot)
     *   2. Logged-in user's stored preference
     *   3. Session value (set when guest toggles)
     *   4. Cookie (persisted across sessions for guests)
     *   5. Browser Accept-Language header
     *   6. App default
     */
    public function handle(Request $request, Closure $next): Response
    {
        $candidates = array_filter([
            $request->query('lang'),
            $request->user()?->locale,
            $request->session()->get('locale'),
            $request->cookie('locale'),
            $this->fromAcceptLanguage($request),
        ], fn ($v) => is_string($v) && $v !== '');

        $locale = Locales::default();
        foreach ($candidates as $candidate) {
            if (Locales::isValid($candidate)) {
                $locale = $candidate;
                break;
            }
        }

        app()->setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }

    private function fromAcceptLanguage(Request $request): ?string
    {
        $header = $request->header('Accept-Language');
        if (! $header) return null;

        // Parse "bn-BD,bn;q=0.9,en-US;q=0.8" → ['bn-BD', 'bn', 'en-US']
        $tags = collect(explode(',', $header))
            ->map(fn ($s) => strtolower(trim(explode(';', $s)[0])))
            ->filter();

        foreach ($tags as $tag) {
            $primary = explode('-', $tag)[0];
            if (Locales::isValid($primary)) return $primary;
        }
        return null;
    }
}
