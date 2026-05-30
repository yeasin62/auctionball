<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';

const props = defineProps({
    page: { type: Object, required: true },
});

const seoTitle = computed(() => `${props.page.title} | AuctionBall`);
const hasDocs = computed(() => Array.isArray(props.page.doc_sections) && props.page.doc_sections.length > 0);
const inertiaPage = usePage();
const appDomain = computed(() => inertiaPage.props.appDomain || 'auctionball.com');
const canonicalUrl = computed(() => `https://${appDomain.value}/${props.page.slug}`);
const { t } = useI18n();
const videoEmbedUrl = computed(() => props.page.video?.youtube_id
    ? `https://www.youtube.com/embed/${props.page.video.youtube_id}`
    : null);
</script>

<template>
    <Head :title="seoTitle">
        <meta name="description" :content="page.description" head-key="description" />
        <meta name="robots" content="index,follow" head-key="robots" />
        <link rel="canonical" :href="canonicalUrl" head-key="canonical" />
        <link rel="alternate" hreflang="en" :href="canonicalUrl + '?lang=en'" />
        <link rel="alternate" hreflang="bn" :href="canonicalUrl + '?lang=bn'" />
        <link rel="alternate" hreflang="x-default" :href="canonicalUrl" />
    </Head>

    <div class="page-bg min-h-screen text-ink-900">
        <PublicHeader />

        <main>
            <section class="mx-auto max-w-6xl px-4 sm:px-6 py-14 sm:py-18">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <div class="font-mono text-[11px] tracking-widest text-brand-indigo uppercase">{{ page.eyebrow }}</div>
                        <div v-if="page.updated_at" class="font-mono text-[11px] text-ink-500">{{ page.updated_at }}</div>
                    </div>
                    <h1 class="text-[34px] sm:text-[48px] leading-tight font-extrabold tracking-tight">{{ page.title }}</h1>
                    <p class="mt-5 text-[17px] sm:text-[19px] leading-8 text-ink-600">{{ page.description }}</p>
                </div>
            </section>

            <section v-if="videoEmbedUrl" class="mx-auto max-w-6xl px-4 sm:px-6 pb-12">
                <div class="rounded-lg border border-ink-200/70 bg-white p-4 sm:p-5 shadow-sm">
                    <div v-if="page.video.title || page.video.description" class="mb-4">
                        <h2 v-if="page.video.title" class="text-[22px] sm:text-[28px] font-extrabold tracking-tight">{{ page.video.title }}</h2>
                        <p v-if="page.video.description" class="mt-2 text-[14.5px] sm:text-[15.5px] leading-7 text-ink-600">{{ page.video.description }}</p>
                    </div>
                    <div class="aspect-video overflow-hidden rounded-lg bg-ink-950">
                        <iframe
                            class="h-full w-full"
                            :src="videoEmbedUrl"
                            :title="page.video.title || page.title"
                            loading="lazy"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen></iframe>
                    </div>
                </div>
            </section>

            <section class="border-y border-ink-200/60 bg-white/55">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 py-10 grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <article v-for="section in page.sections" :key="section[0]" class="rounded-lg border border-ink-200/70 bg-white p-6">
                        <h2 class="text-[18px] font-bold tracking-tight">{{ section[0] }}</h2>
                        <p class="mt-3 text-[14.5px] leading-7 text-ink-600">{{ section[1] }}</p>
                    </article>
                </div>
            </section>

            <section v-if="hasDocs" class="mx-auto max-w-6xl px-4 sm:px-6 py-12 lg:py-14 grid lg:grid-cols-12 gap-8">
                <aside class="lg:col-span-3">
                    <div class="lg:sticky lg:top-24 rounded-lg border border-ink-200/70 bg-white p-5">
                        <div class="font-mono text-[11px] tracking-widest text-ink-500 uppercase mb-4">{{ t('public_page.contents') }}</div>
                        <nav class="space-y-2">
                            <a v-for="section in page.doc_sections"
                               :key="section.title"
                               :href="`#${section.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}`"
                               class="block text-[13px] leading-5 text-ink-600 hover:text-brand-indigo">
                                {{ section.title }}
                            </a>
                        </nav>
                    </div>
                </aside>

                <div class="lg:col-span-9 space-y-5">
                    <article v-for="section in page.doc_sections"
                             :id="section.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')"
                             :key="section.title"
                             class="rounded-lg border border-ink-200/70 bg-white p-6 sm:p-7 scroll-mt-28">
                        <h2 class="text-[22px] sm:text-[26px] font-extrabold tracking-tight leading-tight">{{ section.title }}</h2>
                        <p class="mt-3 text-[15.5px] leading-8 text-ink-600">{{ section.body }}</p>
                        <ul v-if="section.items?.length" class="mt-5 space-y-3">
                            <li v-for="item in section.items" :key="item" class="flex gap-3 text-[14.5px] leading-7 text-ink-700">
                                <span class="mt-2 h-2 w-2 rounded-full bg-brand-indigo shrink-0"></span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 sm:px-6 py-12 grid lg:grid-cols-12 gap-8" :class="hasDocs ? 'pt-0' : ''">
                <div class="lg:col-span-7">
                    <h2 class="text-[24px] font-extrabold tracking-tight">{{ page.practice_title || t('public_page.practice_title') }}</h2>
                    <p class="mt-4 text-[15.5px] leading-8 text-ink-600">
                        {{ page.practice_body || t('public_page.practice_body') }}
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link href="/register" class="btn-primary px-5 py-3">{{ t('nav.start_free') }}</Link>
                        <Link href="/contact" class="btn-ghost px-5 py-3">{{ t('public_page.contact_support') }}</Link>
                    </div>
                </div>
                <aside class="lg:col-span-5 rounded-lg border border-ink-200/70 bg-white p-6">
                    <div class="font-mono text-[11px] tracking-widest text-ink-500 uppercase mb-4">{{ t('public_page.checklist') }}</div>
                    <ul class="space-y-3">
                        <li v-for="item in page.checklist" :key="item" class="flex gap-3 text-[14px] text-ink-700">
                            <span class="mt-1 h-2 w-2 rounded-full bg-brand-indigo shrink-0"></span>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </aside>
            </section>
        </main>

        <PublicFooter />
        <!--
        <footer class="border-t border-ink-200/60 bg-white/60">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 py-8 flex flex-wrap justify-between gap-4 text-[12px] font-mono text-ink-500">
                <span>© 2026 AuctionBall</span>
                <span>{{ appDomain }}</span>
            </div>
        </footer>
        -->
    </div>
</template>
