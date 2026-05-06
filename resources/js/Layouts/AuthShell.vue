<script setup>
import { Link } from '@inertiajs/vue3';
import LanguageToggle from '@/Components/LanguageToggle.vue';
defineProps({ title: String, subtitle: String });
</script>

<template>
    <div class="page-bg min-h-screen flex flex-col">
        <header class="px-6 py-5 flex items-center justify-between">
            <Link href="/" class="inline-flex items-center gap-2.5">
                <span class="grid place-items-center h-9 w-9 rounded-lg bg-gradient-brand shadow-cta">
                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                        <rect x="4" y="4" width="16" height="16" rx="3"/>
                        <path d="M8 12h8M8 8h5"/>
                    </svg>
                </span>
                <span class="font-semibold text-[17px] tracking-tight">AuctionBall</span>
            </Link>
            <LanguageToggle />
        </header>

        <main class="flex-1 grid place-items-center px-6 pb-12">
            <div class="w-full max-w-md">
                <div class="text-center mb-7">
                    <h1 v-if="title" class="text-[32px] leading-[1.1] font-extrabold tracking-tight">{{ title }}</h1>
                    <p v-if="subtitle" class="mt-2 text-[14px] text-ink-500">{{ subtitle }}</p>
                </div>
                <div class="glass-strong rounded-2xl p-7 shadow-glass-lg">
                    <slot />
                </div>
                <div class="mt-6 text-center">
                    <slot name="footer" />
                </div>
            </div>
        </main>
    </div>
</template>
