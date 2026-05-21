<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';

const props = defineProps({
    status: { type: Number, default: 404 },
});

const pageCopy = computed(() => {
    if (props.status === 403) {
        return {
            title: 'Access not allowed',
            eyebrow: '403',
            body: 'This page is private or your account does not have permission to open it.',
        };
    }

    if (props.status === 500) {
        return {
            title: 'Something went wrong',
            eyebrow: '500',
            body: 'The server hit a problem while loading this page. Try again in a moment.',
        };
    }

    if (props.status === 503) {
        return {
            title: 'Temporarily unavailable',
            eyebrow: '503',
            body: 'AuctionBall is temporarily unavailable while maintenance or deployment finishes.',
        };
    }

    return {
        title: 'Page not found',
        eyebrow: '404',
        body: 'The page may have moved, the link may be old, or the auction resource may no longer be available.',
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
                    <Link href="/" class="btn-primary px-5 py-3">Go home</Link>
                    <Link href="/blog" class="btn-ghost px-5 py-3">Read blog</Link>
                    <Link href="/contact" class="btn-ghost px-5 py-3">Contact support</Link>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
