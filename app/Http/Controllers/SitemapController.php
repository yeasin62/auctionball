<?php

namespace App\Http\Controllers;

use App\Models\PlatformSettings;
use App\Models\BlogPost;
use Illuminate\Http\Response;

/**
 * Dynamic sitemap.xml — only public, indexable URLs. Auth-walled / private
 * paths (/dashboard, /admin, /bid, /r/{token}, /tr/{token}) are intentionally
 * absent so search engines never even attempt to crawl them.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        // Fall back to the platform's configured app domain if APP_URL is
        // localhost (typical of misconfigured prod) so the sitemap is at least
        // pointing at the canonical public domain.
        if (str_contains($base, 'localhost') || str_contains($base, '127.0.0.1')) {
            $domain = PlatformSettings::current()->app_domain ?? 'auctionball.com';
            $base = "https://{$domain}";
        }

        $today = now()->toDateString();

        $urls = [
            ['loc' => "{$base}/",         'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => "{$base}/features",   'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => "{$base}/pricing",    'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => "{$base}/live-demo",  'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => "{$base}/blog",       'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => "{$base}/help",       'priority' => '0.8', 'changefreq' => 'monthly'],
        ];

        foreach (\App\Http\Controllers\PublicPageController::PAGE_SLUGS as $slug) {
            if (in_array($slug, ['features', 'pricing', 'live-demo'], true)) {
                continue;
            }
            $urls[] = ['loc' => "{$base}/{$slug}", 'priority' => '0.6', 'changefreq' => 'monthly'];
        }

        foreach (BlogPost::query()->published()->latest('published_at')->get(['slug']) as $post) {
            $urls[] = ['loc' => "{$base}/blog/{$post->slug}", 'priority' => '0.6', 'changefreq' => 'monthly'];
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        foreach ($urls as $u) {
            $xml .= '<url>';
            $xml .= '<loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>';
            $xml .= "<lastmod>{$today}</lastmod>";
            $xml .= "<changefreq>{$u['changefreq']}</changefreq>";
            $xml .= "<priority>{$u['priority']}</priority>";
            // hreflang annotations so search engines can serve the right locale.
            $xml .= '<xhtml:link rel="alternate" hreflang="en" href="' . htmlspecialchars($u['loc'], ENT_XML1) . '?lang=en" />';
            $xml .= '<xhtml:link rel="alternate" hreflang="bn" href="' . htmlspecialchars($u['loc'], ENT_XML1) . '?lang=bn" />';
            $xml .= '<xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($u['loc'], ENT_XML1) . '" />';
            $xml .= '</url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',     // 1h CDN cache; sitemap rarely changes
        ]);
    }
}
