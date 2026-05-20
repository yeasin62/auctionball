<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    posts: { type: Array, default: () => [] },
    scriptSettings: { type: Object, default: () => ({}) },
});

const editing = ref(null);

const blankPost = () => ({
    title: '',
    slug: '',
    category: '',
    excerpt: '',
    body: '',
    meta_title: '',
    meta_description: '',
    read_time: '',
    is_published: false,
    published_at: '',
});

const postForm = useForm(blankPost());
const scriptForm = useForm({
    head_scripts: props.scriptSettings.head_scripts || '',
    body_start_scripts: props.scriptSettings.body_start_scripts || '',
    body_end_scripts: props.scriptSettings.body_end_scripts || '',
});

const isEditing = computed(() => Boolean(editing.value));

const editPost = (post) => {
    editing.value = post;
    postForm.defaults({ ...blankPost(), ...post });
    postForm.reset();
    Object.assign(postForm, { ...blankPost(), ...post });
};

const resetPostForm = () => {
    editing.value = null;
    postForm.defaults(blankPost());
    postForm.reset();
};

const savePost = () => {
    const options = {
        preserveScroll: true,
        onSuccess: resetPostForm,
    };

    if (editing.value) {
        postForm.patch(route('admin.content.blog.update', editing.value.slug), options);
        return;
    }

    postForm.post(route('admin.content.blog.store'), options);
};

const deletePost = (post) => {
    if (! window.confirm(`Delete "${post.title}"?`)) return;
    router.delete(route('admin.content.blog.delete', post.slug), { preserveScroll: true });
};

const saveScripts = () => {
    scriptForm.patch(route('admin.content.scripts.update'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Content | Super Admin" />
    <SuperAdminLayout title="Content">
        <div class="grid xl:grid-cols-12 gap-5">
            <section class="xl:col-span-7 glass rounded-2xl p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h2 class="text-[20px] font-extrabold tracking-tight">{{ isEditing ? 'Edit blog post' : 'New blog post' }}</h2>
                        <p class="mt-1 text-[13px] text-ink-500">Published posts appear on /blog and /blog/slug.</p>
                    </div>
                    <button v-if="isEditing" type="button" @click="resetPostForm" class="btn-ghost py-2 px-4 text-[13px]">New post</button>
                </div>

                <form @submit.prevent="savePost" class="space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <Field label="Title" :error="postForm.errors.title" required>
                            <TextField v-model="postForm.title" placeholder="How to run a clean auction night" />
                        </Field>
                        <Field label="Slug" hint="Leave blank to auto-generate" :error="postForm.errors.slug">
                            <TextField v-model="postForm.slug" placeholder="clean-auction-night" />
                        </Field>
                        <Field label="Category" :error="postForm.errors.category">
                            <TextField v-model="postForm.category" placeholder="Auction operations" />
                        </Field>
                        <Field label="Read time" :error="postForm.errors.read_time">
                            <TextField v-model="postForm.read_time" placeholder="5 min read" />
                        </Field>
                    </div>

                    <Field label="Excerpt" :error="postForm.errors.excerpt">
                        <textarea v-model="postForm.excerpt" rows="3" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    </Field>

                    <Field label="Body" hint="Plain text, blank line creates a paragraph" :error="postForm.errors.body" required>
                        <textarea v-model="postForm.body" rows="12" class="font-mono w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[13px] leading-6 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    </Field>

                    <div class="grid md:grid-cols-2 gap-4">
                        <Field label="SEO title" :error="postForm.errors.meta_title">
                            <TextField v-model="postForm.meta_title" />
                        </Field>
                        <Field label="Publish date" :error="postForm.errors.published_at">
                            <TextField v-model="postForm.published_at" type="datetime-local" />
                        </Field>
                    </div>
                    <Field label="SEO description" :error="postForm.errors.meta_description">
                        <textarea v-model="postForm.meta_description" rows="2" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    </Field>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <label class="inline-flex items-center gap-2 rounded-lg border border-ink-200/70 bg-white/70 px-3 py-2 cursor-pointer">
                            <input v-model="postForm.is_published" type="checkbox" class="h-4 w-4" />
                            <span class="text-[13px] font-medium">Publish</span>
                        </label>
                        <button type="submit" class="btn-primary py-2.5 px-5 text-[13px]" :disabled="postForm.processing">
                            {{ postForm.processing ? 'Saving...' : (isEditing ? 'Update post' : 'Create post') }}
                        </button>
                    </div>
                </form>
            </section>

            <aside class="xl:col-span-5 space-y-5">
                <section class="glass rounded-2xl p-6">
                    <h2 class="text-[18px] font-extrabold tracking-tight mb-4">Blog posts</h2>
                    <div class="space-y-3">
                        <article v-for="post in posts" :key="post.id" class="rounded-xl border border-ink-200/70 bg-white/70 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-bold tracking-tight">{{ post.title }}</h3>
                                    <div class="mt-1 font-mono text-[11px] text-ink-500">
                                        /blog/{{ post.slug }} · {{ post.is_published ? 'Published' : 'Draft' }}
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-1 font-mono text-[10px]" :class="post.is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                                    {{ post.is_published ? 'LIVE' : 'DRAFT' }}
                                </span>
                            </div>
                            <p v-if="post.excerpt" class="mt-2 text-[12.5px] leading-5 text-ink-500">{{ post.excerpt }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" @click="editPost(post)" class="btn-ghost py-1.5 px-3 text-[12px]">Edit</button>
                                <Link v-if="post.public_url" :href="post.public_url" target="_blank" class="btn-ghost py-1.5 px-3 text-[12px]">View</Link>
                                <button type="button" @click="deletePost(post)" class="btn-ghost py-1.5 px-3 text-[12px] text-rose-600">Delete</button>
                            </div>
                        </article>
                        <p v-if="!posts.length" class="rounded-xl border border-dashed border-ink-200 p-5 text-center text-[13px] text-ink-500">
                            No blog posts yet.
                        </p>
                    </div>
                </section>
            </aside>
        </div>

        <section class="mt-5 glass rounded-2xl p-6">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div>
                    <h2 class="text-[20px] font-extrabold tracking-tight">Header, body, and footer scripts</h2>
                    <p class="mt-1 text-[13px] text-ink-500">Use for analytics, pixels, chat widgets, or custom verification scripts. These render raw on every page.</p>
                </div>
                <button type="button" @click="saveScripts" class="btn-primary py-2.5 px-5 text-[13px]" :disabled="scriptForm.processing">
                    {{ scriptForm.processing ? 'Saving...' : 'Save scripts' }}
                </button>
            </div>
            <div class="grid lg:grid-cols-3 gap-4">
                <Field label="Header scripts" hint="Before </head>" :error="scriptForm.errors.head_scripts">
                    <textarea v-model="scriptForm.head_scripts" rows="9" class="font-mono w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[12.5px] leading-5 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" placeholder="<script>...</script>" />
                </Field>
                <Field label="Body scripts" hint="After <body>" :error="scriptForm.errors.body_start_scripts">
                    <textarea v-model="scriptForm.body_start_scripts" rows="9" class="font-mono w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[12.5px] leading-5 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" placeholder="<noscript>...</noscript>" />
                </Field>
                <Field label="Footer scripts" hint="Before </body>" :error="scriptForm.errors.body_end_scripts">
                    <textarea v-model="scriptForm.body_end_scripts" rows="9" class="font-mono w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[12.5px] leading-5 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" placeholder="<script src='...'></script>" />
                </Field>
            </div>
        </section>
    </SuperAdminLayout>
</template>
