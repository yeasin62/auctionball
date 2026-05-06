<script setup>
import AuthShell from '@/Layouts/AuthShell.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post(route('login'), {
    onFinish: () => form.reset('password'),
});
</script>

<template>
    <Head title="Log in to AuctionBall" />
    <AuthShell :title="t('auth.welcome_back')" :subtitle="t('auth.welcome_back_subtitle')">
        <div v-if="status" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-100 px-3 py-2 text-[13px] text-emerald-700">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <Field :label="t('common.email')" :error="form.errors.email" required>
                <TextField v-model="form.email" type="email" placeholder="you@example.com" autofocus autocomplete="username" />
            </Field>

            <Field :label="t('common.password')" :error="form.errors.password" required>
                <TextField v-model="form.password" type="password" autocomplete="current-password" />
            </Field>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="form.remember"
                           class="h-4 w-4 rounded border-ink-300 text-brand-indigo focus:ring-brand-indigo/30" />
                    <span class="text-[13px] text-ink-700">{{ t('auth.remember_me') }}</span>
                </label>
                <Link v-if="canResetPassword" :href="route('password.request')"
                      class="text-[13px] text-ink-700 hover:text-ink-900 underline">
                    {{ t('auth.forgot_password') }}
                </Link>
            </div>

            <button type="submit" class="btn-primary w-full py-3"
                    :class="{ 'opacity-60 pointer-events-none': form.processing }"
                    :disabled="form.processing">
                {{ form.processing ? t('auth.logging_in') : t('auth.login_button') }}
            </button>
        </form>

        <template #footer>
            <p class="text-[13.5px] text-ink-500">
                {{ t('auth.new_here') }}
                <Link :href="route('register')" class="text-ink-900 font-medium hover:underline">{{ t('auth.create_org_link') }}</Link>
            </p>
        </template>
    </AuthShell>
</template>
