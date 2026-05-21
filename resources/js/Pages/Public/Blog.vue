<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LanguageToggle from '@/Components/LanguageToggle.vue';
import PublicFooter from '@/Components/PublicFooter.vue';

defineProps({
    posts: { type: Array, default: () => [] },
});

const appLogo = computed(() => usePage().props.appLogo);
const user = computed(() => usePage().props.auth?.user);
</script>

<template>
    <Head title="AuctionBall Blog | Auction Tips, Tournament Guides & Product Updates">
        <meta name="description" content="Read AuctionBall guides, tournament auction tips, product updates, and practical playbooks for running cricket, football, and live player auctions." head-key="description" />
        <meta name="robots" content="index,follow" head-key="robots" />
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
                    <Link href="/blog" class="font-semibold text-ink-900">Blog</Link>
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

        <main class="mx-auto max-w-6xl px-4 sm:px-6 py-14">
            <section class="max-w-3xl">
                <div class="font-mono text-[11px] tracking-widest text-brand-indigo uppercase mb-4">Blog</div>
                <h1 class="text-[34px] sm:text-[48px] leading-tight font-extrabold tracking-tight">AuctionBall Blog</h1>
                <p class="mt-5 text-[17px] sm:text-[19px] leading-8 text-ink-600">
                    Guides, product updates, and practical auction playbooks for organizers running cricket, football, and live player auctions.
                </p>
            </section>

            <section class="mt-10 grid lg:grid-cols-3 gap-4">
                <article v-for="post in posts" :key="post.slug" class="overflow-hidden rounded-lg border border-ink-200/70 bg-white">
                    <Link v-if="post.featured_image_url" :href="post.url" class="block aspect-[16/9] bg-ink-100">
                        <img :src="post.featured_image_url" :alt="post.title" class="h-full w-full object-cover" />
                    </Link>
                    <div class="p-6">
                        <div class="flex items-center justify-between gap-3 text-[11px] font-mono text-ink-500">
                            <span>{{ post.category || 'Uncategorized' }}</span>
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
                            <Link :href="post.url" class="text-[13px] font-semibold text-brand-indigo hover:text-brand-violet">Read</Link>
                        </div>
                    </div>
                </article>
                <div v-if="!posts.length" class="lg:col-span-3 rounded-lg border border-ink-200/70 bg-white p-8 text-center">
                    <h2 class="text-[22px] font-extrabold tracking-tight">No posts published yet</h2>
                    <p class="mt-2 text-[14.5px] text-ink-600">New auction guides will appear here after they are published.</p>
                </div>
            </section>

            <section class="mt-12 rounded-lg border border-ink-200/70 bg-white p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-5 justify-between">
                <div>
                    <h2 class="text-[22px] font-extrabold tracking-tight">Need help preparing an auction?</h2>
                    <p class="mt-2 text-[14.5px] text-ink-600">Use the help center or contact support before event day.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link href="/help" class="btn-ghost px-5 py-3">Help center</Link>
                    <Link href="/contact" class="btn-primary px-5 py-3">Contact</Link>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
