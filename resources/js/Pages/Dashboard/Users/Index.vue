<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConfirm, useAlert } from '@/composables/useConfirm';

const confirmDialog = useConfirm();
const alertDialog   = useAlert();

const props = defineProps({
    users:       Array,
    invitations: Array,
    teams:       Array,
});

const { t } = useI18n();

const showInvite = ref(false);
const form = useForm({
    name: '',
    email: '',
    role: 'team_owner',
    team_id: '',
});

const teamRequired = computed(() => form.role === 'team_owner');

// Clear team_id when switching to a non-team-owner role so a stale value
// doesn't sneak through to the backend (and the dropdown re-shows blank
// next time the admin picks team_owner again).
watch(() => form.role, (r) => { if (r !== 'team_owner') form.team_id = ''; });

const submit = () => form.post(route('dashboard.invitations.store'), {
    onSuccess: () => { showInvite.value = false; form.reset(); form.role = 'team_owner'; },
    preserveScroll: true,
});

const revoke = async (i) => {
    if (! await confirmDialog({
        title: t('users_page.confirm_revoke', { email: i.email }),
        variant: 'danger',
        confirmText: 'Revoke invitation',
    })) return;
    router.delete(route('dashboard.invitations.destroy', i.id), { preserveScroll: true });
};

const copyLink = async (link) => {
    await navigator.clipboard.writeText(link);
    alertDialog({ title: t('users_page.invite_link_copied'), variant: 'info' });
};

const removeUser = async (u) => {
    if (! await confirmDialog({
        title: `Remove ${u.name}?`,
        body: `${u.email}\n\nThis removes their access to this organization. If they are a team owner, their team login link will stop working until another owner is assigned.`,
        variant: 'danger',
        confirmText: 'Remove user',
    })) return;

    router.delete(route('dashboard.users.remove', u.id), { preserveScroll: true });
};

const roleLabel = (r) => ({
    org_admin:  'Admin',
    auctioneer: t('users_page.role_auctioneer'),
    team_owner: t('users_page.role_team_owner'),
    viewer:     t('users_page.role_viewer'),
}[r] || r);

const roleColor = (r) => ({
    org_admin:  'bg-violet-50 text-violet-700 border-violet-100',
    auctioneer: 'bg-blue-50 text-blue-700 border-blue-100',
    team_owner: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    viewer:     'bg-ink-100 text-ink-600 border-ink-200',
}[r] || 'bg-ink-100 text-ink-500');
</script>

<template>
    <DashboardLayout :title="t('sidebar.users')">
        <template #actions>
            <button @click="showInvite = !showInvite" class="btn-primary py-2 px-4 text-[13px]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                {{ t('users_page.invite_button') }}
            </button>
        </template>

        <!-- Invite form -->
        <div v-if="showInvite" class="glass-strong rounded-2xl p-6 mb-5">
            <h3 class="text-[16px] font-bold tracking-tight mb-4">{{ t('users_page.send_invite_title') }}</h3>
            <form @submit.prevent="submit" class="grid md:grid-cols-2 gap-4">
                <Field :label="t('common.email')" :error="form.errors.email" required>
                    <TextField v-model="form.email" type="email" :placeholder="t('users_page.email_placeholder')" autofocus />
                </Field>
                <Field :label="t('users_page.name_optional')" :error="form.errors.name">
                    <TextField v-model="form.name" :placeholder="t('users_page.name_placeholder')" />
                </Field>
                <Field :label="t('users_page.role')" :error="form.errors.role" required>
                    <select v-model="form.role" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                        <option value="team_owner">{{ t('users_page.role_team_owner') }}</option>
                        <option value="auctioneer">{{ t('users_page.role_auctioneer') }}</option>
                        <option value="viewer">{{ t('users_page.role_viewer') }}</option>
                    </select>
                </Field>
                <Field v-if="teamRequired" :label="t('users_page.team')" :error="form.errors.team_id" required>
                    <select v-model="form.team_id" class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                        <option value="">{{ t('users_page.team_pick') }}</option>
                        <option v-for="tt in teams" :key="tt.id" :value="tt.id">{{ tt.name }}</option>
                    </select>
                </Field>
                <div v-else></div>
                <div class="md:col-span-2 flex gap-2 justify-end">
                    <button type="button" class="btn-ghost py-2 px-4 text-[13px]" @click="showInvite = false">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary py-2 px-4 text-[13px]" :disabled="form.processing">
                        {{ form.processing ? t('users_page.sending') : t('users_page.send_invitation') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Pending invitations -->
        <div v-if="invitations.length" class="glass rounded-2xl overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-ink-100">
                <h3 class="text-[14px] font-bold tracking-tight">
                    {{ t('users_page.pending_invitations') }}
                    <span class="text-ink-400 font-mono text-[11.5px] ml-1">{{ invitations.length }}</span>
                </h3>
            </div>
            <table class="w-full text-[13.5px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">{{ t('users_page.col_email') }}</th>
                        <th class="px-4 py-2.5">{{ t('users_page.col_role') }}</th>
                        <th class="px-4 py-2.5">{{ t('users_page.col_team') }}</th>
                        <th class="px-4 py-2.5">{{ t('users_page.col_expires') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="i in invitations" :key="i.id">
                        <td class="px-4 py-2.5 font-mono">{{ i.email }}</td>
                        <td class="px-4 py-2.5">
                            <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] uppercase border" :class="roleColor(i.role)">{{ roleLabel(i.role) }}</span>
                        </td>
                        <td class="px-4 py-2.5">{{ i.team || '—' }}</td>
                        <td class="px-4 py-2.5 font-mono text-[12px]" :class="i.expired ? 'text-rose-500' : 'text-ink-500'">
                            {{ i.expires_at }}{{ i.expired ? t('users_page.expired_suffix') : '' }}
                        </td>
                        <td class="px-4 py-2.5 text-right space-x-3">
                            <button @click="copyLink(i.link)" class="text-[12px] text-brand-indigo hover:underline">{{ t('users_page.copy_link') }}</button>
                            <button @click="revoke(i)" class="text-[12px] text-rose-500 hover:text-rose-700">{{ t('users_page.revoke') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Users -->
        <div class="glass rounded-2xl overflow-hidden">
            <table class="w-full text-[13.5px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">{{ t('users_page.col_name') }}</th>
                        <th class="px-4 py-2.5">{{ t('users_page.col_email') }}</th>
                        <th class="px-4 py-2.5">{{ t('users_page.col_role') }}</th>
                        <th class="px-4 py-2.5">{{ t('users_page.col_joined') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="u in users" :key="u.id">
                        <td class="px-4 py-2.5 font-medium">{{ u.name }}</td>
                        <td class="px-4 py-2.5 text-ink-700 font-mono text-[12.5px]">{{ u.email }}</td>
                        <td class="px-4 py-2.5">
                            <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] uppercase border" :class="roleColor(u.role)">{{ roleLabel(u.role) }}</span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[12px] text-ink-500">{{ u.joined_at?.slice(0,10) }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <button v-if="u.can_remove" type="button" @click="removeUser(u)" class="text-[12px] text-rose-500 hover:text-rose-700">
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </DashboardLayout>
</template>
