<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';

const props = defineProps({
    posts: { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1 }) },
});

const { t } = useI18n();
const page = usePage();
const appDomain = computed(() => page.props.appDomain || 'auctionball.com');
const canonicalUrl = computed(() => `https://${appDomain.value}/blog`);
const visiblePosts = ref([...(props.posts?.data || [])]);
const isLoadingMore = ref(false);
const hasMorePosts = computed(() => (props.posts?.current_page || 1) < (props.posts?.last_page || 1));
const nextPage = computed(() => (props.posts?.current_page || 1) + 1);

watch(
    () => props.posts,
    (posts) => {
        const incoming = posts?.data || [];

        if ((posts?.current_page || 1) <= 1) {
            visiblePosts.value = [...incoming];
            return;
        }

        const seen = new Set(visiblePosts.value.map((post) => post.slug));
        visiblePosts.value = [
            ...visiblePosts.value,
            ...incoming.filter((post) => !seen.has(post.slug)),
        ];
    },
    { deep: true },
);

const loadMore = () => {
    if (!hasMorePosts.value || isLoadingMore.value) return;

    router.get('/blog', { page: nextPage.value }, {
        only: ['posts'],
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            isLoadingMore.value = true;
        },
        onFinish: () => {
            isLoadingMore.value = false;
        },
    });
};
</script>

<template>
    <Head :title="t('public_blog.seo_title')">
        <meta name="description" :content="t('public_blog.seo_description')" head-key="description" />
        <meta name="robots" content="index,follow" head-key="robots" />
        <link rel="canonical" :href="canonicalUrl" head-key="canonical" />
        <link rel="alternate" hreflang="en" :href="canonicalUrl + '?lang=en'" />
        <link rel="alternate" hreflang="bn" :href="canonicalUrl + '?lang=bn'" />
        <link rel="alternate" hreflang="x-default" :href="canonicalUrl" />
    </Head>

    <div class="page-bg min-h-screen text-ink-900">
        <PublicHeader />

        <main class="mx-auto max-w-6xl px-4 sm:px-6 py-14">
            <section class="max-w-3xl">
                <div class="font-mono text-[11px] tracking-widest text-brand-indigo uppercase mb-4">{{ t('public_blog.eyebrow') }}</div>
                <h1 class="text-[34px] sm:text-[48px] leading-tight font-extrabold tracking-tight">{{ t('public_blog.heading') }}</h1>
                <p class="mt-5 text-[17px] sm:text-[19px] leading-8 text-ink-600">
                    {{ t('public_blog.subtitle') }}
                </p>
            </section>

            <section class="mt-10 grid lg:grid-cols-3 gap-4">
                <article v-for="post in visiblePosts" :key="post.slug" class="overflow-hidden rounded-lg border border-ink-200/70 bg-white">
                    <Link v-if="post.featured_image_url" :href="post.url" class="block aspect-[16/9] bg-ink-100">
                        <img :src="post.featured_image_url" :alt="post.title" class="h-full w-full object-cover" loading="lazy" decoding="async" />
                    </Link>
                    <div class="p-6">
                        <div class="flex items-center justify-between gap-3 text-[11px] font-mono text-ink-500">
                            <span>{{ post.category || t('public_blog.uncategorized') }}</span>
                            <span>{{ post.read_time }}</span>
                        </div>
                        <h2 class="mt-5 text-[20px] font-bold tracking-tight leading-snug">
                            <Link :href="post.url" class="hover:text-brand-indigo">{{ post.title }}</Link>
                        </h2>
                        <p class="mt-3 text-[14.5px] leading-7 text-ink-600">{{ post.excerpt }}</p>
                        <div class="mt-6 flex items-center justify-between gap-3">
                            <div class="text-[12px] font-mono text-ink-500">
                                <span v-if="post.show_date !== false">{{ post.date }}</span>
                            </div>
                            <Link :href="post.url" class="text-[13px] font-semibold text-brand-indigo hover:text-brand-violet">{{ t('public_blog.read') }}</Link>
                        </div>
                    </div>
                </article>
                <div v-if="!visiblePosts.length" class="lg:col-span-3 rounded-lg border border-ink-200/70 bg-white p-8 text-center">
                    <h2 class="text-[22px] font-extrabold tracking-tight">{{ t('public_blog.empty_title') }}</h2>
                    <p class="mt-2 text-[14.5px] text-ink-600">{{ t('public_blog.empty_body') }}</p>
                </div>
            </section>

            <div v-if="hasMorePosts" class="mt-8 flex justify-center">
                <button
                    type="button"
                    class="btn-ghost px-6 py-3 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isLoadingMore"
                    @click="loadMore"
                >
                    {{ isLoadingMore ? t('public_blog.loading_more') : t('public_blog.load_more') }}
                </button>
            </div>

            <section class="mt-12 rounded-lg border border-ink-200/70 bg-white p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-5 justify-between">
                <div>
                    <h2 class="text-[22px] font-extrabold tracking-tight">{{ t('public_blog.help_title') }}</h2>
                    <p class="mt-2 text-[14.5px] text-ink-600">{{ t('public_blog.help_body') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link href="/help" class="btn-ghost px-5 py-3">{{ t('public_blog.help_center') }}</Link>
                    <Link href="/contact" class="btn-primary px-5 py-3">{{ t('public_blog.contact') }}</Link>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
