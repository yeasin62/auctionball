<script setup>
import AuthShell from '@/Layouts/AuthShell.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ invitation: Object });

const form = useForm({
    name: props.invitation.name ?? '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(route('invite.accept', props.invitation.token), {
    onFinish: () => form.reset('password', 'password_confirmation'),
});

const roleLabel = ({
    auctioneer: 'Auctioneer',
    team_owner: 'Team owner',
    viewer:     'Viewer',
})[props.invitation.role] ?? props.invitation.role;
</script>

<template>
    <Head :title="`Join ${invitation.org.name}`" />
    <AuthShell :title="`Join ${invitation.org.name}`"
               :subtitle="`You've been invited as ${roleLabel}` + (invitation.team ? ` for ${invitation.team.name}` : '')">
        <form @submit.prevent="submit" class="space-y-5">
            <div class="rounded-xl bg-white/60 border border-ink-200/60 px-4 py-3 text-[13px]">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1">INVITED EMAIL</div>
                <div class="font-mono">{{ invitation.email }}</div>
            </div>

            <template v-if="!invitation.has_account">
                <Field label="Your name" :error="form.errors.name" required>
                    <TextField v-model="form.name" placeholder="Karim Captain" autofocus autocomplete="name" />
                </Field>
                <div class="grid grid-cols-2 gap-3">
                    <Field label="Password" :error="form.errors.password" required>
                        <TextField v-model="form.password" type="password" autocomplete="new-password" />
                    </Field>
                    <Field label="Confirm password" :error="form.errors.password_confirmation" required>
                        <TextField v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                    </Field>
                </div>
            </template>
            <template v-else>
                <p class="text-[13.5px] text-ink-600">
                    You already have an AuctionBall account with this email — clicking accept will add this organization to your account.
                </p>
            </template>

            <button type="submit" class="btn-primary w-full py-3"
                    :disabled="form.processing"
                    :class="{ 'opacity-60 pointer-events-none': form.processing }">
                {{ form.processing ? 'Accepting…' : invitation.has_account ? 'Accept invitation' : 'Create account & accept' }}
            </button>
        </form>
    </AuthShell>
</template>
