<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LanguageToggle from '@/Components/LanguageToggle.vue';
import PublicFooter from '@/Components/PublicFooter.vue';

const props = defineProps({
    post: { type: Object, required: true },
});

const appLogo = computed(() => usePage().props.appLogo);
const user = computed(() => usePage().props.auth?.user);
const metaTitle = computed(() => props.post.meta_title || `${props.post.title} | AuctionBall Blog`);
const metaDescription = computed(() => props.post.meta_description || props.post.excerpt || 'AuctionBall blog article for auction organizers.');
const bodyHtml = computed(() => {
    const body = String(props.post.body || '').trim();
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
});
</script>

<template>
    <Head :title="metaTitle">
        <meta name="description" :content="metaDescription" head-key="description" />
        <meta name="robots" content="index,follow" head-key="robots" />
        <component :is="'script'" v-if="post.schema_json" type="application/ld+json" head-key="blog-schema" v-text="post.schema_json" />
    </Head>

    <div class="page-bg min-h-screen text-ink-900">
        <header class="sticky top-0 z-30 bg-white/75 backdrop-blur-md border-b border-ink-200/50">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 py-3 flex items-center gap-3">
                <Link href="/" class="flex items-center gap-2.5">
                    <img v-if="appLogo" :src="appLogo" alt="AuctionBall" class="h-9 w-9 rounded-lg object-contain bg-white border border-ink-200/40" />
                    <span v-else class="grid place-items-center h-9 w-9 rounded-lg bg-gradient-brand shadow-cta">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8"/></svg>
                    </span>
                    <span class="font-semibold text-[16px] tracking-tight">AuctionBall</span>
                </Link>
                <nav class="hidden md:flex items-center gap-5 ml-8 text-[13px] text-ink-600">
                    <Link href="/features" class="hover:text-ink-900">Features</Link>
                    <Link href="/pricing" class="hover:text-ink-900">Pricing</Link>
                    <Link href="/blog" class="hover:text-ink-900">Blog</Link>
                    <Link href="/help" class="hover:text-ink-900">Help</Link>
                </nav>
                <div class="ml-auto flex items-center gap-2">
                    <LanguageToggle />
                    <Link v-if="user" href="/dashboard" class="btn-primary text-[13px] py-2 px-3">Dashboard</Link>
                    <template v-else>
                        <Link href="/login" class="hidden sm:inline-flex btn-ghost text-[13px] py-2 px-3">Log in</Link>
                        <Link href="/register" class="btn-primary text-[13px] py-2 px-3">Start free</Link>
                    </template>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 sm:px-6 py-14">
            <Link href="/blog" class="font-mono text-[12px] text-ink-500 hover:text-ink-900">Back to blog</Link>
            <div class="mt-6 flex flex-wrap items-center gap-3 text-[12px] font-mono text-ink-500">
                <span v-if="post.category" class="text-brand-indigo">{{ post.category }}</span>
                <span v-if="post.date">{{ post.date }}</span>
                <span v-if="post.read_time">{{ post.read_time }}</span>
            </div>
            <h1 class="mt-4 text-[34px] sm:text-[48px] leading-tight font-extrabold tracking-tight">{{ post.title }}</h1>
            <p v-if="post.excerpt" class="mt-5 text-[17px] sm:text-[19px] leading-8 text-ink-600">{{ post.excerpt }}</p>

            <figure v-if="post.featured_image_url" class="mt-8 overflow-hidden rounded-xl border border-ink-200/70 bg-white">
                <img :src="post.featured_image_url" :alt="post.title" class="h-auto w-full object-cover" />
            </figure>

            <article class="blog-body mt-10 rounded-lg border border-ink-200/70 bg-white p-6 sm:p-8" v-html="bodyHtml"></article>
        </main>

        <PublicFooter />
    </div>
</template>

<style scoped>
.blog-body {
    font-size: 16px;
    line-height: 1.85;
    color: rgb(51 65 85);
}

.blog-body :deep(h2) {
    margin: 1.7rem 0 0.75rem;
    font-size: 1.65rem;
    line-height: 1.25;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: rgb(15 23 42);
}

.blog-body :deep(h3) {
    margin: 1.35rem 0 0.55rem;
    font-size: 1.2rem;
    line-height: 1.35;
    font-weight: 800;
    color: rgb(15 23 42);
}

.blog-body :deep(p) {
    margin: 0 0 1.15rem;
}

.blog-body :deep(ul),
.blog-body :deep(ol) {
    margin: 0.9rem 0 1.25rem 1.35rem;
    padding-left: 0.35rem;
}

.blog-body :deep(ul) {
    list-style: disc;
}

.blog-body :deep(ol) {
    list-style: decimal;
}

.blog-body :deep(li) {
    margin: 0.35rem 0;
}

.blog-body :deep(blockquote) {
    margin: 1.4rem 0;
    border-left: 4px solid rgb(99 102 241);
    border-radius: 6px;
    padding: 0.55rem 0 0.55rem 1.1rem;
    color: rgb(71 85 105);
    background: rgba(99, 102, 241, 0.06);
}

.blog-body :deep(a) {
    color: rgb(79 70 229);
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.blog-body :deep(figure) {
    margin: 1.8rem 0;
}

.blog-body :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.35);
}

.blog-body :deep(figcaption) {
    margin-top: 0.45rem;
    font-size: 13px;
    line-height: 1.5;
    color: rgb(100 116 139);
    text-align: center;
}

.blog-body :deep(strong),
.blog-body :deep(b) {
    color: rgb(15 23 42);
}
</style>
