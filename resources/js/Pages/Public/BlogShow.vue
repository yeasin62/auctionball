<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';

const props = defineProps({
    post: { type: Object, required: true },
    recentPosts: { type: Array, default: () => [] },
});

const { t } = useI18n();
const metaTitle = computed(() => props.post.meta_title || `${props.post.title} | AuctionBall Blog`);
const metaDescription = computed(() => props.post.meta_description || props.post.excerpt || t('public_blog.post_fallback_description'));
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
        <PublicHeader />

        <main class="mx-auto grid max-w-7xl gap-8 px-4 py-14 sm:px-6 lg:grid-cols-[minmax(0,1fr)_280px]">
            <article class="order-1 min-w-0">
                <div class="mx-auto max-w-3xl">
                    <div class="flex flex-wrap items-center gap-3 text-[12px] font-mono text-ink-500">
                        <span v-if="post.category" class="text-brand-indigo">{{ post.category }}</span>
                        <span v-if="post.show_date !== false && post.date">{{ post.date }}</span>
                        <span v-if="post.read_time">{{ post.read_time }}</span>
                    </div>
                    <h1 class="mt-4 text-[34px] sm:text-[48px] leading-tight font-extrabold tracking-tight">{{ post.title }}</h1>
                    <p v-if="post.excerpt" class="mt-5 text-[17px] sm:text-[19px] leading-8 text-ink-600">{{ post.excerpt }}</p>

                    <figure v-if="post.featured_image_url" class="mt-8 overflow-hidden rounded-xl border border-ink-200/70 bg-white">
                        <img :src="post.featured_image_url" :alt="post.title" class="h-auto w-full object-cover" />
                    </figure>

                    <div class="blog-body mt-10 rounded-lg border border-ink-200/70 bg-white p-6 sm:p-8" v-html="bodyHtml"></div>
                </div>
            </article>

            <aside class="order-2">
                <div class="sticky top-24 rounded-xl border border-ink-200/70 bg-white/85 p-5 shadow-sm">
                    <Link href="/blog" class="font-mono text-[12px] text-ink-500 hover:text-ink-900">{{ t('public_blog.back_to_blog') }}</Link>
                    <div v-if="recentPosts.length" class="mt-6">
                        <h2 class="text-[15px] font-extrabold tracking-tight text-ink-900">Recent posts</h2>
                        <div class="mt-4 space-y-3">
                            <Link v-for="recent in recentPosts" :key="recent.slug" :href="recent.url" class="block rounded-lg border border-ink-200/70 bg-white p-3 transition hover:border-brand-indigo/30 hover:shadow-sm">
                                <img v-if="recent.featured_image_url" :src="recent.featured_image_url" alt="" class="mb-2 h-20 w-full rounded-md object-cover" />
                                <div class="text-[13px] font-bold leading-5 text-ink-900">{{ recent.title }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 font-mono text-[10.5px] text-ink-400">
                                    <span v-if="recent.category">{{ recent.category }}</span>
                                    <span v-if="recent.show_date !== false && recent.date">{{ recent.date }}</span>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </aside>
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
