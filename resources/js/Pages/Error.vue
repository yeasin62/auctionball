<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LanguageToggle from '@/Components/LanguageToggle.vue';
import PublicFooter from '@/Components/PublicFooter.vue';

const props = defineProps({
    status: { type: Number, default: 404 },
});

const page = usePage();
const appLogo = computed(() => page.props.appLogo);
const user = computed(() => page.props.auth?.user);

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
        <header class="sticky top-0 z-30 bg-white/75 backdrop-blur-md border-b border-ink-200/50">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 py-3 flex items-center gap-3">
                <Link href="/" class="flex items-center gap-2.5">
                    <img v-if="appLogo" :src="appLogo" alt="AuctionBall" class="h-9 w-9 rounded-lg object-contain bg-white border border-ink-200/40" />
                    <span v-else class="grid place-items-center h-9 w-9 rounded-lg bg-gradient-brand shadow-cta">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M8 8h5"/></svg>
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
                    <template v-if="user">
                        <Link href="/logout" method="post" as="button" type="button" class="hidden sm:inline-flex btn-ghost text-[13px] py-2 px-3">Log out</Link>
                        <Link href="/dashboard" class="btn-primary text-[13px] py-2 px-3">Dashboard</Link>
                    </template>
                    <template v-else>
                        <Link href="/login" class="hidden sm:inline-flex btn-ghost text-[13px] py-2 px-3">Log in</Link>
                        <Link href="/register" class="btn-primary text-[13px] py-2 px-3">Start free</Link>
                    </template>
                </div>
            </div>
        </header>

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
