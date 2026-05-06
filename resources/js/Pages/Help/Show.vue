<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import LanguageToggle from '@/Components/LanguageToggle.vue';

const props = defineProps({
    toc:  { type: Array, default: () => [] },
    doc:  { type: Object, default: null },
    prev: { type: Object, default: null },
    next: { type: Object, default: null },
});

const page = usePage();
const appLogo   = computed(() => page.props.appLogo);
const appDomain = computed(() => page.props.appDomain || 'auctionball.com');

const search = ref('');
const filteredToc = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (! q) return props.toc;
    return props.toc
        .map(g => ({
            group: g.group,
            items: g.items.filter(it => it.title.toLowerCase().includes(q)),
        }))
        .filter(g => g.items.length > 0);
});

// SEO: every help page is indexable on its own URL.
const seoTitle = computed(() => props.doc ? `${props.doc.title} — AuctionBall help` : 'AuctionBall help');
const seoDesc  = computed(() => props.doc?.body
    ? props.doc.body.replace(/[#*`>_\-\[\]\(\)]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 160)
    : 'Step-by-step guides for setting up tournaments, running live auctions, and managing teams on AuctionBall.');

const sidebarOpen = ref(false);
</script>

<template>
    <Head :title="seoTitle">
        <meta name="description" :content="seoDesc" head-key="description" />
        <link rel="canonical" :href="`https://${appDomain}/help` + (doc ? '/' + doc.slug : '')" head-key="canonical" />
        <meta property="og:title" :content="seoTitle" head-key="og:title" />
        <meta property="og:description" :content="seoDesc" head-key="og:description" />
        <meta name="robots" content="index,follow" head-key="robots" />
    </Head>

    <div class="page-bg min-h-screen flex flex-col">
        <!-- ============== TOP BAR ============== -->
        <header class="sticky top-0 z-30 bg-white/70 backdrop-blur-md border-b border-ink-200/40">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 py-3 flex items-center gap-3">
                <button class="lg:hidden p-2 -ml-2" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle docs sidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>
                <Link href="/" class="flex items-center gap-2.5">
                    <img v-if="appLogo" :src="appLogo" alt="AuctionBall" class="h-9 w-9 rounded-lg object-contain bg-white border border-ink-200/40" />
                    <span v-else class="grid place-items-center h-9 w-9 rounded-lg bg-gradient-brand shadow-cta">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M8 8h5"/></svg>
                    </span>
                    <span class="font-semibold text-[16px] tracking-tight">AuctionBall</span>
                    <span class="hidden sm:inline-block font-mono text-[11px] tracking-widest text-ink-500 ml-2 px-2 py-0.5 rounded-full border border-ink-200/60">DOCS</span>
                </Link>
                <div class="ml-auto flex items-center gap-2">
                    <LanguageToggle />
                    <Link href="/login" class="hidden sm:inline-flex btn-ghost text-[13px] py-2 px-3">Log in</Link>
                    <Link href="/register" class="btn-primary text-[13px] py-2 px-3">Get started</Link>
                </div>
            </div>
        </header>

        <div class="flex flex-1 min-h-0 mx-auto max-w-7xl w-full">
            <!-- ============== SIDEBAR (desktop, sticky) ==============
                 Stays pinned beneath the page header (≈60px) and runs the full
                 viewport height with its own internal scroll. The search box
                 inside is also sticky-to-top so it never disappears even when
                 the TOC list is long. -->
            <aside class="hidden lg:block w-64 shrink-0 border-r border-ink-200/60 bg-white/40 self-start sticky top-[80px] h-[calc(100vh-80px)] mt-5">
                <div class="flex flex-col h-full px-4 py-5">
                    <div class="sticky top-0 z-10 -mx-4 px-4 pb-3 bg-white/40 backdrop-blur-md border-b border-ink-200/40">
                        <input v-model="search" type="search" placeholder="Search the docs…"
                               class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    </div>
                    <nav class="flex-1 overflow-y-auto space-y-5 pt-4 pb-4 scroll-smooth">
                        <div v-for="g in filteredToc" :key="g.group">
                            <div class="px-2 pb-1.5 font-mono text-[10px] tracking-widest text-ink-400 uppercase">{{ g.group }}</div>
                            <ul class="space-y-0.5">
                                <li v-for="it in g.items" :key="it.slug">
                                    <Link :href="`/help/${it.slug}`"
                                          class="block px-2.5 py-1.5 rounded-lg text-[13.5px] transition"
                                          :class="doc && doc.slug === it.slug
                                              ? 'bg-white shadow-card text-ink-900 font-semibold'
                                              : 'text-ink-600 hover:bg-white/60 hover:text-ink-900'">
                                        {{ it.title }}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <div v-if="filteredToc.length === 0" class="px-2 text-[12.5px] text-ink-500">No docs match.</div>
                    </nav>
                </div>
            </aside>

            <!-- ============== MOBILE DRAWER ============== -->
            <div v-if="sidebarOpen" class="lg:hidden fixed inset-0 z-40">
                <div class="absolute inset-0 bg-ink-900/30" @click="sidebarOpen = false"></div>
                <aside class="absolute inset-y-0 left-0 w-72 bg-white p-4 shadow-xl overflow-y-auto">
                    <input v-model="search" type="search" placeholder="Search the docs…"
                           class="mb-5 w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    <div v-for="g in filteredToc" :key="g.group" class="mb-5">
                        <div class="px-2 pb-1.5 font-mono text-[10px] tracking-widest text-ink-400 uppercase">{{ g.group }}</div>
                        <ul class="space-y-0.5">
                            <li v-for="it in g.items" :key="it.slug">
                                <Link :href="`/help/${it.slug}`" @click="sidebarOpen = false"
                                      class="block px-2.5 py-1.5 rounded-lg text-[13.5px]"
                                      :class="doc && doc.slug === it.slug ? 'bg-ink-100 font-semibold' : 'text-ink-600'">
                                    {{ it.title }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>

            <!-- ============== MAIN ============== -->
            <main class="flex-1 min-w-0 px-4 sm:px-8 py-8 sm:py-12">
                <article v-if="doc" class="max-w-3xl mx-auto">
                    <!-- Breadcrumb -->
                    <nav class="text-[12px] font-mono tracking-wide text-ink-500 mb-4 flex gap-1.5 items-center">
                        <Link href="/help" class="hover:underline">Help</Link>
                        <span>/</span>
                        <span class="text-ink-700">{{ doc.group }}</span>
                        <span>/</span>
                        <span class="text-ink-900">{{ doc.title }}</span>
                    </nav>

                    <!-- Markdown content — styled via .prose (Tailwind typography-ish) -->
                    <div class="prose prose-ink max-w-none" v-html="doc.html"></div>

                    <!-- Prev / Next -->
                    <div v-if="prev || next" class="mt-12 pt-6 border-t border-ink-200/60 grid sm:grid-cols-2 gap-3">
                        <Link v-if="prev" :href="`/help/${prev.slug}`" class="block rounded-xl border border-ink-200/60 bg-white/60 hover:bg-white px-5 py-4 transition">
                            <div class="font-mono text-[10.5px] tracking-widest text-ink-500">← PREVIOUS</div>
                            <div class="text-[14px] font-semibold text-ink-900 mt-0.5">{{ prev.title }}</div>
                        </Link>
                        <span v-else></span>
                        <Link v-if="next" :href="`/help/${next.slug}`" class="block sm:text-right rounded-xl border border-ink-200/60 bg-white/60 hover:bg-white px-5 py-4 transition">
                            <div class="font-mono text-[10.5px] tracking-widest text-ink-500">NEXT →</div>
                            <div class="text-[14px] font-semibold text-ink-900 mt-0.5">{{ next.title }}</div>
                        </Link>
                    </div>

                    <p class="mt-8 text-center text-[12.5px] text-ink-500">
                        Still stuck? <a href="mailto:support@auctionball.com" class="text-brand-indigo hover:underline">Email support</a>
                        — we usually reply within a few hours.
                    </p>
                </article>

                <div v-else class="max-w-2xl mx-auto text-center py-20">
                    <h1 class="text-[28px] font-extrabold tracking-tight">Docs are loading...</h1>
                    <p class="mt-3 text-ink-500">If this stays empty, no markdown files are present in <code>resources/docs/{en,bn}</code>.</p>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
/* Lightweight prose styles — match the app's Mona-Sans aesthetic without
   pulling in @tailwindcss/typography (extra ~20kb). */
.prose :deep(h1) { font-size: 32px; font-weight: 800; letter-spacing: -0.02em; line-height: 1.15; margin: 0 0 1rem; }
.prose :deep(h2) { font-size: 22px; font-weight: 700; letter-spacing: -0.015em; line-height: 1.25; margin: 2rem 0 0.75rem; }
.prose :deep(h3) { font-size: 17px; font-weight: 700; line-height: 1.3; margin: 1.5rem 0 0.5rem; }
.prose :deep(p)  { font-size: 15.5px; line-height: 1.7; color: rgb(51 65 85); margin: 0.85rem 0; }
.prose :deep(ul), .prose :deep(ol) { padding-left: 1.4rem; margin: 0.85rem 0; }
.prose :deep(li) { font-size: 15.5px; line-height: 1.7; color: rgb(51 65 85); margin: 0.3rem 0; }
.prose :deep(strong) { color: rgb(15 23 42); font-weight: 600; }
.prose :deep(a)  { color: rgb(99 102 241); text-decoration: underline; text-underline-offset: 2px; }
.prose :deep(a:hover) { color: rgb(67 56 202); }
.prose :deep(code) { font-family: 'JetBrains Mono', monospace; font-size: 13px; background: rgb(241 245 249); color: rgb(15 23 42); padding: 0.1rem 0.4rem; border-radius: 4px; }
.prose :deep(pre) { background: rgb(15 23 42); color: rgb(226 232 240); padding: 1rem 1.25rem; border-radius: 12px; overflow-x: auto; font-size: 13px; line-height: 1.6; margin: 1rem 0; }
.prose :deep(pre code) { background: transparent; color: inherit; padding: 0; font-size: 13px; }
.prose :deep(blockquote) { border-left: 3px solid rgb(99 102 241); padding: 0.4rem 1rem; margin: 1rem 0; background: rgb(238 242 255 / 0.5); border-radius: 0 8px 8px 0; }
.prose :deep(blockquote p) { margin: 0.4rem 0; color: rgb(67 56 202); font-style: normal; }
.prose :deep(table) { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 14px; }
.prose :deep(th) { text-align: left; padding: 0.5rem 0.75rem; background: rgb(248 250 252); font-weight: 600; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(100 116 139); border-bottom: 2px solid rgb(226 232 240); }
.prose :deep(td) { padding: 0.5rem 0.75rem; border-bottom: 1px solid rgb(226 232 240); vertical-align: top; }
.prose :deep(hr) { border: none; border-top: 1px solid rgb(226 232 240); margin: 2rem 0; }
.prose :deep(.heading-anchor) { color: rgb(148 163 184); text-decoration: none; }
.prose :deep(.heading-anchor:hover) { color: rgb(99 102 241); }
.prose :deep(input[type=checkbox]) { margin-right: 0.4rem; }
</style>
