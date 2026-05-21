<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';

defineProps({
    posts: { type: Array, default: () => [] },
});

</script>

<template>
    <Head title="AuctionBall Blog | Auction Tips, Tournament Guides & Product Updates">
        <meta name="description" content="Read AuctionBall guides, tournament auction tips, product updates, and practical playbooks for running cricket, football, and live player auctions." head-key="description" />
        <meta name="robots" content="index,follow" head-key="robots" />
    </Head>

    <div class="page-bg min-h-screen text-ink-900">
        <PublicHeader />

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
