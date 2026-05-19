<script setup>
import AuthShell from '@/Layouts/AuthShell.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({ invitation: Object });

const form = useForm({
    name: props.invitation.name ?? '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(route('invite.accept', props.invitation.token), {
    onFinish: () => form.reset('password', 'password_confirmation'),
});

const roleLabel = computed(() => ({
    auctioneer: t('invite.role_auctioneer'),
    team_owner: t('invite.role_team_owner'),
    viewer:     t('invite.role_viewer'),
}[props.invitation.role] ?? props.invitation.role));

const subtitle = computed(() => props.invitation.team
    ? t('invite.subtitle_team', { role: roleLabel.value, team: props.invitation.team.name })
    : t('invite.subtitle', { role: roleLabel.value }));
</script>

<template>
    <Head :title="t('invite.head_title', { org: invitation.org.name })" />
    <AuthShell :title="t('invite.head_title', { org: invitation.org.name })" :subtitle="subtitle">
        <form @submit.prevent="submit" class="space-y-5">
            <div class="rounded-xl bg-white/60 border border-ink-200/60 px-4 py-3 text-[13px]">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1">{{ t('invite.invited_email') }}</div>
                <div class="font-mono">{{ invitation.email }}</div>
            </div>

            <template v-if="!invitation.has_account">
                <Field :label="t('auth.your_name')" :error="form.errors.name" required>
                    <TextField v-model="form.name" :placeholder="t('invite.your_name_placeholder')" autofocus autocomplete="name" />
                </Field>
                <div class="grid grid-cols-2 gap-3">
                    <Field :label="t('common.password')" :error="form.errors.password" required>
                        <TextField v-model="form.password" type="password" autocomplete="new-password" />
                    </Field>
                    <Field :label="t('common.confirm_password')" :error="form.errors.password_confirmation" required>
                        <TextField v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                    </Field>
                </div>
            </template>
            <template v-else>
                <p class="text-[13.5px] text-ink-600">
                    {{ t('invite.already_have_account') }}
                </p>
            </template>

            <button type="submit" class="btn-primary w-full py-3"
                    :disabled="form.processing"
                    :class="{ 'opacity-60 pointer-events-none': form.processing }">
                {{ form.processing ? t('invite.accepting') : invitation.has_account ? t('invite.accept_button') : t('invite.create_and_accept_button') }}
            </button>
        </form>
    </AuthShell>
</template>
