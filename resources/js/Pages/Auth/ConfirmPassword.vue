<script setup>
import AuthShell from '@/Layouts/AuthShell.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    password: '',
});

const submit = () => form.post(route('password.confirm'), {
    onFinish: () => form.reset(),
});
</script>

<template>
    <Head :title="t('auth.confirm_title')" />
    <AuthShell :title="t('auth.confirm_title')" :subtitle="t('auth.confirm_subtitle')">
        <form @submit.prevent="submit" class="space-y-5">
            <Field :label="t('common.password')" :error="form.errors.password" required>
                <TextField v-model="form.password" type="password" autofocus autocomplete="current-password" />
            </Field>

            <button type="submit" class="btn-primary w-full py-3"
                    :class="{ 'opacity-60 pointer-events-none': form.processing }"
                    :disabled="form.processing">
                {{ form.processing ? t('auth.confirming') : t('auth.confirm_button') }}
            </button>
        </form>
    </AuthShell>
</template>
