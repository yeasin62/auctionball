<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const appLogo = computed(() => usePage().props.appLogo);

const footerCols = computed(() => [
    { title: t('landing.footer.product'), links: [
        { label: t('landing.footer.product_l1'), href: '/features' },
        { label: t('landing.footer.product_l2'), href: '/pricing' },
        { label: t('landing.footer.product_l3'), href: '/live-demo' },
        { label: t('landing.footer.product_l4'), href: '/changelog' },
        { label: t('landing.footer.product_l5'), href: '/roadmap' },
        { label: 'Blog', href: '/blog' },
    ] },
    { title: t('landing.footer.docs'), links: [
        { label: t('landing.footer.docs_l1'), href: '/getting-started' },
        { label: t('landing.footer.docs_l2'), href: '/auction-guide' },
        { label: t('landing.footer.docs_l3'), href: '/big-screen-setup' },
        { label: t('landing.footer.docs_l4'), href: '/team-device-guide' },
    ] },
    { title: t('landing.footer.support'), links: [
        { label: t('landing.footer.support_l1'), href: '/help' },
        { label: t('landing.footer.support_l2'), href: '/community' },
        { label: t('landing.footer.support_l3'), href: '/status' },
        { label: t('landing.footer.support_l4'), href: '/contact' },
    ] },
    { title: t('landing.footer.legal'), links: [
        { label: t('landing.footer.legal_l1'), href: '/terms' },
        { label: t('landing.footer.legal_l2'), href: '/privacy' },
        { label: t('landing.footer.legal_l3'), href: '/refunds' },
        { label: t('landing.footer.legal_l4'), href: '/acceptable-use' },
    ] },
]);
</script>

<template>
    <footer class="relative">
        <div class="mx-auto max-w-7xl px-6 pb-12">
            <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-[1.6fr_repeat(4,1fr)]">
                <div>
                    <div class="flex items-center gap-2.5">
                        <img v-if="appLogo" :src="appLogo" alt="AuctionBall" class="h-9 w-9 rounded-lg object-contain bg-white border border-ink-200/40" />
                        <span v-else class="grid place-items-center h-9 w-9 rounded-lg bg-gradient-brand shadow-cta">
                            <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                <rect x="4" y="4" width="16" height="16" rx="3"/>
                                <path d="M8 12h8M8 8h5"/>
                            </svg>
                        </span>
                        <span class="font-semibold text-[17px] tracking-tight">AuctionBall</span>
                    </div>
                    <p class="mt-4 max-w-xs text-[13px] text-ink-500 leading-relaxed">
                        {{ t('landing.footer.about') }}
                    </p>
                </div>

                <div v-for="col in footerCols" :key="col.title">
                    <div class="font-mono text-[11px] tracking-widest text-ink-500 mb-4">{{ col.title }}</div>
                    <ul class="space-y-3 text-[13.5px] text-ink-700">
                        <li v-for="l in col.links" :key="l.href"><Link :href="l.href" class="hover:text-ink-900">{{ l.label }}</Link></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 pt-6 border-t border-ink-200/60 flex flex-wrap justify-between gap-4 text-[12px] font-mono text-ink-500">
                <span>{{ t('landing.footer.copyright') }}</span>
            </div>
        </div>
    </footer>
</template>
