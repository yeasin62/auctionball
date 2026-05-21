<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import LanguageToggle from '@/Components/LanguageToggle.vue';

const props = defineProps({
    home: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();
const appLogo = computed(() => page.props.appLogo);
const authUser = computed(() => page.props.auth?.user);

const navLinks = computed(() => [
    { key: 'nav.home', href: '/' },
    { key: 'nav.how_it_works', href: props.home ? '#how-it-works' : '/#how-it-works' },
    { key: 'nav.features', href: props.home ? '#features' : '/#features' },
    { key: 'nav.big_screen_preview', href: props.home ? '#big-screen' : '/#big-screen' },
    { key: 'nav.pricing', href: props.home ? '#pricing' : '/#pricing' },
    { key: 'nav.blog', href: '/blog' },
    { key: 'nav.faq', href: props.home ? '#faq' : '/#faq' },
]);
</script>

<template>
    <header class="sticky top-0 z-30 bg-white/70 backdrop-blur-md border-b border-ink-200/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2.5">
                <slot name="before-brand" />
                <Link href="/" class="flex min-w-0 items-center gap-2.5">
                    <img v-if="appLogo" :src="appLogo" alt="AuctionBall" class="h-9 w-9 rounded-lg object-contain bg-white border border-ink-200/40" />
                    <span v-else class="grid place-items-center h-9 w-9 rounded-lg bg-gradient-brand shadow-cta">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                            <rect x="4" y="4" width="16" height="16" rx="3"/>
                            <path d="M8 12h8M8 8h5"/>
                        </svg>
                    </span>
                    <span class="font-semibold text-[17px] tracking-tight">AuctionBall</span>
                    <slot name="after-brand" />
                </Link>
            </div>

            <nav class="hidden lg:flex items-center gap-7 text-[14px] text-ink-600">
                <a v-for="l in navLinks" :key="l.href" :href="l.href" class="hover:text-ink-900 transition-colors">{{ t(l.key) }}</a>
            </nav>

            <div class="flex items-center gap-1.5 sm:gap-2.5">
                <LanguageToggle />
                <template v-if="authUser">
                    <Link href="/logout" method="post" as="button" type="button"
                          class="hidden sm:inline-flex btn-ghost text-[14px] py-2.5 px-4">
                        {{ t('auth.verify_log_out') }}
                    </Link>
                    <Link href="/dashboard-redirect" class="btn-primary text-[13px] sm:text-[14px] py-2 px-3 sm:py-2.5 sm:px-4">
                        Dashboard
                    </Link>
                </template>
                <template v-else>
                    <Link href="/login" class="hidden sm:inline-flex btn-ghost text-[14px] py-2.5 px-4">{{ t('nav.log_in') }}</Link>
                    <Link href="/register" class="btn-primary text-[13px] sm:text-[14px] py-2 px-3 sm:py-2.5 sm:px-4">{{ t('nav.start_free') }}</Link>
                </template>
            </div>
        </div>
    </header>
</template>
