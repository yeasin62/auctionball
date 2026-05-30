<?php

namespace App\Http\Middleware;

use App\Models\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        $canonicalHost = strtolower(trim((string) PlatformSettings::current()->app_domain));
        $requestHost = strtolower($request->getHost());

        if ($canonicalHost && $requestHost === "www.{$canonicalHost}") {
            return redirect()->away("https://{$canonicalHost}{$request->getRequestUri()}", 301);
        }

        return $next($request);
    }
}
