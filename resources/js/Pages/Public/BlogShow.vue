<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LanguageToggle from '@/Components/LanguageToggle.vue';
import PublicFooter from '@/Components/PublicFooter.vue';

const props = defineProps({
    post: { type: Object, required: true },
});

const appLogo = computed(() => usePage().props.appLogo);
const paragraphs = computed(() => String(props.post.body || '').split(/\n{2,}/).map((p) => p.trim()).filter(Boolean));
const metaTitle = computed(() => props.post.meta_title || `${props.post.title} | AuctionBall Blog`);
const metaDescription = computed(() => props.post.meta_description || props.post.excerpt || 'AuctionBall blog article for auction organizers.');
</script>

<template>
    <Head :title="metaTitle">
        <meta name="description" :content="metaDescription" head-key="description" />
        <meta name="robots" content="index,follow" head-key="robots" />
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
                    <Link href="/login" class="hidden sm:inline-flex btn-ghost text-[13px] py-2 px-3">Log in</Link>
                    <Link href="/register" class="btn-primary text-[13px] py-2 px-3">Start free</Link>
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

            <article class="mt-10 rounded-lg border border-ink-200/70 bg-white p-6 sm:p-8">
                <p v-for="paragraph in paragraphs" :key="paragraph" class="mb-6 last:mb-0 text-[16px] leading-8 text-ink-700 whitespace-pre-line">
                    {{ paragraph }}
                </p>
            </article>
        </main>

        <PublicFooter />
    </div>
</template>
