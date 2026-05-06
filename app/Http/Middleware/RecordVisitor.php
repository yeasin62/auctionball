<?php

namespace App\Http\Middleware;

use App\Models\VisitorEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records one row per debounced page hit into `visitor_events`.
 *
 * Skipped:
 *   - non-GET requests (no analytic value, plus avoids POST loops)
 *   - JSON / file-download responses (API calls aren't "visits")
 *   - Inertia partial reloads (router.reload({ only: [...] }) — already counted on the full page)
 *
 * Debounce: identical session_id + path within 30s collapses into one row.
 */
class RecordVisitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->record($request, $response);
        } catch (\Throwable $e) {
            // Tracking is best-effort — a DB hiccup shouldn't take down the page.
            Log::debug('Visitor tracking skipped: ' . $e->getMessage());
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        if (! $request->isMethod('GET')) return;

        // Inertia partial reloads (router.reload({ only: [...] })) don't render
        // a fresh page — the visitor was already counted when the full page loaded.
        if ($request->header('X-Inertia') === 'true' && $request->header('X-Inertia-Partial-Data')) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        // Treat HTML and Inertia (which the browser sends as text/html) as a "visit".
        // Pure JSON, octet-stream, application/pdf, etc. are not.
        if (! str_contains($contentType, 'text/html')) return;

        $sessionId = session()->getId();
        if (! $sessionId) return;     // no session → typically a bot or static-asset request that slipped through

        $hash = hash('sha256', $sessionId);

        // Debounce: same session + same path within 30s = one event.
        $cacheKey = "visit:{$hash}:" . md5($request->path());
        if (Cache::has($cacheKey)) return;
        Cache::put($cacheKey, 1, 30);

        VisitorEvent::create([
            'session_id'      => $hash,
            'ip_hash'         => hash('sha256', $request->ip() . config('app.key')),
            'path'            => substr('/' . ltrim($request->path(), '/'), 0, 255),
            'referrer'        => $this->cleanReferrer($request),
            'utm_source'      => $this->cleanUtm($request->query('utm_source')),
            'utm_medium'      => $this->cleanUtm($request->query('utm_medium')),
            'utm_campaign'    => $this->cleanUtm($request->query('utm_campaign')),
            'organization_id' => $request->attributes->get('current_organization')?->id,
            'user_id'         => Auth::id(),
            'created_at'      => now(),
        ]);
    }

    /**
     * Trim referrer to a sane size and drop the query string — we only need the
     * hostname for source classification. Avoids storing user-identifying paths
     * from the referring site.
     */
    private function cleanReferrer(Request $request): ?string
    {
        $ref = $request->headers->get('referer');
        if (! $ref) return null;
        $parts = parse_url($ref);
        if (! $parts || empty($parts['host'])) return null;
        $url = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if (! empty($parts['path']) && $parts['path'] !== '/') {
            $url .= $parts['path'];
        }
        return substr($url, 0, 512);
    }

    /** UTM tags often arrive as raw user input — strip and cap. */
    private function cleanUtm(?string $value): ?string
    {
        if (! $value) return null;
        $v = trim(strip_tags((string) $value));
        return $v === '' ? null : substr($v, 0, 100);
    }
}
