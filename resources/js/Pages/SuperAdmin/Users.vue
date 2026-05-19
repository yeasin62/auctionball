<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useConfirm, useAlert } from '@/composables/useConfirm';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const confirm = useConfirm();
const alertDialog = useAlert();

const props = defineProps({
    users:   Object,
    filters: Object,
    orgs:    { type: Array, default: () => [] },
    plans:   { type: Array, default: () => [] },
    roles:   { type: Array, default: () => [] },
});
const page = usePage();
const me = page.props.auth?.user?.id;

const f = ref({ ...props.filters });
const apply = () => router.get(route('admin.users.index'), f.value, { preserveState: true });
const clear = () => { f.value = { q: '', is_super_admin: '' }; apply(); };

// --- Create user modal ---
const showCreate = ref(false);
const createForm = useForm({
    name: '',
    email: '',
    password: '',
    is_super_admin: false,
    attach_mode: 'new_org',
    // new_org
    org_name: '',
    org_slug: '',
    plan: 'free',
    gift_months: 1,
    // existing_org
    organization_id: null,
    role: 'org_admin',
});

const slugify = (s) => (s || '').toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);
const onOrgNameInput = () => {
    if (! createForm.org_slug) createForm.org_slug = slugify(createForm.org_name);
};

const submitCreate = () => {
    createForm.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; createForm.reset(); createForm.attach_mode = 'new_org'; createForm.plan = 'free'; createForm.role = 'org_admin'; createForm.gift_months = 1; },
    });
};

const isPaidPlan = computed(() => createForm.plan && createForm.plan !== 'free');

const roleLabel = (r) => ({
    org_admin:  t('super_admin.role_org_admin'),
    auctioneer: t('super_admin.role_auctioneer'),
    team_owner: t('super_admin.role_team_owner'),
    viewer:     t('super_admin.role_viewer'),
}[r] || r);

const toggleSuperAdmin = async (u) => {
    if (u.id === me) return alertDialog({ title: t('super_admin.cant_change_own'), variant: 'warning' });
    if (! await confirm({
        title: u.is_super_admin
            ? t('super_admin.demote_title', { name: u.name })
            : t('super_admin.promote_title', { name: u.name }),
        description: u.is_super_admin
            ? t('super_admin.demote_body')
            : t('super_admin.promote_body'),
        variant: u.is_super_admin ? 'warning' : 'danger',
        confirmText: u.is_super_admin ? t('super_admin.demote_button') : t('super_admin.promote_button'),
    })) return;
    router.post(route('admin.users.toggle-super-admin', u.id), {}, { preserveScroll: true });
};

const resetPassword = async (u) => {
    if (u.id === me) return;
    if (! await confirm({
        title: t('super_admin.reset_pw_title', { name: u.name }),
        description: t('super_admin.reset_pw_body_long'),
        variant: 'warning',
        confirmText: t('super_admin.reset_pw'),
    })) return;
    router.post(route('admin.users.reset-password', u.id), {}, { preserveScroll: true });
};

// ============== Manual subscription grant — user-centric flow ==============
// Reuses the org-side `admin.orgs.extend-sub` endpoint (single source of truth)
// but exposes it from the user view: pick a user → pick which of their orgs →
// set plan + end date → save.
const grantUser    = ref(null);
const grantForm    = useForm({ organization_id: '', plan: '', until: '', note: '' });

const todayPlus = (days) => {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
};
const todayIso = computed(() => new Date().toISOString().slice(0, 10));

const openGrant = (u) => {
    if (! u.orgs_for_grant?.length) {
        alertDialog({
            title: t('super_admin.no_org_title'),
            description: t('super_admin.no_org_body', { name: u.name }),
            variant: 'warning',
        });
        return;
    }
    grantUser.value          = u;
    grantForm.organization_id = u.orgs_for_grant[0].id;
    grantForm.plan            = u.orgs_for_grant[0].plan;
    grantForm.until           = u.orgs_for_grant[0].sub_until || todayPlus(30);
    grantForm.note            = '';
    grantForm.clearErrors();
};

// When the admin picks a different org from the dropdown, snap plan + date
// defaults to that org's current state.
watch(() => grantForm.organization_id, (newId) => {
    if (! grantUser.value) return;
    const org = grantUser.value.orgs_for_grant.find(o => o.id === newId);
    if (! org) return;
    grantForm.plan  = org.plan;
    grantForm.until = org.sub_until || todayPlus(30);
});

const closeGrant = () => { grantUser.value = null; grantForm.reset(); };

const submitGrant = () => {
    grantForm.post(route('admin.orgs.extend-sub', grantForm.organization_id), {
        preserveScroll: true,
        onSuccess: () => closeGrant(),
    });
};

const deleteUser = async (u) => {
    if (u.id === me) return alertDialog({ title: t('super_admin.delete_yourself'), variant: 'warning' });
    if (! await confirm({
        title: t('super_admin.delete_user_confirm_title', { name: u.name }),
        description: t('super_admin.delete_user_body_long', { email: u.email }),
        variant: 'danger',
        confirmText: t('super_admin.delete_account_button'),
        typeToConfirm: 'DELETE',
    })) return;
    router.delete(route('admin.users.delete', u.id), { preserveScroll: true });
};
</script>

<template>
    <SuperAdminLayout :title="t('super_admin.users_title')">

        <div class="flex justify-end mb-4">
            <button @click="showCreate = true" class="btn-primary py-2 px-4 text-[13px]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                {{ t('super_admin.create_user') }}
            </button>
        </div>

        <!-- Filters -->
        <div class="glass rounded-2xl p-4 mb-4 flex flex-wrap items-center gap-2">
            <input v-model="f.q" @keyup.enter="apply"
                   :placeholder="t('super_admin.users_search_placeholder')"
                   class="flex-1 min-w-[220px] rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
            <select v-model="f.is_super_admin" @change="apply" class="rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px]">
                <option value="">{{ t('super_admin.all_users') }}</option>
                <option value="1">{{ t('super_admin.users_filter_super') }}</option>
                <option value="0">{{ t('super_admin.users_filter_regular_only') }}</option>
            </select>
            <button @click="apply" class="btn-primary py-2 px-4 text-[13px]">{{ t('super_admin.users_apply') }}</button>
            <button @click="clear" class="btn-ghost py-2 px-3 text-[12px]">{{ t('super_admin.reset') }}</button>
            <span class="text-[12px] font-mono text-ink-500 ml-auto">{{ t('super_admin.n_total', { n: users.total }) }}</span>
        </div>

        <!-- Users table -->
        <div class="glass rounded-2xl overflow-hidden">
            <table class="w-full text-[13.5px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">{{ t('super_admin.th_user') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_email') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_orgs') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_organization') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_joined') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_super_short') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-white/40">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="h-8 w-8 rounded-full bg-gradient-to-br from-cyan-200 to-indigo-300 grid place-items-center font-mono text-[10px] font-bold text-indigo-700 shrink-0">
                                    {{ u.name?.[0]?.toUpperCase() }}
                                </div>
                                <div class="leading-tight">
                                    <div class="font-medium">{{ u.name }}</div>
                                    <div v-if="u.id === me" class="font-mono text-[9.5px] text-violet-600">{{ t('super_admin.you') }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[12px] text-ink-700">{{ u.email }}</td>
                        <td class="px-4 py-2.5 font-mono">{{ u.organizations_count }}</td>
                        <td class="px-4 py-2.5">
                            <div v-if="u.sample_org" class="leading-tight">
                                <div class="text-[12.5px]">{{ u.sample_org.name }}</div>
                                <div class="font-mono text-[10.5px] text-ink-500">{{ roleLabel(u.sample_org.role) }}</div>
                            </div>
                            <span v-else class="text-ink-400">—</span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[12px] text-ink-500">{{ u.created_at }}</td>
                        <td class="px-4 py-2.5">
                            <button @click="toggleSuperAdmin(u)"
                                    class="inline-flex items-center gap-1.5 cursor-pointer"
                                    :class="u.id === me ? 'opacity-40 pointer-events-none' : ''">
                                <span class="h-4 w-7 rounded-full transition-colors relative"
                                      :class="u.is_super_admin ? 'bg-violet-500' : 'bg-ink-200'">
                                    <span class="absolute top-0.5 left-0.5 h-3 w-3 bg-white rounded-full transition-transform"
                                          :class="u.is_super_admin ? 'translate-x-3' : ''"></span>
                                </span>
                                <span v-if="u.is_super_admin" class="font-mono text-[10px] text-violet-700 font-bold tracking-wider">{{ t('super_admin.th_super_short') }}</span>
                            </button>
                        </td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            <button @click="openGrant(u)"
                                    class="text-[11.5px] text-emerald-600 hover:text-emerald-800 hover:underline mr-3"
                                    :class="{ 'opacity-40 pointer-events-none': ! u.orgs_for_grant?.length }">
                                {{ t('super_admin.grant_sub') }}
                            </button>
                            <button @click="resetPassword(u)" class="text-[11.5px] text-brand-indigo hover:underline mr-3"
                                    :class="u.id === me ? 'opacity-40 pointer-events-none' : ''">
                                {{ t('super_admin.reset_pw') }}
                            </button>
                            <button @click="deleteUser(u)" class="text-[11.5px] text-rose-500 hover:text-rose-700"
                                    :class="u.id === me ? 'opacity-40 pointer-events-none' : ''">
                                {{ t('super_admin.delete') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.last_page > 1" class="mt-4 flex justify-center gap-1">
            <Link v-for="link in users.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                  class="px-3 py-1.5 rounded-lg text-[13px] font-mono"
                  :class="link.active ? 'bg-gradient-brand text-white' : link.url ? 'text-ink-700 hover:bg-white/60' : 'text-ink-300'"
                  :preserve-scroll="true" />
        </div>

        <!-- Create user modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink-900/50 backdrop-blur-sm p-4 pt-10" @click.self="showCreate = false">
            <div class="glass-strong rounded-2xl max-w-xl w-full p-6 shadow-glass-lg">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.create_user_title') }}</div>
                        <div class="mt-1 text-[18px] font-bold tracking-tight">{{ t('super_admin.create_user_subtitle') }}</div>
                    </div>
                    <button @click="showCreate = false" class="text-ink-400 hover:text-ink-700 text-[20px]">×</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <!-- Identity -->
                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.field_full_name') }}</label>
                            <input v-model="createForm.name" type="text" autofocus
                                   class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                            <p v-if="createForm.errors.name" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.field_email') }}</label>
                            <input v-model="createForm.email" type="email"
                                   class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                            <p v-if="createForm.errors.email" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.email }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.field_password_label') }} <span class="text-ink-400 normal-case tracking-normal">{{ t('super_admin.field_password_hint') }}</span></label>
                        <input v-model="createForm.password" type="text" :placeholder="t('super_admin.field_password_placeholder')"
                               class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                        <p v-if="createForm.errors.password" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.password }}</p>
                    </div>

                    <label class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl bg-violet-50 border border-violet-200 cursor-pointer">
                        <input type="checkbox" v-model="createForm.is_super_admin" class="h-4 w-4" />
                        <div class="flex-1">
                            <div class="text-[13px] font-semibold">{{ t('super_admin.mark_super_admin') }}</div>
                            <div class="text-[11.5px] text-violet-700">{{ t('super_admin.mark_super_admin_help') }}</div>
                        </div>
                    </label>

                    <!-- Attach mode tabs -->
                    <div>
                        <label class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-2 block">{{ t('super_admin.field_organization') }}</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" v-for="m in [
                                { v: 'new_org', label: t('super_admin.attach_new_org_label') },
                                { v: 'existing_org', label: t('super_admin.attach_existing_org_label') },
                                { v: 'none', label: t('super_admin.attach_none_label') },
                            ]" :key="m.v"
                                @click="createForm.attach_mode = m.v"
                                class="rounded-xl border px-3 py-2.5 text-[12px] transition"
                                :class="createForm.attach_mode === m.v
                                    ? 'bg-gradient-brand text-white border-transparent shadow-cta'
                                    : 'bg-white/70 border-ink-200/70 text-ink-700 hover:bg-white'">
                                {{ m.label }}
                            </button>
                        </div>
                    </div>

                    <!-- New org branch -->
                    <div v-if="createForm.attach_mode === 'new_org'" class="rounded-xl bg-white/70 border border-ink-200/60 p-4 space-y-3">
                        <div class="grid md:grid-cols-2 gap-3">
                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.field_org_name') }}</label>
                                <input v-model="createForm.org_name" @input="onOrgNameInput" type="text"
                                       class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                                <p v-if="createForm.errors.org_name" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.org_name }}</p>
                            </div>
                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.field_slug') }}</label>
                                <input v-model="createForm.org_slug" type="text" :placeholder="t('super_admin.field_slug_placeholder')"
                                       class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                                <p v-if="createForm.errors.org_slug" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.org_slug }}</p>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-3">
                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.field_gift_plan') }}</label>
                                <select v-model="createForm.plan"
                                        class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] capitalize focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                    <option v-for="p in plans" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <div v-if="isPaidPlan">
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.field_gift_duration') }}</label>
                                <input v-model.number="createForm.gift_months" type="number" min="1" max="60"
                                       class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                            </div>
                        </div>
                        <p v-if="isPaidPlan" class="text-[11.5px] text-ink-500">
                            {{ t('super_admin.paid_plan_note', { months: createForm.gift_months }) }}
                        </p>
                    </div>

                    <!-- Existing org branch -->
                    <div v-else-if="createForm.attach_mode === 'existing_org'" class="rounded-xl bg-white/70 border border-ink-200/60 p-4 space-y-3">
                        <div>
                            <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.field_organization') }}</label>
                            <select v-model.number="createForm.organization_id"
                                    class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                <option :value="null" disabled>{{ t('super_admin.pick_org_placeholder') }}</option>
                                <option v-for="o in orgs" :key="o.id" :value="o.id">{{ o.name }} ({{ o.slug }} · {{ o.plan }})</option>
                            </select>
                            <p v-if="createForm.errors.organization_id" class="mt-1 text-[12px] text-rose-500">{{ createForm.errors.organization_id }}</p>
                        </div>
                        <div>
                            <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.field_role') }}</label>
                            <select v-model="createForm.role"
                                    class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-else class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-2.5 text-[12.5px] text-amber-800">
                        {{ t('super_admin.no_org_note') }}
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showCreate = false" class="btn-ghost py-2 px-4 text-[13px] flex-1">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary py-2 px-4 text-[13px] flex-1" :disabled="createForm.processing">
                            {{ createForm.processing ? t('super_admin.creating') : t('super_admin.create_user') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============== Manual subscription grant modal ============== -->
        <Teleport to="body">
            <Transition name="grant-fade">
                <div v-if="grantUser" class="fixed inset-0 z-[60] grid place-items-center bg-ink-900/55 backdrop-blur-sm p-4"
                     @click.self="closeGrant">
                    <div class="glass-strong rounded-2xl max-w-md w-full p-6 shadow-glass-lg">
                        <div class="flex items-start gap-4 mb-5">
                            <span class="grid place-items-center h-11 w-11 rounded-xl shrink-0 bg-emerald-100">
                                <svg class="h-5 w-5 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-[16px] font-bold tracking-tight">{{ t('super_admin.grant_heading') }}</h3>
                                <p class="mt-1 text-[13px] text-ink-600 leading-relaxed">
                                    {{ t('super_admin.grant_body', { name: grantUser.name }) }}
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submitGrant" class="space-y-3.5">
                            <!-- Org picker — only shown when user has 2+ orgs -->
                            <div v-if="grantUser.orgs_for_grant.length > 1">
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1 block">{{ t('super_admin.field_organization') }}</label>
                                <select v-model.number="grantForm.organization_id"
                                        class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                    <option v-for="o in grantUser.orgs_for_grant" :key="o.id" :value="o.id">
                                        {{ t('super_admin.grant_org_until', { name: o.name, plan: o.plan, until: o.sub_until ? t('super_admin.grant_until_suffix', { date: o.sub_until }) : '' }) }}
                                    </option>
                                </select>
                            </div>
                            <!-- Single org — show as a read-only card -->
                            <div v-else class="rounded-xl bg-white/60 border border-ink-200/60 px-3 py-2.5">
                                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.field_organization') }}</div>
                                <div class="text-[14px] font-semibold mt-0.5">{{ grantUser.orgs_for_grant[0].name }}</div>
                                <div class="font-mono text-[11px] text-ink-500">
                                    {{ t('super_admin.grant_current', { plan: grantUser.orgs_for_grant[0].plan }) }}
                                    <span v-if="grantUser.orgs_for_grant[0].sub_until">{{ t('super_admin.grant_active_until', { date: grantUser.orgs_for_grant[0].sub_until }) }}</span>
                                    <span v-else>{{ t('super_admin.grant_no_active') }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1 block">{{ t('super_admin.grant_plan') }}</label>
                                <select v-model="grantForm.plan"
                                        class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-[14px] capitalize focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                                    <option v-for="p in plans" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <p v-if="grantForm.errors.plan" class="mt-1 text-[12px] text-rose-500">{{ grantForm.errors.plan }}</p>
                            </div>

                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1 block">{{ t('super_admin.grant_active_until_label') }}</label>
                                <input v-model="grantForm.until" type="date" :min="todayIso"
                                       class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                                <p v-if="grantForm.errors.until" class="mt-1 text-[12px] text-rose-500">{{ grantForm.errors.until }}</p>
                                <div class="mt-2 flex gap-1.5 flex-wrap">
                                    <button v-for="(d, label) in { [t('super_admin.preset_7d')]: 7, [t('super_admin.preset_30d')]: 30, [t('super_admin.preset_90d')]: 90, [t('super_admin.preset_1y')]: 365 }"
                                            :key="label" type="button" @click="grantForm.until = todayPlus(d)"
                                            class="text-[11px] font-mono px-2 py-1 rounded-md border border-ink-200 hover:bg-white text-ink-600">
                                        {{ label }}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="font-mono text-[10.5px] tracking-widest text-ink-500 mb-1 block">{{ t('super_admin.grant_note') }} <span class="text-ink-400">{{ t('super_admin.grant_note_optional') }}</span></label>
                                <textarea v-model="grantForm.note" rows="2"
                                          :placeholder="t('super_admin.grant_note_placeholder')"
                                          class="w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30"></textarea>
                            </div>

                            <div class="flex gap-2 justify-end pt-2">
                                <button type="button" @click="closeGrant" class="btn-ghost py-2 px-4 text-[13px]">{{ t('common.cancel') }}</button>
                                <button type="submit" :disabled="grantForm.processing"
                                        class="btn-primary py-2 px-4 text-[13px]"
                                        :class="{ 'opacity-60 pointer-events-none': grantForm.processing }">
                                    {{ grantForm.processing ? t('super_admin.saving_dots') : t('super_admin.grant_button') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </SuperAdminLayout>
</template>

<style scoped>
.grant-fade-enter-active,
.grant-fade-leave-active { transition: opacity .2s ease; }
.grant-fade-enter-from,
.grant-fade-leave-to     { opacity: 0; }
</style>
