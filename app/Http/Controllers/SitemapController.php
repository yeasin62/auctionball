<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\PlatformSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Dynamic sitemap.xml with only public, indexable URLs.
 *
 * Auth-walled/private paths such as /dashboard, /admin, /bid, /r/{token},
 * /tr/{token}, payment callbacks, and invite links are intentionally absent.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $base = rtrim((string) config('app.url'), '/');

        if (str_contains($base, 'localhost') || str_contains($base, '127.0.0.1')) {
            $domain = PlatformSettings::current()->app_domain ?? 'auctionball.com';
            $base = "https://{$domain}";
        }

        $today = now()->toDateString();
        $urls = collect([$this->url("{$base}/", '1.0', 'weekly', $today)]);

        foreach (PublicPageController::PAGE_SLUGS as $slug) {
            $urls->push($this->url(
                "{$base}/{$slug}",
                match ($slug) {
                    'pricing' => '0.9',
                    'features', 'getting-started' => '0.8',
                    'live-demo', 'contact' => '0.7',
                    default => '0.6',
                },
                in_array($slug, ['pricing', 'changelog', 'status'], true) ? 'weekly' : 'monthly',
                $today
            ));
        }

        $urls->push($this->url("{$base}/blog", '0.7', 'weekly', $today));
        $urls->push($this->url("{$base}/help", '0.8', 'monthly', $today));

        foreach ($this->helpDocs() as $doc) {
            $urls->push($this->url(
                "{$base}/help/{$doc['slug']}",
                '0.6',
                'monthly',
                $doc['lastmod'] ?? $today
            ));
        }

        foreach (BlogPost::query()->published()->latest('published_at')->get(['slug', 'updated_at', 'published_at']) as $post) {
            $urls->push($this->url(
                "{$base}/blog/{$post->slug}",
                '0.6',
                'monthly',
                ($post->updated_at ?? $post->published_at)?->toDateString() ?? $today
            ));
        }

        return response($this->toXml($urls->unique('loc')->values()->all()), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function url(string $loc, string $priority, string $changefreq, string $lastmod): array
    {
        return compact('loc', 'priority', 'changefreq', 'lastmod');
    }

    private function helpDocs(): array
    {
        $dir = resource_path('docs/en');

        if (! is_dir($dir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $docs = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            $frontmatter = $this->frontmatter((string) file_get_contents($file->getPathname()));
            $filename = preg_replace('/^\d+[-_]?/', '', $file->getBasename('.md'));

            $docs[] = [
                'slug' => $frontmatter['slug'] ?? Str::slug($filename),
                'lastmod' => date('Y-m-d', $file->getMTime()),
            ];
        }

        return collect($docs)->unique('slug')->sortBy('slug')->values()->all();
    }

    private function frontmatter(string $raw): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $raw, $match)) {
            return [];
        }

        $frontmatter = [];
        foreach (preg_split("/\r?\n/", $match[1]) as $line) {
            if (preg_match('/^([a-z_]+)\s*:\s*(.+)$/i', trim($line), $kv)) {
                $frontmatter[strtolower($kv[1])] = trim($kv[2], "\"' \t");
            }
        }

        return $frontmatter;
    }

    private function toXml(array $urls): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $dom->appendChild($dom->createProcessingInstruction(
            'xml-stylesheet',
            'type="text/xsl" href="/sitemap.xsl"'
        ));

        $urlset = $dom->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urlset->setAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        $dom->appendChild($urlset);

        foreach ($urls as $url) {
            $node = $urlset->appendChild($dom->createElement('url'));
            $node->appendChild($dom->createElement('loc', $url['loc']));
            $node->appendChild($dom->createElement('lastmod', $url['lastmod']));
            $node->appendChild($dom->createElement('changefreq', $url['changefreq']));
            $node->appendChild($dom->createElement('priority', $url['priority']));

            foreach (['en' => '?lang=en', 'bn' => '?lang=bn', 'x-default' => ''] as $lang => $suffix) {
                $alternate = $dom->createElementNS('http://www.w3.org/1999/xhtml', 'xhtml:link');
                $alternate->setAttribute('rel', 'alternate');
                $alternate->setAttribute('hreflang', $lang);
                $alternate->setAttribute('href', $url['loc'] . $suffix);
                $node->appendChild($alternate);
            }
        }

        return $dom->saveXML();
    }
}
