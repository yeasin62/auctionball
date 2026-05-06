<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LanguageToggle from '@/Components/LanguageToggle.vue';

defineProps({
    org:    Object,
    role:   String,
    reason: String,
});

const { t, locale } = useI18n();
const logout = () => router.post(route('logout'));
</script>

<template>
    <Head title="No team assigned" />
    <div class="page-bg min-h-[100dvh] flex flex-col">
        <header class="px-4 py-3 flex items-center justify-between bg-white/70 backdrop-blur-md border-b border-ink-200/60">
            <Link href="/" class="flex items-center gap-2">
                <span class="grid place-items-center h-8 w-8 rounded-lg bg-gradient-brand">
                    <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                        <rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M8 8h5"/>
                    </svg>
                </span>
                <span class="font-semibold text-[14px] tracking-tight">AuctionBall</span>
            </Link>
            <div class="flex items-center gap-2">
                <LanguageToggle />
                <button @click="logout" class="text-[11.5px] text-rose-500 hover:text-rose-700 font-medium px-2">
                    {{ t('nav.log_out') }}
                </button>
            </div>
        </header>

        <main class="flex-1 grid place-items-center px-6">
            <div class="text-center max-w-sm">
                <div class="grid place-items-center h-16 w-16 rounded-full bg-amber-50 border border-amber-100 mx-auto mb-5">
                    <svg class="h-7 w-7 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 9v3m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                    </svg>
                </div>
                <h1 class="text-[22px] font-extrabold tracking-tight">
                    {{ locale === 'bn' ? 'কোনো দল বরাদ্দ নেই' : 'No team assigned to you' }}
                </h1>
                <p class="mt-3 text-[14px] text-ink-500 leading-relaxed">
                    {{ locale === 'bn'
                        ? `${org?.name}-এ আপনি ${role || 'একজন সদস্য'} হিসেবে আছেন কিন্তু এই মৌসুমে কোনো দলের সাথে যুক্ত নন। বিড করার জন্য অর্গ অ্যাডমিনকে আপনাকে একটি দলের অধিনায়ক হিসেবে বরাদ্দ করতে বলুন।`
                        : `You're a ${role || 'member'} of ${org?.name} but no team is bound to your account in the current season. Ask an org admin to assign you as a team owner before you can place bids.` }}
                </p>
                <p v-if="reason === 'team_not_in_active_season'" class="mt-3 text-[12px] font-mono text-ink-400">
                    {{ locale === 'bn'
                        ? 'আপনার পুরনো দল সক্রিয় মৌসুমে নেই।'
                        : 'Your old team is not part of the currently active season.' }}
                </p>
            </div>
        </main>
    </div>
</template>
