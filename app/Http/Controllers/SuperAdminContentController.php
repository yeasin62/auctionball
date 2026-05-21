<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogCategory;
use App\Models\PlatformSettings;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SuperAdminContentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Content', [
            'categories' => BlogCategory::query()
                ->withCount('posts')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (BlogCategory $category) => $this->categoryPayload($category)),
            'posts' => BlogPost::query()
                ->with('blogCategory')
                ->latest('updated_at')
                ->get()
                ->map(fn (BlogPost $post) => $this->postPayload($post)),
        ]);
    }

    public function advanced(): Response
    {
        $settings = PlatformSettings::current();

        return Inertia::render('SuperAdmin/Advanced', [
            'scriptSettings' => $settings->only([
                'head_scripts',
                'body_start_scripts',
                'body_end_scripts',
            ]),
        ]);
    }

    public function storePost(Request $request): RedirectResponse
    {
        $data = $this->validatePost($request);
        $data = $this->normalizePostCategory($data);
        $data['slug'] = $data['slug'] ?: BlogPost::makeUniqueSlug($data['title']);
        $data['published_at'] = $data['is_published']
            ? ($data['published_at'] ?: now())
            : null;

        $post = BlogPost::create($data);

        Audit::log('blog.created', "Blog post created: {$post->title}", ['post_id' => $post->id]);

        return back()->with('success', 'Blog post created.');
    }

    public function updatePost(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $this->validatePost($request, $post);
        $data = $this->normalizePostCategory($data);
        $data['slug'] = $data['slug'] ?: BlogPost::makeUniqueSlug($data['title'], $post->id);
        $data['published_at'] = $data['is_published']
            ? ($data['published_at'] ?: $post->published_at ?: now())
            : null;

        $post->update($data);

        Audit::log('blog.updated', "Blog post updated: {$post->title}", ['post_id' => $post->id]);

        return back()->with('success', 'Blog post updated.');
    }

    public function deletePost(BlogPost $post): RedirectResponse
    {
        $title = $post->title;
        $post->delete();

        Audit::log('blog.deleted', "Blog post deleted: {$title}", ['post_id' => $post->id]);

        return back()->with('success', 'Blog post deleted.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('blog_categories', 'name')],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_categories', 'slug'),
            ],
            'description' => ['nullable', 'string', 'max:300'],
        ]);

        $data['slug'] = $data['slug'] ?: BlogCategory::makeUniqueSlug($data['name']);
        $data['sort_order'] = (int) BlogCategory::query()->max('sort_order') + 1;

        $category = BlogCategory::create($data);

        Audit::log('blog_category.created', "Blog category created: {$category->name}", ['category_id' => $category->id]);

        return back()->with('success', 'Blog category created.');
    }

    public function deleteCategory(BlogCategory $category): RedirectResponse
    {
        if ($category->posts()->exists()) {
            return back()->with('error', 'Move posts out of this category before deleting it.');
        }

        $name = $category->name;
        $category->delete();

        Audit::log('blog_category.deleted', "Blog category deleted: {$name}", ['category_id' => $category->id]);

        return back()->with('success', 'Blog category deleted.');
    }

    public function generatePost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keyword' => ['required', 'string', 'max:180'],
            'word_count' => ['required', 'integer', 'min:250', 'max:2500'],
            'language' => ['required', Rule::in(['en', 'bn'])],
            'tone' => ['required', 'string', 'max:80'],
            'audience' => ['nullable', 'string', 'max:160'],
            'extra_notes' => ['nullable', 'string'],
            'blog_category_id' => ['nullable', Rule::exists('blog_categories', 'id')],
            'include_schema' => ['boolean'],
        ]);

        $settings = PlatformSettings::current();
        $provider = $this->resolveAiProvider($settings);
        if (! $provider) {
            return response()->json([
                'message' => 'OpenAI or Claude API key is missing. Add one in Admin > Integrations, or set OPENAI_API_KEY / ANTHROPIC_API_KEY in .env.',
            ], 422);
        }

        $category = empty($data['blog_category_id'])
            ? null
            : BlogCategory::query()->find($data['blog_category_id']);

        $language = $data['language'] === 'bn' ? 'Bangla' : 'English';
        $schemaInstruction = $data['include_schema']
            ? 'Return a valid JSON-LD Article object in schema_json.'
            : 'Return schema_json as an empty string.';

        $prompt = implode("\n", [
            "Keyword/topic: {$data['keyword']}",
            "Target length: about {$data['word_count']} words.",
            "Language: {$language}.",
            "Tone: {$data['tone']}.",
            'Audience: ' . ($data['audience'] ?: 'auction organizers, team owners, and sports event operators.'),
            'Category: ' . ($category?->name ?: 'Uncategorized'),
            'Extra notes: ' . ($data['extra_notes'] ?: 'None.'),
            $schemaInstruction,
            'Create a complete blog draft for AuctionBall. Use natural editorial writing, specific examples, varied sentence structure, clear subheadings, and practical advice. Avoid generic filler, hype, repeated phrases, and phrases that announce automated writing.',
            'Return only JSON with these exact keys: title, slug, excerpt, body_html, meta_title, meta_description, read_time, schema_json.',
        ]);

        $aiResponse = $provider['name'] === 'anthropic'
            ? $this->generateWithAnthropic($provider, $prompt, (int) $data['word_count'])
            : $this->generateWithOpenAi($provider, $prompt, (int) $data['word_count']);

        if (! $aiResponse['ok']) {
            return response()->json(['message' => $aiResponse['message']], 422);
        }

        if (trim($aiResponse['text']) === '') {
            Log::warning('AI blog generation returned empty text', [
                'provider' => $provider['name'],
                'model' => $provider['model'],
            ]);

            return response()->json([
                'message' => "AI returned an empty response from {$provider['name']} ({$provider['model']}). Try a smaller word count or a faster model.",
            ], 422);
        }

        $generated = $this->decodeGeneratedPost($aiResponse['text']);
        if (! $generated) {
            Log::warning('AI blog generation response parse failed', [
                'provider' => $provider['name'],
                'model' => $provider['model'],
                'text' => Str::limit($aiResponse['text'], 1500),
            ]);

            return response()->json(['message' => 'AI response could not be parsed. Try again.'], 422);
        }

        $generated['slug'] = BlogPost::makeUniqueSlug($generated['slug'] ?: $generated['title']);
        $generated['category'] = $category?->name;
        $generated['blog_category_id'] = $category?->id;
        $generated['is_published'] = false;
        $generated['published_at'] = '';
        $generated['featured_image_url'] = '';

        return response()->json($generated);
    }

    public function updateScripts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'head_scripts' => ['nullable', 'string', 'max:50000'],
            'body_start_scripts' => ['nullable', 'string', 'max:50000'],
            'body_end_scripts' => ['nullable', 'string', 'max:50000'],
        ]);

        PlatformSettings::current()->update($data);

        Audit::log('platform_scripts.updated', 'Platform script slots updated');

        return back()->with('success', 'Script settings updated.');
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
        $path = $data['image']->store('blog', $disk);
        $url = Storage::disk($disk)->url($path);

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            $url = '/storage/' . ltrim($path, '/');
        }

        Audit::log('blog.image_uploaded', 'Blog image uploaded', [
            'disk' => $disk,
            'path' => $path,
        ]);

        return response()->json(['url' => $url]);
    }

    private function validatePost(Request $request, ?BlogPost $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug')->ignore($post?->id),
            ],
            'category' => ['nullable', 'string', 'max:80'],
            'blog_category_id' => ['nullable', Rule::exists('blog_categories', 'id')],
            'featured_image_url' => ['nullable', 'string', 'max:2048'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'schema_json' => ['nullable', 'json', 'max:50000'],
            'read_time' => ['nullable', 'string', 'max:40'],
            'show_date' => ['boolean'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function normalizePostCategory(array $data): array
    {
        $category = empty($data['blog_category_id'])
            ? null
            : BlogCategory::query()->find($data['blog_category_id']);

        $data['blog_category_id'] = $category?->id;
        $data['category'] = $category?->name;

        return $data;
    }

    private function resolveAiProvider(PlatformSettings $settings): ?array
    {
        $openAi = [
            'name' => 'openai',
            'key' => $settings->openai_api_key ?: config('services.openai.key'),
            'model' => $this->normalizeAiModel('openai', $settings->openai_model ?: config('services.openai.model', 'gpt-5.5')),
        ];
        $anthropic = [
            'name' => 'anthropic',
            'key' => $settings->anthropic_api_key ?: config('services.anthropic.key'),
            'model' => $this->normalizeAiModel('anthropic', $settings->anthropic_model ?: config('services.anthropic.model', 'claude-opus-4-1-20250805')),
        ];

        $preferred = $settings->ai_provider ?: 'auto';
        if ($preferred === 'openai') {
            return filled($openAi['key']) ? $openAi : (filled($anthropic['key']) ? $anthropic : null);
        }
        if ($preferred === 'anthropic') {
            return filled($anthropic['key']) ? $anthropic : (filled($openAi['key']) ? $openAi : null);
        }

        return filled($openAi['key']) ? $openAi : (filled($anthropic['key']) ? $anthropic : null);
    }

    private function normalizeAiModel(string $provider, ?string $model): string
    {
        $model = trim((string) $model);

        $knownBadModels = [
            'anthropic' => [
                'claude-opus-4-7',
                'claude-sonnet-4-6',
                'claude-haiku-4-5',
                'claude-haiku-4-5-20251001',
            ],
        ];

        if ($provider === 'openai') {
            return $model === '' ? 'gpt-5.5' : $model;
        }

        return $model === '' || in_array($model, $knownBadModels['anthropic'], true)
            ? 'claude-opus-4-1-20250805'
            : $model;
    }

    private function aiInstructions(): string
    {
        return 'You are a senior content editor for a SaaS product called AuctionBall. You write helpful, human-readable, SEO-aware blog drafts. Output valid JSON only. body_html must contain clean HTML using p, h2, h3, ul, ol, li, strong, em, blockquote, and a tags only.';
    }

    private function generateWithOpenAi(array $provider, string $prompt, int $wordCount): array
    {
        try {
            $response = Http::withToken($provider['key'])
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(55)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $provider['model'],
                    'instructions' => $this->aiInstructions(),
                    'input' => $prompt,
                    'max_output_tokens' => min(5500, max(1200, $wordCount * 3)),
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'auctionball_blog_post',
                            'strict' => true,
                            'schema' => $this->aiPostSchema(),
                        ],
                    ],
                ]);
        } catch (Throwable $e) {
            return $this->aiRequestException('OpenAI', $provider, $e);
        }

        if ($response->failed()) {
            $this->logAiFailure('OpenAI', $provider, $response->status(), $response->body());

            return [
                'ok' => false,
                'message' => $response->json('error.message') ?: 'OpenAI post generation failed. Check the API key, selected model, and account billing/limits.',
                'text' => '',
            ];
        }

        return [
            'ok' => true,
            'message' => null,
            'text' => $this->openAiText($response->json()),
        ];
    }

    private function generateWithAnthropic(array $provider, string $prompt, int $wordCount): array
    {
        try {
            $response = Http::withHeaders([
                    'x-api-key' => $provider['key'],
                    'anthropic-version' => '2023-06-01',
                ])
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(55)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $provider['model'],
                    'max_tokens' => min(5500, max(1200, $wordCount * 3)),
                    'system' => $this->aiInstructions(),
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (Throwable $e) {
            return $this->aiRequestException('Claude', $provider, $e);
        }

        if ($response->failed()) {
            $this->logAiFailure('Claude', $provider, $response->status(), $response->body());

            return [
                'ok' => false,
                'message' => $response->json('error.message') ?: 'Claude post generation failed. Check the API key, selected model, and account billing/limits.',
                'text' => '',
            ];
        }

        return [
            'ok' => true,
            'message' => null,
            'text' => $this->anthropicText($response->json()),
        ];
    }

    private function aiRequestException(string $providerName, array $provider, Throwable $e): array
    {
        Log::warning('AI blog generation request failed', [
            'provider' => $providerName,
            'model' => $provider['model'] ?? null,
            'error' => $e->getMessage(),
        ]);

        return [
            'ok' => false,
            'message' => "{$providerName} request timed out or failed before a response was received. Try a smaller word count first, then check server internet access, API key, and selected model.",
            'text' => '',
        ];
    }

    private function logAiFailure(string $providerName, array $provider, int $status, string $body): void
    {
        Log::warning('AI blog generation provider rejected request', [
            'provider' => $providerName,
            'model' => $provider['model'] ?? null,
            'status' => $status,
            'body' => Str::limit($body, 1000),
        ]);
    }

    private function aiPostSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'excerpt' => ['type' => 'string'],
                'body_html' => ['type' => 'string'],
                'meta_title' => ['type' => 'string'],
                'meta_description' => ['type' => 'string'],
                'read_time' => ['type' => 'string'],
                'schema_json' => ['type' => 'string'],
            ],
            'required' => [
                'title',
                'slug',
                'excerpt',
                'body_html',
                'meta_title',
                'meta_description',
                'read_time',
                'schema_json',
            ],
        ];
    }

    private function openAiText(array $response): string
    {
        $text = $response['output_text'] ?? null;

        if (! $text) {
            $parts = [];
            foreach ($response['output'] ?? [] as $item) {
                foreach ($item['content'] ?? [] as $content) {
                    if (($content['type'] ?? null) === 'output_text') {
                        $parts[] = $content['text'] ?? '';
                    }
                }
            }
            $text = trim(implode("\n", $parts));
        }

        return trim((string) $text);
    }

    private function anthropicText(array $response): string
    {
        $parts = [];
        foreach ($response['content'] ?? [] as $content) {
            if (($content['type'] ?? null) === 'text') {
                $parts[] = $content['text'] ?? '';
            }
        }

        return trim(implode("\n", $parts));
    }

    private function decodeGeneratedPost(string $text): ?array
    {
        $text = trim((string) $text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            $jsonStart = strpos($text, '{');
            $jsonEnd = strrpos($text, '}');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $decoded = json_decode(substr($text, $jsonStart, $jsonEnd - $jsonStart + 1), true);
            }
        }

        if (! is_array($decoded)) {
            return null;
        }

        $body = trim((string) ($decoded['body_html'] ?? $decoded['body'] ?? ''));
        if ($body === '') {
            return null;
        }

        $schemaValue = $decoded['schema_json'] ?? '';
        $schema = '';
        if (is_array($schemaValue)) {
            $schema = json_encode($schemaValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        } else {
            $schema = trim((string) $schemaValue);
        }

        if ($schema !== '' && json_decode($schema, true) === null && json_last_error() !== JSON_ERROR_NONE) {
            $schema = '';
        }

        $title = Str::limit(trim((string) ($decoded['title'] ?? '')), 180, '');
        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'slug' => Str::slug((string) ($decoded['slug'] ?? $title)),
            'excerpt' => Str::limit(trim((string) ($decoded['excerpt'] ?? '')), 500, ''),
            'body' => $body,
            'meta_title' => Str::limit(trim((string) ($decoded['meta_title'] ?? $title)), 180, ''),
            'meta_description' => Str::limit(trim((string) ($decoded['meta_description'] ?? $decoded['excerpt'] ?? '')), 500, ''),
            'read_time' => Str::limit(trim((string) ($decoded['read_time'] ?? '')), 40, ''),
            'schema_json' => $schema,
        ];
    }

    private function categoryPayload(BlogCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'posts_count' => $category->posts_count,
        ];
    }

    private function postPayload(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'category' => $post->categoryName(),
            'blog_category_id' => $post->blog_category_id,
            'featured_image_url' => $post->featured_image_url,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'schema_json' => $post->schema_json,
            'read_time' => $post->read_time,
            'show_date' => $post->show_date,
            'is_published' => $post->is_published,
            'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
            'published_date' => $post->formattedDate(),
            'public_url' => $post->is_published ? route('public.blog.show', $post) : null,
            'updated_at' => $post->updated_at?->format('M j, Y H:i'),
        ];
    }
}
