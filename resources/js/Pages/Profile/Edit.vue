<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head :title="t('profile.head_title')" />

    <DashboardLayout :title="t('profile.heading')">
        <div class="mx-auto max-w-5xl space-y-5">
            <div>
                <p class="font-mono text-[11px] uppercase tracking-widest text-brand-indigo">Account</p>
                <h1 class="mt-2 text-[26px] font-extrabold tracking-tight text-ink-900">{{ t('profile.heading') }}</h1>
                <p class="mt-2 max-w-2xl text-[14px] leading-6 text-ink-500">
                    Manage your account details, profile image, password, and account access.
                </p>
            </div>

            <div class="grid gap-5">
                <div class="glass rounded-2xl p-5 sm:p-7">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-2xl"
                    />
                </div>

                <div class="glass rounded-2xl p-5 sm:p-7">
                    <UpdatePasswordForm class="max-w-2xl" />
                </div>

                <div class="glass rounded-2xl p-5 sm:p-7">
                    <DeleteUserForm class="max-w-2xl" />
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
