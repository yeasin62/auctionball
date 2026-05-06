<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    /** When `dark`, the trigger uses light text on a transparent background — for use on the dark CTA. */
    variant: { type: String, default: 'light' },
});

const page = usePage();
const open = ref(false);

const current = computed(() => {
    const code = page.props.locale ?? 'en';
    return (page.props.locales ?? []).find(l => l.code === code) ?? { code, native: code.toUpperCase(), flag: '' };
});

const switchTo = (code) => {
    open.value = false;
    if (code === current.value.code) return;
    router.post(route('locale.switch', code), {}, {
        preserveScroll: true,
        preserveState: false,    // hard-reload so server-side rendered text (mailers/PDFs) also use the new locale next time
    });
};

const triggerCls = (variant) => variant === 'dark'
    ? 'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-mono text-[11.5px] tracking-wide bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200'
    : 'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-mono text-[11.5px] tracking-wide bg-white/70 hover:bg-white border border-ink-200/60 text-ink-700';
</script>

<template>
    <div class="relative">
        <button :class="triggerCls(variant)" @click="open = !open" type="button">
            <span class="text-[12px]">{{ current.flag }}</span>
            <span>{{ current.native }}</span>
            <svg class="h-2.5 w-2.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </button>

        <div v-if="open"
             class="absolute right-0 mt-2 w-44 rounded-xl py-1.5 shadow-glass-lg z-50"
             style="background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.8);">
            <button v-for="l in page.props.locales" :key="l.code"
                    @click="switchTo(l.code)"
                    class="w-full text-left px-3 py-2 text-[13px] flex items-center gap-2.5 hover:bg-ink-50 transition-colors"
                    :class="l.code === current.code ? 'text-ink-900 font-semibold' : 'text-ink-700'">
                <span class="text-[15px]">{{ l.flag }}</span>
                <span class="flex-1">{{ l.native }}</span>
                <svg v-if="l.code === current.code" class="h-3.5 w-3.5 text-brand-indigo" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                    <path d="M5 12l5 5L20 7"/>
                </svg>
            </button>
        </div>
    </div>
</template>
