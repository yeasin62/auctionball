<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref } from 'vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
});

const editing = ref(null);
const editorEl = ref(null);
const editorMode = ref('visual');
const imageInput = ref(null);
const featuredImageInput = ref(null);
const imageUploading = ref(false);
const featuredImageUploading = ref(false);
const slugManuallyEdited = ref(false);
const imageUrl = ref('');
const imageError = ref('');
let savedEditorRange = null;

const blankPost = () => ({
    title: '',
    slug: '',
    blog_category_id: '',
    category: '',
    featured_image_url: '',
    excerpt: '',
    body: '',
    meta_title: '',
    meta_description: '',
    schema_json: '',
    read_time: '',
    show_date: true,
    is_published: false,
    published_at: '',
});

const postForm = useForm(blankPost());
const categoryForm = useForm({
    name: '',
    slug: '',
    description: '',
});
const aiForm = reactive({
    keyword: '',
    word_count: 800,
    language: 'en',
    tone: 'Natural, practical, expert',
    audience: 'auction organizers and sports event teams',
    extra_notes: '',
    blog_category_id: '',
    include_schema: true,
});
const aiGenerating = ref(false);
const aiError = ref('');
const isEditing = computed(() => Boolean(editing.value));

const plainBodyText = computed(() => String(postForm.body || '')
    .replace(/<script[\s\S]*?<\/script>/gi, ' ')
    .replace(/<style[\s\S]*?<\/style>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim());

const wordCount = computed(() => plainBodyText.value
    ? plainBodyText.value.split(/\s+/).filter(Boolean).length
    : 0);

const hasValidSchema = computed(() => {
    const schema = String(postForm.schema_json || '').trim();
    if (! schema) return false;

    try {
        JSON.parse(schema);
        return true;
    } catch {
        return false;
    }
});

const seoChecks = computed(() => [
    {
        label: 'SEO title length',
        detail: 'Keep it around 40-60 characters.',
        passed: postForm.meta_title.length >= 40 && postForm.meta_title.length <= 60,
        points: 15,
    },
    {
        label: 'Meta description length',
        detail: 'Best range is 120-160 characters.',
        passed: postForm.meta_description.length >= 120 && postForm.meta_description.length <= 160,
        points: 15,
    },
    {
        label: 'Readable slug',
        detail: 'Use a short lowercase URL slug.',
        passed: /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(postForm.slug) && postForm.slug.length <= 80,
        points: 10,
    },
    {
        label: 'Featured image',
        detail: 'Add a strong thumbnail for sharing and listing pages.',
        passed: Boolean(postForm.featured_image_url),
        points: 10,
    },
    {
        label: 'Excerpt',
        detail: 'Write a short summary for cards and readers.',
        passed: postForm.excerpt.length >= 60 && postForm.excerpt.length <= 220,
        points: 10,
    },
    {
        label: 'Article depth',
        detail: 'Aim for at least 300 words.',
        passed: wordCount.value >= 300,
        points: 15,
    },
    {
        label: 'Headings',
        detail: 'Use H2/H3 sections in the body.',
        passed: /<h2[\s>]|<h3[\s>]/i.test(postForm.body),
        points: 10,
    },
    {
        label: 'Internal or external link',
        detail: 'Add at least one useful link.',
        passed: /<a\s/i.test(postForm.body),
        points: 5,
    },
    {
        label: 'Category',
        detail: 'Assign a blog category.',
        passed: Boolean(postForm.blog_category_id),
        points: 5,
    },
    {
        label: 'Valid schema',
        detail: 'Optional JSON-LD helps rich results.',
        passed: hasValidSchema.value,
        points: 5,
    },
]);

const seoScore = computed(() => seoChecks.value.reduce((score, item) => (
    score + (item.passed ? item.points : 0)
), 0));

const seoGrade = computed(() => {
    if (seoScore.value >= 85) return 'Excellent';
    if (seoScore.value >= 70) return 'Good';
    if (seoScore.value >= 50) return 'Needs work';
    return 'Weak';
});

const seoScoreClass = computed(() => {
    if (seoScore.value >= 85) return 'text-emerald-600';
    if (seoScore.value >= 70) return 'text-lime-600';
    if (seoScore.value >= 50) return 'text-amber-600';
    return 'text-rose-600';
});

const slugify = (value) => String(value || '')
    .toLowerCase()
    .trim()
    .replace(/['"]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

const handleTitleInput = (value) => {
    postForm.title = value;
    if (! slugManuallyEdited.value) {
        postForm.slug = slugify(value);
    }
};

const handleSlugInput = (value) => {
    postForm.slug = slugify(value);
    slugManuallyEdited.value = Boolean(postForm.slug);
};

const generatePostSlug = () => {
    postForm.slug = slugify(postForm.title);
    slugManuallyEdited.value = Boolean(postForm.slug);
};

const bodyToEditorHtml = (value) => {
    const body = String(value || '').trim();
    if (! body) return '';
    if (/<[a-z][\s\S]*>/i.test(body)) return body;

    const escapeHtml = (text) => text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    return body
        .split(/\n{2,}/)
        .map((paragraph) => `<p>${escapeHtml(paragraph.trim()).replace(/\n/g, '<br>')}</p>`)
        .join('');
};

const syncEditor = () => {
    if (! editorEl.value) return;
    postForm.body = editorEl.value.innerHTML.trim();
};

const saveEditorSelection = () => {
    if (! editorEl.value || editorMode.value !== 'visual') return;

    const selection = window.getSelection();
    if (! selection || selection.rangeCount === 0) return;

    const range = selection.getRangeAt(0);
    if (editorEl.value.contains(range.commonAncestorContainer)) {
        savedEditorRange = range.cloneRange();
    }
};

const restoreEditorSelection = () => {
    if (! savedEditorRange || editorMode.value !== 'visual') return false;

    const selection = window.getSelection();
    if (! selection) return false;

    selection.removeAllRanges();
    selection.addRange(savedEditorRange);
    return true;
};

const setEditorHtml = async (value) => {
    await nextTick();
    if (editorEl.value) {
        editorEl.value.innerHTML = bodyToEditorHtml(value);
        syncEditor();
    }
};

const runCommand = (command, value = null) => {
    restoreEditorSelection();
    editorEl.value?.focus();
    document.execCommand(command, false, value);
    syncEditor();
    saveEditorSelection();
};

const setBlock = (tag) => runCommand('formatBlock', tag);

const switchEditorMode = async (mode) => {
    if (editorMode.value === mode) return;
    if (editorMode.value === 'visual') syncEditor();

    editorMode.value = mode;

    if (mode === 'visual') {
        await setEditorHtml(postForm.body);
    }
};

const addLink = () => {
    const url = window.prompt('Paste URL');
    if (! url) return;
    runCommand('createLink', url);
};

const imageMarkup = (url) => `<figure><img src="${url}" alt=""><figcaption>Image caption</figcaption></figure>`;

const insertImageUrl = async (url) => {
    imageError.value = '';
    const cleanUrl = String(url || '').trim();
    if (! cleanUrl) {
        imageError.value = 'Add an image URL or upload an image first.';
        return;
    }

    const scrollY = window.scrollY;
    const markup = imageMarkup(cleanUrl);

    if (editorMode.value === 'html') {
        postForm.body = `${postForm.body || ''}\n\n${markup}`.trim();
        imageUrl.value = '';
        return;
    }

    await nextTick();
    if (restoreEditorSelection()) {
        document.execCommand('insertHTML', false, markup);
    } else {
        editorEl.value?.insertAdjacentHTML('beforeend', markup);
    }

    syncEditor();
    saveEditorSelection();
    imageUrl.value = '';
    window.requestAnimationFrame(() => window.scrollTo({ top: scrollY, left: 0, behavior: 'auto' }));
};

const chooseImage = (event = null) => {
    event?.preventDefault?.();
    saveEditorSelection();
    imageInput.value?.click();
};

const uploadImage = async (event) => {
    const file = event.target.files?.[0];
    if (! file) return;

    const data = new FormData();
    data.append('image', file);
    imageUploading.value = true;

    try {
        const response = await window.axios.post(route('admin.content.images.upload'), data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        await insertImageUrl(response.data.url);
    } catch (error) {
        imageError.value = error.response?.data?.message || 'Image upload failed.';
    } finally {
        imageUploading.value = false;
        event.target.value = '';
    }
};

const addImageByUrl = () => {
    saveEditorSelection();
    insertImageUrl(imageUrl.value);
};

const uploadDroppedImage = async (event) => {
    saveEditorSelection();
    const file = Array.from(event.dataTransfer?.files || []).find((item) => item.type.startsWith('image/'));
    if (! file) return;

    await uploadImage({ target: { files: [file], value: '' } });
};

const chooseFeaturedImage = () => {
    featuredImageInput.value?.click();
};

const uploadFeaturedImage = async (event) => {
    const file = event.target.files?.[0];
    if (! file) return;

    const data = new FormData();
    data.append('image', file);
    featuredImageUploading.value = true;

    try {
        const response = await window.axios.post(route('admin.content.images.upload'), data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        postForm.featured_image_url = response.data.url;
    } catch (error) {
        window.alert(error.response?.data?.message || 'Featured image upload failed.');
    } finally {
        featuredImageUploading.value = false;
        event.target.value = '';
    }
};

const setFeaturedImageByUrl = () => {
    const url = window.prompt('Paste featured image URL', postForm.featured_image_url || '');
    if (! url) return;
    postForm.featured_image_url = url;
};

const removeFeaturedImage = () => {
    postForm.featured_image_url = '';
};

const editPost = (post) => {
    editing.value = post;
    editorMode.value = 'visual';
    slugManuallyEdited.value = true;
    const payload = { ...blankPost(), ...post, blog_category_id: post.blog_category_id || '' };
    postForm.defaults(payload);
    postForm.reset();
    Object.assign(postForm, payload);
    setEditorHtml(post.body);
};

const resetPostForm = () => {
    editing.value = null;
    editorMode.value = 'visual';
    slugManuallyEdited.value = false;
    postForm.defaults(blankPost());
    postForm.reset();
    setEditorHtml('');
};

const savePost = () => {
    if (editorMode.value === 'visual') syncEditor();

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

const generationErrorMessage = (error) => {
    const data = error.response?.data;

    if (data?.message) return data.message;
    if (data?.errors) {
        const first = Object.values(data.errors).flat()[0];
        if (first) return first;
    }

    if (error.response?.status) {
        return `Post generation failed with server status ${error.response.status}. Check Laravel logs for the exact provider error.`;
    }

    if (error.code === 'ECONNABORTED') {
        return 'Post generation timed out. Try a smaller word count or a faster model.';
    }

    if (error.message) {
        return `Post generation failed: ${error.message}`;
    }

    return 'Post generation failed. Please try again.';
};

const generateAiPost = async () => {
    aiError.value = '';
    aiGenerating.value = true;

    try {
        const response = await window.axios.post(route('admin.content.blog.generate'), {
            ...aiForm,
            blog_category_id: aiForm.blog_category_id || null,
        });

        const generated = response.data;
        editing.value = null;
        editorMode.value = 'visual';
        slugManuallyEdited.value = true;
        const payload = {
            ...blankPost(),
            ...generated,
            blog_category_id: generated.blog_category_id || aiForm.blog_category_id || '',
            is_published: false,
            published_at: '',
        };

        postForm.defaults(payload);
        postForm.reset();
        Object.assign(postForm, payload);
        await setEditorHtml(generated.body || '');
    } catch (error) {
        aiError.value = generationErrorMessage(error);
    } finally {
        aiGenerating.value = false;
    }
};

const deletePost = (post) => {
    if (! window.confirm(`Delete "${post.title}"?`)) return;
    router.delete(route('admin.content.blog.delete', post.slug), { preserveScroll: true });
};

const saveCategory = () => {
    categoryForm.post(route('admin.content.categories.store'), {
        preserveScroll: true,
        onSuccess: () => {
            categoryForm.reset();
        },
    });
};

const deleteCategory = (category) => {
    if (category.posts_count > 0) {
        window.alert('This category has posts. Move those posts to another category before deleting it.');
        return;
    }

    if (! window.confirm(`Delete "${category.name}" category?`)) return;
    router.delete(route('admin.content.categories.delete', category.id), { preserveScroll: true });
};

</script>

<template>
    <Head title="Blog Posts | Super Admin" />
    <SuperAdminLayout title="Blog Posts">
        <div class="grid xl:grid-cols-12 gap-5">
            <section class="xl:col-span-7 glass rounded-2xl p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h2 class="text-[20px] font-extrabold tracking-tight">{{ isEditing ? 'Edit blog post' : 'New blog post' }}</h2>
                        <p class="mt-1 text-[13px] text-ink-500">Published posts appear on /blog and /blog/slug.</p>
                    </div>
                    <button v-if="isEditing" type="button" @click="resetPostForm" class="btn-ghost py-2 px-4 text-[13px]">New post</button>
                </div>

                <form @submit.prevent="generateAiPost" class="mb-5 rounded-2xl border border-brand-indigo/15 bg-brand-indigo/5 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-[16px] font-extrabold tracking-tight text-ink-900">AI draft generator</h3>
                            <p class="mt-1 text-[12.5px] leading-5 text-ink-500">Enter a keyword, target length, and style. It creates an editable draft, not a published post.</p>
                        </div>
                        <button type="submit" class="btn-primary py-2.5 px-4 text-[13px]" :disabled="aiGenerating || !aiForm.keyword">
                            {{ aiGenerating ? 'Generating...' : 'Generate draft' }}
                        </button>
                    </div>

                    <div class="mt-4 grid md:grid-cols-2 gap-3">
                        <Field label="Keyword or topic" required>
                            <TextField v-model="aiForm.keyword" placeholder="football auction software" />
                        </Field>
                        <Field label="Target words">
                            <input v-model.number="aiForm.word_count" type="number" min="250" max="2500" step="50" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                        </Field>
                        <Field label="Language">
                            <select v-model="aiForm.language" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                <option value="en">English</option>
                                <option value="bn">Bangla</option>
                            </select>
                        </Field>
                        <Field label="Tone">
                            <TextField v-model="aiForm.tone" placeholder="Natural, practical, expert" />
                        </Field>
                        <Field label="Audience">
                            <TextField v-model="aiForm.audience" placeholder="auction organizers and sports event teams" />
                        </Field>
                        <Field label="Category">
                            <select v-model="aiForm.blog_category_id" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                <option value="">Uncategorized</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </Field>
                    </div>

                    <Field label="Extra instructions" class="mt-3">
                        <textarea v-model="aiForm.extra_notes" rows="2" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" placeholder="Mention live bidding, team wallet, player photos, pricing, or any specific angle." />
                    </Field>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <label class="inline-flex items-center gap-2 rounded-lg border border-ink-200/70 bg-white/70 px-3 py-2 cursor-pointer">
                            <input v-model="aiForm.include_schema" type="checkbox" class="h-4 w-4" />
                            <span class="text-[13px] font-medium">Generate Article schema</span>
                        </label>
                        <p v-if="aiError" class="text-[12.5px] font-semibold text-rose-600">{{ aiError }}</p>
                    </div>
                </form>

                <form @submit.prevent="savePost" class="space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <Field label="Title" :error="postForm.errors.title" required>
                            <TextField :model-value="postForm.title" placeholder="How to run a clean auction night" @update:model-value="handleTitleInput" />
                        </Field>
                        <Field label="Slug" hint="Auto-generated from title, editable before saving" :error="postForm.errors.slug">
                            <div class="flex gap-2">
                                <div class="min-w-0 flex-1">
                                    <TextField :model-value="postForm.slug" placeholder="clean-auction-night" @update:model-value="handleSlugInput" />
                                </div>
                                <button type="button" @click="generatePostSlug" class="btn-ghost shrink-0 px-3 py-2 text-[12px]">Generate</button>
                            </div>
                        </Field>
                        <Field label="Category" :error="postForm.errors.blog_category_id">
                            <select v-model="postForm.blog_category_id" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                <option value="">Uncategorized</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </Field>
                        <Field label="Read time" :error="postForm.errors.read_time">
                            <TextField v-model="postForm.read_time" placeholder="5 min read" />
                        </Field>
                    </div>

                    <Field label="Featured image" hint="Shown on blog listing and at the top of the single blog post" :error="postForm.errors.featured_image_url">
                        <div class="rounded-xl border border-ink-200/70 bg-white/80 p-3">
                            <div v-if="postForm.featured_image_url" class="mb-3 overflow-hidden rounded-lg border border-ink-200/70 bg-white">
                                <img :src="postForm.featured_image_url" alt="Featured preview" class="h-52 w-full object-cover" />
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="chooseFeaturedImage" class="btn-ghost py-2 px-3 text-[13px]" :disabled="featuredImageUploading">
                                    {{ featuredImageUploading ? 'Uploading...' : 'Upload image' }}
                                </button>
                                <button type="button" @click="setFeaturedImageByUrl" class="btn-ghost py-2 px-3 text-[13px]">Image URL</button>
                                <button v-if="postForm.featured_image_url" type="button" @click="removeFeaturedImage" class="btn-ghost py-2 px-3 text-[13px] text-rose-600">Remove</button>
                                <input ref="featuredImageInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="uploadFeaturedImage" />
                            </div>
                            <input v-model="postForm.featured_image_url" type="text" class="mt-3 w-full rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 font-mono text-[12px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" placeholder="/storage/blog/featured.jpg" />
                        </div>
                    </Field>

                    <Field label="Excerpt" :error="postForm.errors.excerpt">
                        <textarea v-model="postForm.excerpt" rows="3" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    </Field>

                    <Field label="Body" hint="Use visual mode, or switch to HTML to paste/edit source code" :error="postForm.errors.body" required>
                        <div class="rounded-xl border border-ink-200/70 bg-white/80 overflow-hidden shadow-sm">
                            <div class="flex flex-wrap items-center gap-1 border-b border-ink-200/70 bg-ink-50/80 px-2 py-2">
                                <div class="mr-2 inline-flex rounded-lg border border-ink-200 bg-white p-0.5">
                                    <button type="button" @click="switchEditorMode('visual')" class="mode-btn" :class="{ 'mode-btn-active': editorMode === 'visual' }">Visual</button>
                                    <button type="button" @click="switchEditorMode('html')" class="mode-btn" :class="{ 'mode-btn-active': editorMode === 'html' }">HTML</button>
                                </div>
                                <template v-if="editorMode === 'visual'">
                                    <button type="button" @mousedown.prevent @click="setBlock('p')" class="editor-btn">P</button>
                                    <button type="button" @mousedown.prevent @click="setBlock('h2')" class="editor-btn">H2</button>
                                    <button type="button" @mousedown.prevent @click="setBlock('h3')" class="editor-btn">H3</button>
                                    <span class="mx-1 h-5 w-px bg-ink-200"></span>
                                    <button type="button" @mousedown.prevent @click="runCommand('bold')" class="editor-btn font-bold">B</button>
                                    <button type="button" @mousedown.prevent @click="runCommand('italic')" class="editor-btn italic">I</button>
                                    <button type="button" @mousedown.prevent @click="runCommand('underline')" class="editor-btn underline">U</button>
                                    <span class="mx-1 h-5 w-px bg-ink-200"></span>
                                    <button type="button" @mousedown.prevent @click="runCommand('insertUnorderedList')" class="editor-btn">Bullets</button>
                                    <button type="button" @mousedown.prevent @click="runCommand('insertOrderedList')" class="editor-btn">1. List</button>
                                    <button type="button" @mousedown.prevent @click="setBlock('blockquote')" class="editor-btn">Quote</button>
                                    <button type="button" @mousedown.prevent @click="addLink" class="editor-btn">Link</button>
                                    <span class="mx-1 h-5 w-px bg-ink-200"></span>
                                    <button type="button" @mousedown.prevent @click="runCommand('undo')" class="editor-btn">Undo</button>
                                    <button type="button" @mousedown.prevent @click="runCommand('redo')" class="editor-btn">Redo</button>
                                </template>
                            </div>
                            <div class="border-b border-ink-200/70 bg-white/70 px-3 py-3">
                                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <input v-model="imageUrl" type="url" class="min-w-0 flex-1 rounded-lg border border-ink-200/70 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" placeholder="Paste image URL, then Insert image" />
                                        <button type="button" @click="addImageByUrl" class="btn-ghost shrink-0 px-3 py-2 text-[12px]">Insert image</button>
                                    </div>
                                    <label class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-dashed border-brand-indigo/35 bg-brand-indigo/5 px-4 py-2 text-[13px] font-bold text-brand-indigo transition hover:bg-brand-indigo/10">
                                        {{ imageUploading ? 'Uploading...' : 'Upload image' }}
                                        <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" :disabled="imageUploading" @click="saveEditorSelection" @change="uploadImage" />
                                    </label>
                                </div>
                                <p v-if="imageError" class="mt-2 text-[12px] font-semibold text-rose-600">{{ imageError }}</p>
                            </div>
                            <div v-if="editorMode === 'visual'"
                                 ref="editorEl"
                                 contenteditable="true"
                                 class="blog-editor min-h-[320px] px-5 py-4 text-[15px] leading-8 text-ink-800 focus:outline-none"
                                 @dragover.prevent
                                 @drop.prevent="uploadDroppedImage"
                                 @focus="saveEditorSelection"
                                 @keyup="saveEditorSelection"
                                 @mouseup="saveEditorSelection"
                                 @input="syncEditor(); saveEditorSelection()"
                                 @blur="saveEditorSelection"></div>
                            <textarea v-else
                                      v-model="postForm.body"
                                      rows="16"
                                      class="font-mono min-h-[320px] w-full resize-y border-0 bg-white px-5 py-4 text-[13px] leading-6 text-ink-800 focus:outline-none"
                                      placeholder="<h2>Section title</h2>&#10;<p>Write HTML here...</p>"></textarea>
                        </div>
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
                    <Field label="Schema JSON-LD" hint="Paste valid schema.org JSON. It will render as application/ld+json on the single blog post." :error="postForm.errors.schema_json">
                        <textarea v-model="postForm.schema_json" rows="8" class="font-mono w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[12.5px] leading-5 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" placeholder="{&#10;  &quot;@context&quot;: &quot;https://schema.org&quot;,&#10;  &quot;@type&quot;: &quot;Article&quot;,&#10;  &quot;headline&quot;: &quot;Your post title&quot;&#10;}" />
                    </Field>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="inline-flex items-center gap-2 rounded-lg border border-ink-200/70 bg-white/70 px-3 py-2 cursor-pointer">
                                <input v-model="postForm.is_published" type="checkbox" class="h-4 w-4" />
                                <span class="text-[13px] font-medium">Publish</span>
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-lg border border-ink-200/70 bg-white/70 px-3 py-2 cursor-pointer">
                                <input v-model="postForm.show_date" type="checkbox" class="h-4 w-4" />
                                <span class="text-[13px] font-medium">Show date</span>
                            </label>
                        </div>
                        <button type="submit" class="btn-primary py-2.5 px-5 text-[13px]" :disabled="postForm.processing">
                            {{ postForm.processing ? 'Saving...' : (isEditing ? 'Update post' : 'Create post') }}
                        </button>
                    </div>
                </form>
            </section>

            <aside class="xl:col-span-5 space-y-5">
                <section class="glass rounded-2xl p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-[18px] font-extrabold tracking-tight text-ink-900">SEO score</h2>
                            <p class="mt-1 text-[12.5px] leading-5 text-ink-500">Live checks before publishing.</p>
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-[40px] font-black leading-none" :class="seoScoreClass">{{ seoScore }}</div>
                            <div class="mt-1 text-[12px] font-bold uppercase tracking-widest text-ink-500">{{ seoGrade }}</div>
                        </div>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-ink-100">
                        <div class="h-full rounded-full bg-gradient-brand transition-all" :style="{ width: `${seoScore}%` }"></div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div v-for="check in seoChecks" :key="check.label" class="rounded-lg border px-3 py-2" :class="check.passed ? 'border-emerald-200 bg-emerald-50/70' : 'border-ink-200/70 bg-white/70'">
                            <div class="flex items-center gap-2">
                                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] font-bold" :class="check.passed ? 'bg-emerald-500 text-white' : 'bg-ink-200 text-ink-500'">
                                    {{ check.passed ? '✓' : '!' }}
                                </span>
                                <span class="min-w-0 text-[12px] font-bold leading-4 text-ink-800">{{ check.label }}</span>
                                <span class="ml-auto font-mono text-[11px] text-ink-400">+{{ check.points }}</span>
                            </div>
                            <p class="mt-1 pl-7 text-[11px] leading-4 text-ink-500">{{ check.detail }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3 font-mono text-[11px] text-ink-500">
                        <span>Title: {{ postForm.meta_title.length }} chars</span>
                        <span>Description: {{ postForm.meta_description.length }} chars</span>
                        <span>Words: {{ wordCount }}</span>
                    </div>
                </section>

                <section class="glass rounded-2xl p-6">
                    <h2 class="text-[18px] font-extrabold tracking-tight">Blog categories</h2>
                    <form @submit.prevent="saveCategory" class="mt-4 space-y-3">
                        <Field label="Name" :error="categoryForm.errors.name" required>
                            <TextField v-model="categoryForm.name" placeholder="Auction operations" />
                        </Field>
                        <Field label="Slug" hint="Leave blank to auto-generate" :error="categoryForm.errors.slug">
                            <TextField v-model="categoryForm.slug" placeholder="auction-operations" />
                        </Field>
                        <Field label="Description" :error="categoryForm.errors.description">
                            <textarea v-model="categoryForm.description" rows="2" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-3 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                        </Field>
                        <button type="submit" class="btn-primary py-2.5 px-4 text-[13px]" :disabled="categoryForm.processing">
                            {{ categoryForm.processing ? 'Saving...' : 'Add category' }}
                        </button>
                    </form>

                    <div class="mt-5 space-y-2">
                        <div v-for="category in categories" :key="category.id" class="flex items-center justify-between gap-3 rounded-xl border border-ink-200/70 bg-white/70 px-4 py-3">
                            <div class="min-w-0">
                                <div class="truncate text-[13px] font-bold text-ink-800">{{ category.name }}</div>
                                <div class="mt-0.5 font-mono text-[11px] text-ink-500">
                                    {{ category.slug }} · {{ category.posts_count }} posts
                                </div>
                            </div>
                            <button type="button" @click="deleteCategory(category)" class="btn-ghost shrink-0 py-1.5 px-3 text-[12px] text-rose-600" :disabled="category.posts_count > 0">
                                Delete
                            </button>
                        </div>
                        <p v-if="!categories.length" class="rounded-xl border border-dashed border-ink-200 p-4 text-center text-[13px] text-ink-500">
                            No categories yet.
                        </p>
                    </div>
                </section>

                <section class="glass rounded-2xl p-6">
                    <h2 class="text-[18px] font-extrabold tracking-tight mb-4">Blog posts</h2>
                    <div class="space-y-3">
                        <article v-for="post in posts" :key="post.id" class="rounded-xl border border-ink-200/70 bg-white/70 p-4">
                            <img v-if="post.featured_image_url" :src="post.featured_image_url" alt="" class="mb-3 h-28 w-full rounded-lg object-cover border border-ink-200/60" />
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

    </SuperAdminLayout>
</template>

<style scoped>
.editor-btn {
    min-height: 30px;
    border-radius: 8px;
    padding: 0.35rem 0.6rem;
    font-size: 12px;
    line-height: 1;
    color: rgb(51 65 85);
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: rgba(255, 255, 255, 0.85);
}

.editor-btn:hover {
    color: rgb(15 23 42);
    background: white;
}

.editor-btn:disabled {
    cursor: not-allowed;
    opacity: 0.65;
}

.mode-btn {
    min-height: 28px;
    border-radius: 6px;
    padding: 0.3rem 0.65rem;
    font-size: 12px;
    line-height: 1;
    color: rgb(71 85 105);
}

.mode-btn-active {
    color: white;
    background: rgb(79 70 229);
    box-shadow: 0 8px 18px rgba(79, 70, 229, 0.18);
}

.blog-editor :deep(h2) {
    margin: 1.2rem 0 0.55rem;
    font-size: 1.45rem;
    line-height: 1.25;
    font-weight: 800;
    letter-spacing: -0.01em;
}

.blog-editor :deep(h3) {
    margin: 1rem 0 0.45rem;
    font-size: 1.12rem;
    line-height: 1.35;
    font-weight: 800;
}

.blog-editor :deep(p) {
    margin: 0 0 0.9rem;
}

.blog-editor :deep(ul),
.blog-editor :deep(ol) {
    margin: 0.8rem 0 1rem 1.35rem;
}

.blog-editor :deep(ul) {
    list-style: disc;
}

.blog-editor :deep(ol) {
    list-style: decimal;
}

.blog-editor :deep(blockquote) {
    margin: 1rem 0;
    border-left: 3px solid rgb(99 102 241);
    padding: 0.35rem 0 0.35rem 1rem;
    color: rgb(71 85 105);
    background: rgba(99, 102, 241, 0.06);
}

.blog-editor :deep(a) {
    color: rgb(79 70 229);
    text-decoration: underline;
}

.blog-editor :deep(figure) {
    margin: 1.2rem 0;
}

.blog-editor :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.35);
}

.blog-editor :deep(figcaption) {
    margin-top: 0.35rem;
    font-size: 12px;
    line-height: 1.5;
    color: rgb(100 116 139);
    text-align: center;
}
</style>
