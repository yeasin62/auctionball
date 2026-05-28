<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';

const { t } = useI18n();
const page = usePage();

const phone = '8801912578522';
const message = computed(() => encodeURIComponent(t('whatsapp_chat.message')));
const href = computed(() => `https://wa.me/${phone}?text=${message.value}`);
const showButton = computed(() => ! String(page.component || '').startsWith('SuperAdmin/'));
</script>

<template>
    <a
        v-if="showButton"
        :href="href"
        target="_blank"
        rel="noopener noreferrer"
        class="fixed bottom-5 right-5 z-[70] inline-flex items-center gap-3 rounded-full bg-[#25D366] px-4 py-3 text-white shadow-[0_18px_45px_rgba(16,185,129,0.35)] ring-1 ring-white/30 transition hover:-translate-y-0.5 hover:bg-[#20bd5a] focus:outline-none focus:ring-4 focus:ring-emerald-300/50"
        :aria-label="t('whatsapp_chat.aria')"
        :title="t('whatsapp_chat.title')"
    >
        <span class="grid h-9 w-9 place-items-center rounded-full bg-white/18">
            <svg viewBox="0 0 32 32" class="h-5 w-5 fill-current" aria-hidden="true">
                <path d="M16.04 3.2c-7.06 0-12.8 5.7-12.8 12.72 0 2.24.6 4.43 1.73 6.35L3.2 28.8l6.7-1.74a12.9 12.9 0 0 0 6.14 1.56c7.06 0 12.8-5.7 12.8-12.72S23.1 3.2 16.04 3.2Zm0 23.25c-1.95 0-3.86-.53-5.52-1.54l-.4-.24-3.98 1.03 1.06-3.85-.26-.4a10.42 10.42 0 0 1-1.6-5.53c0-5.82 4.8-10.55 10.7-10.55s10.7 4.73 10.7 10.55-4.8 10.53-10.7 10.53Zm5.86-7.88c-.32-.16-1.9-.93-2.2-1.03-.3-.11-.52-.16-.74.16-.21.32-.84 1.03-1.03 1.25-.19.21-.38.24-.7.08-.32-.16-1.36-.5-2.6-1.6-.96-.85-1.6-1.9-1.8-2.22-.18-.32-.02-.5.14-.66.15-.14.32-.37.48-.56.16-.19.21-.32.32-.53.11-.21.05-.4-.03-.56-.08-.16-.74-1.77-1.02-2.42-.27-.63-.54-.54-.74-.55h-.63c-.21 0-.56.08-.85.4-.3.32-1.12 1.09-1.12 2.65s1.15 3.08 1.31 3.29c.16.21 2.27 3.44 5.5 4.83.77.33 1.37.53 1.84.68.77.24 1.47.21 2.03.13.62-.09 1.9-.77 2.17-1.51.27-.75.27-1.39.19-1.52-.08-.14-.29-.22-.61-.38Z"/>
            </svg>
        </span>
        <span class="hidden sm:block text-left leading-tight">
            <span class="block text-[13px] font-bold">{{ t('whatsapp_chat.label') }}</span>
            <span class="block text-[11px] text-white/85">{{ t('whatsapp_chat.sub_label') }}</span>
        </span>
    </a>
</template>
