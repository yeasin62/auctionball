<script setup>
import AuthShell from '@/Layouts/AuthShell.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(route('password.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <Head :title="t('auth.reset_title')" />
    <AuthShell :title="t('auth.reset_title')" :subtitle="t('auth.reset_subtitle')">
        <form @submit.prevent="submit" class="space-y-5">
            <Field :label="t('common.email')" :error="form.errors.email" required>
                <TextField v-model="form.email" type="email" autofocus autocomplete="username" />
            </Field>

            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('auth.new_password')" :error="form.errors.password" required>
                    <TextField v-model="form.password" type="password" autocomplete="new-password" />
                </Field>
                <Field :label="t('auth.confirm_new_password')" :error="form.errors.password_confirmation" required>
                    <TextField v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                </Field>
            </div>

            <button type="submit" class="btn-primary w-full py-3"
                    :class="{ 'opacity-60 pointer-events-none': form.processing }"
                    :disabled="form.processing">
                {{ form.processing ? t('auth.resetting') : t('auth.reset_button') }}
            </button>
        </form>
    </AuthShell>
</template>
