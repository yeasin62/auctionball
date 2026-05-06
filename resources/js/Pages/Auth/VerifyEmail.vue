<script setup>
import AuthShell from '@/Layouts/AuthShell.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    status: { type: String, default: null },
});

const form = useForm({});
const submit = () => form.post(route('verification.send'));

const linkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head :title="t('auth.verify_title')" />
    <AuthShell :title="t('auth.verify_title')" :subtitle="t('auth.verify_subtitle')">
        <div v-if="linkSent"
             class="mb-4 rounded-lg bg-emerald-50 border border-emerald-100 px-3 py-2.5 text-[13px] text-emerald-700">
            {{ t('auth.verify_link_sent') }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-3">
            <button type="submit" class="btn-primary w-full py-3"
                    :class="{ 'opacity-60 pointer-events-none': form.processing }"
                    :disabled="form.processing">
                {{ form.processing ? t('auth.verify_sending') : t('auth.verify_resend') }}
            </button>

            <Link :href="route('logout')" method="post" as="button" type="button"
                  class="text-center text-[13px] text-ink-500 hover:text-ink-900 underline">
                {{ t('auth.verify_log_out') }}
            </Link>
        </form>
    </AuthShell>
</template>
