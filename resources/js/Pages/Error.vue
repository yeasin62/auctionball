<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';

const props = defineProps({
    status: { type: Number, default: 404 },
});

const { t } = useI18n();
const pageCopy = computed(() => {
    if (props.status === 403) {
        return {
            title: t('error_page.403_title'),
            eyebrow: '403',
            body: t('error_page.403_body'),
        };
    }

    if (props.status === 500) {
        return {
            title: t('error_page.500_title'),
            eyebrow: '500',
            body: t('error_page.500_body'),
        };
    }

    if (props.status === 503) {
        return {
            title: t('error_page.503_title'),
            eyebrow: '503',
            body: t('error_page.503_body'),
        };
    }

    return {
        title: t('error_page.404_title'),
        eyebrow: '404',
        body: t('error_page.404_body'),
    };
});
</script>

<template>
    <Head :title="`${pageCopy.eyebrow} | AuctionBall`">
        <meta name="robots" content="noindex,follow" head-key="robots" />
    </Head>

    <div class="page-bg min-h-screen text-ink-900">
        <PublicHeader />

        <main class="mx-auto grid min-h-[calc(100vh-170px)] max-w-6xl place-items-center px-4 py-16 sm:px-6">
            <section class="w-full max-w-3xl text-center">
                <div class="mx-auto mb-6 inline-flex h-20 w-20 items-center justify-center rounded-2xl border border-brand-indigo/20 bg-white/80 shadow-soft">
                    <span class="font-mono text-[28px] font-black text-brand-indigo">{{ pageCopy.eyebrow }}</span>
                </div>
                <h1 class="text-[38px] font-extrabold leading-tight tracking-tight sm:text-[56px]">{{ pageCopy.title }}</h1>
                <p class="mx-auto mt-5 max-w-2xl text-[17px] leading-8 text-ink-600">{{ pageCopy.body }}</p>

                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <Link href="/" class="btn-primary px-5 py-3">{{ t('error_page.go_home') }}</Link>
                    <Link href="/blog" class="btn-ghost px-5 py-3">{{ t('error_page.read_blog') }}</Link>
                    <Link href="/contact" class="btn-ghost px-5 py-3">{{ t('error_page.contact_support') }}</Link>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
