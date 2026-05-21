<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    posts: { type: Array, default: () => [] },
});

const publishedPosts = computed(() => props.posts.filter((post) => post.is_published));
const draftPosts = computed(() => props.posts.filter((post) => ! post.is_published));

const deletePost = (post) => {
    if (! window.confirm(`Delete "${post.title}"?`)) return;
    router.delete(route('admin.content.blog.delete', post.slug), { preserveScroll: true });
};
</script>

<template>
    <Head title="Blog Posts | Super Admin" />

    <SuperAdminLayout title="Blog Posts">
        <div class="space-y-5">
            <section class="glass rounded-2xl p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-[22px] font-extrabold tracking-tight text-ink-900">Blog posts</h2>
                        <p class="mt-1 max-w-2xl text-[13px] leading-6 text-ink-500">
                            Review published articles and drafts. Open a post to edit content, images, SEO, and schema.
                        </p>
                    </div>
                    <Link :href="route('admin.content.blog-posts.create')" class="btn-primary px-4 py-2.5 text-[13px]">
                        New post
                    </Link>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <div class="glass rounded-2xl p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-[18px] font-extrabold tracking-tight text-ink-900">Drafts</h3>
                            <p class="mt-1 text-[12.5px] text-ink-500">Unpublished posts waiting for review.</p>
                        </div>
                        <span class="rounded-full bg-amber-50 px-3 py-1 font-mono text-[11px] font-bold text-amber-700">
                            {{ draftPosts.length }}
                        </span>
                    </div>

                    <div class="mt-5 space-y-3">
                        <article v-for="post in draftPosts" :key="post.id" class="rounded-xl border border-ink-200/70 bg-white/75 p-4">
                            <img v-if="post.featured_image_url" :src="post.featured_image_url" alt="" class="mb-3 h-36 w-full rounded-lg border border-ink-200/60 object-cover" />
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h4 class="text-[16px] font-extrabold leading-6 tracking-tight text-ink-900">{{ post.title }}</h4>
                                    <div class="mt-1 truncate font-mono text-[11px] text-ink-500">/blog/{{ post.slug }} · Draft</div>
                                </div>
                                <span class="shrink-0 rounded-full bg-amber-50 px-2 py-1 font-mono text-[10px] font-bold text-amber-700">DRAFT</span>
                            </div>
                            <p v-if="post.excerpt" class="mt-2 text-[12.5px] leading-5 text-ink-500">{{ post.excerpt }}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <Link :href="route('admin.content.blog-posts.edit', post.slug)" class="btn-ghost px-3 py-1.5 text-[12px]">Edit</Link>
                                <button type="button" @click="deletePost(post)" class="btn-ghost px-3 py-1.5 text-[12px] text-rose-600">Delete</button>
                            </div>
                        </article>
                        <p v-if="!draftPosts.length" class="rounded-xl border border-dashed border-ink-200 p-5 text-center text-[13px] text-ink-500">
                            No draft posts.
                        </p>
                    </div>
                </div>

                <div class="glass rounded-2xl p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-[18px] font-extrabold tracking-tight text-ink-900">Published</h3>
                            <p class="mt-1 text-[12.5px] text-ink-500">Live articles visible on the public blog.</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 font-mono text-[11px] font-bold text-emerald-700">
                            {{ publishedPosts.length }}
                        </span>
                    </div>

                    <div class="mt-5 space-y-3">
                        <article v-for="post in publishedPosts" :key="post.id" class="rounded-xl border border-ink-200/70 bg-white/75 p-4">
                            <img v-if="post.featured_image_url" :src="post.featured_image_url" alt="" class="mb-3 h-36 w-full rounded-lg border border-ink-200/60 object-cover" />
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h4 class="text-[16px] font-extrabold leading-6 tracking-tight text-ink-900">{{ post.title }}</h4>
                                    <div class="mt-1 truncate font-mono text-[11px] text-ink-500">/blog/{{ post.slug }} · Published</div>
                                </div>
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-1 font-mono text-[10px] font-bold text-emerald-700">LIVE</span>
                            </div>
                            <p v-if="post.excerpt" class="mt-2 text-[12.5px] leading-5 text-ink-500">{{ post.excerpt }}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <Link :href="route('admin.content.blog-posts.edit', post.slug)" class="btn-ghost px-3 py-1.5 text-[12px]">Edit</Link>
                                <Link v-if="post.public_url" :href="post.public_url" target="_blank" class="btn-ghost px-3 py-1.5 text-[12px]">View</Link>
                                <button type="button" @click="deletePost(post)" class="btn-ghost px-3 py-1.5 text-[12px] text-rose-600">Delete</button>
                            </div>
                        </article>
                        <p v-if="!publishedPosts.length" class="rounded-xl border border-dashed border-ink-200 p-5 text-center text-[13px] text-ink-500">
                            No published posts.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </SuperAdminLayout>
</template>
