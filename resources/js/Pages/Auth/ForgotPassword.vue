<script setup>
import AuthShell from '@/Layouts/AuthShell.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
});

const submit = () => form.post(route('password.email'));
</script>

<template>
    <Head :title="t('auth.forgot_title')" />
    <AuthShell :title="t('auth.forgot_title')" :subtitle="t('auth.forgot_subtitle')">
        <div v-if="status" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-100 px-3 py-2.5 text-[13px] text-emerald-700">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <Field :label="t('common.email')" :error="form.errors.email" required>
                <TextField v-model="form.email" type="email" placeholder="you@example.com"
                           autofocus autocomplete="username" />
            </Field>

            <button type="submit" class="btn-primary w-full py-3"
                    :class="{ 'opacity-60 pointer-events-none': form.processing }"
                    :disabled="form.processing">
                {{ form.processing ? t('auth.sending_reset_link') : t('auth.send_reset_link') }}
            </button>
        </form>

        <template #footer>
            <p class="text-[13.5px] text-ink-500">
                <Link :href="route('login')" class="text-ink-900 font-medium hover:underline">
                    ← {{ t('auth.back_to_login') }}
                </Link>
            </p>
        </template>
    </AuthShell>
</template>
