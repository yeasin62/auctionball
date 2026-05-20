<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\PlatformSettings;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminContentController extends Controller
{
    public function index(): Response
    {
        $settings = PlatformSettings::current();

        return Inertia::render('SuperAdmin/Content', [
            'posts' => BlogPost::query()
                ->latest('updated_at')
                ->get()
                ->map(fn (BlogPost $post) => $this->postPayload($post)),
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
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'read_time' => ['nullable', 'string', 'max:40'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function postPayload(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'category' => $post->category,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'read_time' => $post->read_time,
            'is_published' => $post->is_published,
            'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
            'published_date' => $post->formattedDate(),
            'public_url' => $post->is_published ? route('public.blog.show', $post) : null,
            'updated_at' => $post->updated_at?->format('M j, Y H:i'),
        ];
    }
}
