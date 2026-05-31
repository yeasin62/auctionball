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
        ]);
    }

    public function blogPosts(): Response
    {
        return Inertia::render('SuperAdmin/BlogPosts', [
            'posts' => BlogPost::query()
                ->with('blogCategory')
                ->orderByRaw('COALESCE(published_at, created_at) DESC')
                ->orderByDesc('id')
                ->get()
                ->map(fn (BlogPost $post) => $this->postPayload($post)),
        ]);
    }

    public function createPost(): Response
    {
        return Inertia::render('SuperAdmin/BlogPostEditor', [
            'categories' => BlogCategory::query()
                ->withCount('posts')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (BlogCategory $category) => $this->categoryPayload($category)),
            'post' => null,
        ]);
    }

    public function editPost(BlogPost $post): Response
    {
        $post->load('blogCategory');

        return Inertia::render('SuperAdmin/BlogPostEditor', [
            'categories' => BlogCategory::query()
                ->withCount('posts')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (BlogCategory $category) => $this->categoryPayload($category)),
            'post' => $this->postPayload($post),
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

        return redirect()->route('admin.content.blog-posts.index')->with('success', 'Blog post created.');
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

        return redirect()->route('admin.content.blog-posts.index')->with('success', 'Blog post updated.');
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
        $providers = $this->resolveAiProviders($settings);
        if ($providers === []) {
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
            'Do not wrap the response in markdown fences. Do not add comments, labels, explanation, or prose before or after the JSON object.',
            'All values must be JSON-safe strings. Escape quotes and line breaks correctly. body_html must be one JSON string containing clean HTML.',
            'Return only JSON with these exact keys: title, slug, excerpt, body_html, meta_title, meta_description, read_time, schema_json.',
        ]);

        [$aiResponse, $provider] = $this->generateWithAvailableProvider($providers, $prompt, (int) $data['word_count']);

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
                'json_error' => json_last_error_msg(),
                'text' => Str::limit($aiResponse['text'], 1500),
            ]);

            return response()->json(['message' => 'AI response could not be parsed. Try again.'], 422);
        }

        $generated['slug'] = BlogPost::makeUniqueSlug($generated['slug'] ?: $generated['title']);
        $generated['category'] = $category?->name;
        $generated['blog_category_id'] = $category?->id;
        $generated['is_published'] = false;
        $generated['published_at'] = '';
        $generated['featured_image_url'] = $this->generateFeaturedImageUrl(
            $settings,
            $generated,
            $data['keyword'],
            $language,
            $category?->name
        );

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
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);

        [$disk, $path, $url] = $this->storeBlogImage($data['image']);

        Audit::log('blog.image_uploaded', 'Blog image uploaded', [
            'disk' => $disk,
            'path' => $path,
        ]);

        return response()->json(['url' => $url]);
    }

    private function storeBlogImage(\Illuminate\Http\UploadedFile $image): array
    {
        [$bytes, $extension] = $this->optimizedImageBytes(
            file_get_contents($image->getRealPath()) ?: '',
            $image->getClientOriginalExtension() ?: 'jpg'
        );
        $preferredDisk = config('filesystems.default') === 's3' ? 's3' : 'public';

        foreach (array_unique([$preferredDisk, 'public']) as $disk) {
            try {
                $path = 'blog/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . $extension;
                if (! Storage::disk($disk)->put($path, $bytes)) {
                    throw new \RuntimeException("Storage disk [{$disk}] rejected optimized image.");
                }

                return [$disk, $path, $this->storedImageUrl($disk, $path)];
            } catch (Throwable $e) {
                Log::warning('Blog image upload disk failed', [
                    'disk' => $disk,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        abort(500, 'Image upload failed. Please check storage configuration.');
    }

    private function storedImageUrl(string $disk, string $path): string
    {
        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            return '/storage/' . ltrim($path, '/');
        }

        return Storage::disk($disk)->url($path);
    }

    private function generateFeaturedImageUrl(
        PlatformSettings $settings,
        array $generated,
        string $keyword,
        string $language,
        ?string $category
    ): string {
        $key = $settings->openai_api_key ?: config('services.openai.key');
        if (! filled($key)) {
            Log::info('AI featured image skipped because OpenAI key is missing');
            return '';
        }

        $prompt = implode(' ', [
            'Create a polished editorial featured image for an AuctionBall blog post.',
            'Subject: ' . $keyword . '.',
            'Title: ' . ($generated['title'] ?? $keyword) . '.',
            'Category: ' . ($category ?: 'sports auction software') . '.',
            'Language context: ' . $language . '.',
            'Visual direction: modern SaaS sports auction dashboard, cricket or football tournament atmosphere, live bidding energy, clean composition, realistic venue display and mobile bidding cues.',
            'No readable text, no logos, no brand names, no watermarks, no distorted UI.',
            'Aspect ratio 16:9, production-ready blog hero image.',
        ]);

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(90)
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => config('services.openai.image_model', 'gpt-image-1'),
                    'prompt' => $prompt,
                    'size' => '1536x1024',
                    'quality' => 'medium',
                    'n' => 1,
                ]);
        } catch (Throwable $e) {
            Log::warning('AI featured image generation request failed', [
                'error' => $e->getMessage(),
            ]);

            return '';
        }

        if ($response->failed()) {
            Log::warning('AI featured image generation failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 1000),
            ]);

            return '';
        }

        $image = $response->json('data.0', []);
        $bytes = null;
        $extension = 'png';

        if (! empty($image['b64_json'])) {
            $bytes = base64_decode((string) $image['b64_json'], true) ?: null;
        } elseif (! empty($image['url'])) {
            $download = Http::timeout(30)->get($image['url']);
            if ($download->successful()) {
                $bytes = $download->body();
                $extension = str_contains((string) $download->header('Content-Type'), 'jpeg') ? 'jpg' : 'png';
            }
        }

        if (! $bytes) {
            Log::warning('AI featured image generation returned no usable image data');
            return '';
        }

        return $this->storeBlogImageBytes($bytes, $extension);
    }

    private function storeBlogImageBytes(string $bytes, string $extension = 'png'): string
    {
        [$bytes, $extension] = $this->optimizedImageBytes($bytes, $extension, forceWebp: true);
        $preferredDisk = config('filesystems.default') === 's3' ? 's3' : 'public';
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $extension : 'png';
        $path = 'blog/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . $extension;

        foreach (array_unique([$preferredDisk, 'public']) as $disk) {
            try {
                if (! Storage::disk($disk)->put($path, $bytes)) {
                    throw new \RuntimeException("Storage disk [{$disk}] rejected generated image.");
                }

                return $this->storedImageUrl($disk, $path);
            } catch (Throwable $e) {
                Log::warning('Generated blog image storage disk failed', [
                    'disk' => $disk,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return '';
    }

    private function optimizedImageBytes(string $bytes, string $extension, bool $forceWebp = false): array
    {
        if ($bytes === '' || ! function_exists('imagecreatefromstring')) {
            return [$bytes, $this->safeImageExtension($extension)];
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            return [$bytes, $this->safeImageExtension($extension)];
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $maxWidth = 1600;
        $targetWidth = $width > $maxWidth ? $maxWidth : $width;
        $targetHeight = $width > $maxWidth ? (int) round($height * ($maxWidth / $width)) : $height;
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $extension = $forceWebp && function_exists('imagewebp') ? 'webp' : $this->safeImageExtension($extension);

        ob_start();
        $ok = match ($extension) {
            'webp' => function_exists('imagewebp') && imagewebp($target, null, 82),
            'jpg', 'jpeg' => imagejpeg($target, null, 84),
            'png' => imagepng($target, null, 7),
            default => imagejpeg($target, null, 84),
        };
        $optimized = $ok ? (ob_get_clean() ?: '') : '';

        imagedestroy($source);
        imagedestroy($target);

        return $optimized !== ''
            ? [$optimized, $extension === 'jpeg' ? 'jpg' : $extension]
            : [$bytes, $this->safeImageExtension($extension)];
    }

    private function safeImageExtension(string $extension): string
    {
        $extension = strtolower(trim($extension, '. '));
        if ($extension === 'jpeg') {
            return 'jpg';
        }

        return in_array($extension, ['jpg', 'png', 'webp', 'gif'], true) ? $extension : 'jpg';
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

    private function resolveAiProviders(PlatformSettings $settings): array
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
        $ordered = match ($preferred) {
            'anthropic' => [$anthropic, $openAi],
            default => [$openAi, $anthropic],
        };

        return array_values(array_filter($ordered, fn (array $provider) => filled($provider['key'])));
    }

    private function generateWithAvailableProvider(array $providers, string $prompt, int $wordCount): array
    {
        $lastResponse = [
            'ok' => false,
            'message' => 'Post generation failed. Please try again.',
            'text' => '',
        ];
        $lastProvider = $providers[0] ?? ['name' => 'unknown', 'model' => null];

        foreach ($providers as $provider) {
            $response = $provider['name'] === 'anthropic'
                ? $this->generateWithAnthropic($provider, $prompt, $wordCount)
                : $this->generateWithOpenAi($provider, $prompt, $wordCount);

            if ($response['ok']) {
                return [$response, $provider];
            }

            $lastResponse = $response;
            $lastProvider = $provider;

            Log::warning('AI blog generation provider fallback triggered', [
                'provider' => $provider['name'],
                'model' => $provider['model'] ?? null,
                'message' => $response['message'] ?? null,
            ]);
        }

        return [$lastResponse, $lastProvider];
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
        return 'You are a senior content editor for a SaaS product called AuctionBall. You write helpful, human-readable, SEO-aware blog drafts. Output one valid JSON object only. Do not use markdown fences, comments, labels, or any text outside the JSON object. body_html must be a JSON string containing clean HTML using p, h2, h3, ul, ol, li, strong, em, blockquote, and a tags only.';
    }

    private function generateWithOpenAi(array $provider, string $prompt, int $wordCount): array
    {
        try {
            $response = Http::withToken($provider['key'])
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(85)
                ->retry(2, 1200)
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
                ->timeout(85)
                ->retry(2, 1200)
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
        $decoded = $this->decodeAiJson($text);

        if (isset($decoded[0]) && is_array($decoded[0])) {
            $decoded = $decoded[0];
        }

        foreach (['post', 'article', 'data', 'draft'] as $wrapperKey) {
            if (isset($decoded[$wrapperKey]) && is_array($decoded[$wrapperKey])) {
                $decoded = $decoded[$wrapperKey];
                break;
            }
        }

        if (! is_array($decoded)) {
            return null;
        }

        $body = trim((string) ($decoded['body_html'] ?? $decoded['bodyHtml'] ?? $decoded['body'] ?? $decoded['content'] ?? $decoded['html'] ?? ''));
        if ($body === '') {
            return null;
        }

        $schemaValue = $decoded['schema_json'] ?? $decoded['schemaJson'] ?? $decoded['schema'] ?? '';
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
            'excerpt' => Str::limit(trim((string) ($decoded['excerpt'] ?? $decoded['summary'] ?? $decoded['description'] ?? '')), 500, ''),
            'body' => $body,
            'meta_title' => Str::limit(trim((string) ($decoded['meta_title'] ?? $decoded['metaTitle'] ?? $decoded['seo_title'] ?? $title)), 180, ''),
            'meta_description' => Str::limit(trim((string) ($decoded['meta_description'] ?? $decoded['metaDescription'] ?? $decoded['seo_description'] ?? $decoded['excerpt'] ?? '')), 500, ''),
            'read_time' => Str::limit(trim((string) ($decoded['read_time'] ?? $decoded['readTime'] ?? '')), 40, ''),
            'schema_json' => $schema,
        ];
    }

    private function decodeAiJson(string $text): ?array
    {
        foreach ($this->jsonCandidates($text) as $candidate) {
            $decoded = json_decode($candidate, true);

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function jsonCandidates(string $text): array
    {
        $text = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $text));
        if ($text === '') {
            return [];
        }

        $candidates = [$text];

        if (preg_match_all('/```(?:json)?\s*([\s\S]*?)```/i', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $candidates[] = trim($match);
            }
        }

        $stripped = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $stripped = preg_replace('/\s*```$/', '', (string) $stripped);
        $candidates[] = trim((string) $stripped);

        foreach ($this->balancedJsonSnippets($text, '{', '}') as $snippet) {
            $candidates[] = $snippet;
        }

        foreach ($this->balancedJsonSnippets($text, '[', ']') as $snippet) {
            $candidates[] = $snippet;
        }

        return array_values(array_unique(array_filter(array_map('trim', $candidates))));
    }

    /**
     * Finds JSON-looking objects/arrays while respecting quoted strings.
     */
    private function balancedJsonSnippets(string $text, string $open, string $close): array
    {
        $snippets = [];
        $length = strlen($text);

        for ($start = 0; $start < $length; $start++) {
            if ($text[$start] !== $open) {
                continue;
            }

            $depth = 0;
            $inString = false;
            $escape = false;

            for ($index = $start; $index < $length; $index++) {
                $char = $text[$index];

                if ($inString) {
                    if ($escape) {
                        $escape = false;
                        continue;
                    }

                    if ($char === '\\') {
                        $escape = true;
                        continue;
                    }

                    if ($char === '"') {
                        $inString = false;
                    }

                    continue;
                }

                if ($char === '"') {
                    $inString = true;
                    continue;
                }

                if ($char === $open) {
                    $depth++;
                }

                if ($char === $close) {
                    $depth--;
                }

                if ($depth === 0) {
                    $snippets[] = substr($text, $start, $index - $start + 1);
                    break;
                }
            }
        }

        return $snippets;
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
            'title' => (string) $post->title,
            'slug' => (string) $post->slug,
            'category' => (string) ($post->categoryName() ?? ''),
            'blog_category_id' => $post->blog_category_id,
            'featured_image_url' => (string) ($post->featured_image_url ?? ''),
            'excerpt' => (string) ($post->excerpt ?? ''),
            'body' => (string) ($post->body ?? ''),
            'meta_title' => (string) ($post->meta_title ?? ''),
            'meta_description' => (string) ($post->meta_description ?? ''),
            'schema_json' => (string) ($post->schema_json ?? ''),
            'read_time' => (string) ($post->read_time ?? ''),
            'show_date' => $post->show_date,
            'is_published' => $post->is_published,
            'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
            'published_date' => $post->formattedDate(),
            'public_url' => $post->is_published ? route('public.blog.show', $post) : null,
            'updated_at' => $post->updated_at?->format('M j, Y H:i'),
        ];
    }
}
