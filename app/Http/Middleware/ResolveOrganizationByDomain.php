<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Host-based tenant resolution.
 *
 * If the incoming request's host matches a verified `custom_domain` on any org,
 * we bind that org as the current organization for this request. Lets a venue
 * embed the big-screen at e.g. https://bpl-cup.example.com/live without ever
 * leaking AuctionBall's primary domain.
 *
 * Falls through silently when no match — the regular session-based resolution
 * (HandleInertiaRequests + EnsureHasOrganization) takes over.
 */
class ResolveOrganizationByDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        // Skip primary domain + dev hosts — they always go through normal flow.
        $primary = strtolower(parse_url(config('app.url'), PHP_URL_HOST) ?? '');
        if ($host === $primary || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return $next($request);
        }

        $org = Organization::where('custom_domain', $host)
            ->whereNotNull('custom_domain_verified_at')
            ->first();

        if ($org && $org->isWhiteLabel()) {
            // Make this org "stick" for the rest of the request — same session key
            // EnsureHasOrganization reads from. Doesn't override existing session
            // org for authenticated dashboard sessions on a sub-path.
            $request->attributes->set('current_organization', $org);
            $request->attributes->set('domain_resolved', true);

            // Public big-screen / team-device pages on a custom domain shouldn't
            // require an existing session — make sure the org is also resolvable
            // by middleware that reads from session.
            if (! $request->session()->get('current_organization_id')) {
                $request->session()->put('current_organization_id', $org->id);
            }
        }

        return $next($request);
    }
}
