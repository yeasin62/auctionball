<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Markdown-driven help / user-guide.
 *
 *   resources/docs/{locale}/
 *       group-a/01-some-topic.md
 *       group-b/02-another.md
 *
 * Each .md file has YAML-ish frontmatter:
 *   ---
 *   title: Getting Started
 *   group: Setup
 *   order: 1
 *   ---
 *   # heading...
 *
 * Slug is the filename minus the numeric prefix (e.g. `01-getting-started.md`
 * → `getting-started`). Override with `slug:` in frontmatter if you want.
 */
class HelpController extends Controller
{
    public function index(Request $request): Response
    {
        $docs = $this->loadDocs($request);
        $first = $docs[0] ?? null;
        return $this->renderShow($docs, $first);
    }

    public function show(Request $request, string $slug): Response
    {
        $docs = $this->loadDocs($request);
        $doc  = collect($docs)->firstWhere('slug', $slug);
        abort_if(! $doc, 404, 'Doc not found.');
        return $this->renderShow($docs, $doc);
    }

    private function renderShow(array $docs, ?array $current): Response
    {
        // Build prev/next so the bottom nav can move sequentially through the toc
        $idx = collect($docs)->search(fn ($d) => $current && $d['slug'] === $current['slug']);
        $prev = $idx !== false && $idx > 0                  ? $docs[$idx - 1] : null;
        $next = $idx !== false && $idx < count($docs) - 1   ? $docs[$idx + 1] : null;

        return Inertia::render('Help/Show', [
            'toc'  => $this->buildToc($docs),
            'doc'  => $current ? $this->renderDoc($current) : null,
            'prev' => $prev ? ['slug' => $prev['slug'], 'title' => $prev['title']] : null,
            'next' => $next ? ['slug' => $next['slug'], 'title' => $next['title']] : null,
        ]);
    }

    /** Group docs by their `group` frontmatter for sidebar TOC rendering. */
    private function buildToc(array $docs): array
    {
        return collect($docs)
            ->groupBy('group')
            ->map(fn ($groupDocs, $group) => [
                'group' => $group,
                'items' => $groupDocs->map(fn ($d) => [
                    'slug'  => $d['slug'],
                    'title' => $d['title'],
                ])->values(),
            ])
            ->values()
            ->all();
    }

    /** Pull all .md files from the locale's docs dir, parse, sort. */
    private function loadDocs(Request $request): array
    {
        $locale = $request->user()?->preferred_locale ?? app()->getLocale();
        $dir = resource_path("docs/{$locale}");
        if (! is_dir($dir)) $dir = resource_path('docs/en');
        if (! is_dir($dir)) return [];

        $cacheKey = 'help_docs_' . md5($dir);
        // In dev, skip cache so admins can edit md files and reload to see changes
        $useCache = app()->environment('production');

        $build = function () use ($dir) {
            $files = $this->scanMarkdown($dir);
            return collect($files)
                ->map(fn ($f) => $this->parseDoc($f, $dir))
                ->sortBy([['group', 'asc'], ['order', 'asc'], ['title', 'asc']])
                ->values()
                ->all();
        };

        return $useCache ? Cache::remember($cacheKey, 3600, $build) : $build();
    }

    private function scanMarkdown(string $dir): array
    {
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $out = [];
        foreach ($rii as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
                $out[] = $file->getPathname();
            }
        }
        return $out;
    }

    private function parseDoc(string $path, string $rootDir): array
    {
        $raw = file_get_contents($path) ?: '';
        $frontmatter = [];
        $body = $raw;

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $m)) {
            foreach (preg_split("/\r?\n/", $m[1]) as $line) {
                if (preg_match('/^([a-z_]+)\s*:\s*(.+)$/i', trim($line), $kv)) {
                    $frontmatter[strtolower($kv[1])] = trim($kv[2], "\"' \t");
                }
            }
            $body = $m[2];
        }

        $relative = ltrim(str_replace([$rootDir, '\\'], ['', '/'], $path), '/');
        $parts = explode('/', $relative);
        $filename = array_pop($parts);
        $defaultGroup = ! empty($parts) ? Str::headline($parts[0]) : 'General';

        // Strip leading "NN-" from filename to derive slug
        $slugSource = preg_replace('/^\d+[-_]?/', '', $filename);
        $slugSource = preg_replace('/\.md$/i', '', $slugSource);

        return [
            'slug'  => $frontmatter['slug']  ?? Str::slug($slugSource),
            'title' => $frontmatter['title'] ?? Str::headline($slugSource),
            'group' => $frontmatter['group'] ?? $defaultGroup,
            'order' => (int) ($frontmatter['order'] ?? 99),
            'body'  => $body,
        ];
    }

    private function renderDoc(array $doc): array
    {
        // GFM: tables, strikethrough, autolinks, task lists. HeadingPermalink
        // adds anchors so users can deep-link to a section like #setup.
        $env = new \League\CommonMark\Environment\Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'symbol' => '',         // empty so the anchor doesn't render a ¶
                'html_class' => 'heading-anchor',
                'insert' => 'before',
            ],
        ]);
        $env->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
        $env->addExtension(new GithubFlavoredMarkdownExtension());
        $env->addExtension(new HeadingPermalinkExtension());

        $converter = new \League\CommonMark\MarkdownConverter($env);

        return [
            ...$doc,
            'html' => (string) $converter->convert($doc['body']),
        ];
    }
}
