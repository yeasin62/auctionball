<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFmt } from '@/composables/useFmt';
import { useConfirm } from '@/composables/useConfirm';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const confirm = useConfirm();

const props = defineProps({
    orgs:         Array,
    stats:        Object,
    recent_txns:  Array,
    duesoon_subs: { type: Array, default: () => [] },
    plans:        Array,
});

const forceRenew = async (sub) => {
    if (! await confirm({
        title: t('super_admin.force_renew_title', { org: sub.org }),
        description: t('super_admin.force_renew_body', { id: sub.id, plan: sub.plan, provider: sub.provider }),
        variant: 'warning',
        confirmText: t('super_admin.force_renew_button'),
    })) return;
    router.post(route('admin.subs.force-renew', sub.id), {}, { preserveScroll: true });
};

const subStatusColor = (s) => ({
    active:    'text-emerald-700',
    past_due:  'text-amber-700',
    expired:   'text-rose-700',
    canceled:  'text-ink-500',
}[s] || 'text-ink-700');

const editingPlan = ref(null);
const newPlan     = ref('');

// Super-admin currency-aware fmt — respects the admin's own org display_currency.
const fmt = useFmt().money;

const impersonate = async (org) => {
    if (! await confirm({
        title: t('super_admin.impersonate_title', { org: org.name }),
        description: t('super_admin.impersonate_body'),
        variant: 'info',
        confirmText: t('super_admin.impersonate'),
    })) return;
    router.post(route('admin.orgs.impersonate', org.id));
};
const startEdit = (org) => { editingPlan.value = org.id; newPlan.value = org.plan; };
const savePlan = (org) => {
    router.post(route('admin.orgs.set-plan', org.id), { plan: newPlan.value }, {
        preserveScroll: true,
        onFinish: () => { editingPlan.value = null; },
    });
};

const planColor = (p) => ({
    free:       'bg-ink-100 text-ink-600 border-ink-200',
    starter:    'bg-blue-50 text-blue-700 border-blue-100',
    pro:        'bg-violet-50 text-violet-700 border-violet-100',
    enterprise: 'bg-amber-50 text-amber-800 border-amber-100',
}[p] || 'bg-ink-100 text-ink-500');

const txnStatus = (s) => ({
    completed: 'text-emerald-700',
    pending:   'text-amber-600',
    failed:    'text-rose-600',
    refunded:  'text-ink-500',
}[s] || 'text-ink-700');
</script>

<template>
    <SuperAdminLayout :title="t('super_admin.title')">

        <!-- Global stats -->
        <div class="grid md:grid-cols-4 gap-4 mb-5">
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.stat_organizations') }}</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ stats.orgs_total }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.stat_users') }}</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ stats.users_total }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.stat_running_now') }}</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1 text-emerald-600">{{ stats.auctions_running }}</div>
                <div class="text-[11px] font-mono text-ink-500">{{ t('super_admin.stat_active_seasons', { count: stats.seasons_active }) }}</div>
            </div>
            <div class="glass rounded-2xl p-5 bg-gradient-to-br from-blue-50 to-violet-50 border border-violet-100">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.stat_mrr') }}</div>
                <div class="text-[24px] font-extrabold tracking-tight mt-1 text-grad">{{ fmt(stats.mrr_estimate_bdt) }}</div>
                <div class="text-[11px] font-mono text-ink-500">
                    <template v-for="(c, p) in stats.plans_breakdown" :key="p">
                        {{ p }}:{{ c }}
                        <span v-if="!Object.is(p, Object.keys(stats.plans_breakdown).slice(-1)[0])"> · </span>
                    </template>
                </div>
            </div>
        </div>

        <!-- Renewal health -->
        <div class="grid md:grid-cols-2 gap-4 mb-5">
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.stat_past_due') }}</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1" :class="stats.subs_past_due > 0 ? 'text-amber-600' : 'text-ink-400'">{{ stats.subs_past_due }}</div>
                <div class="text-[11px] font-mono text-ink-500">{{ t('super_admin.stat_past_due_help') }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.stat_expiring_7d') }}</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ stats.subs_expiring_7d }}</div>
                <div class="text-[11px] font-mono text-ink-500">{{ t('super_admin.stat_expiring_help') }}</div>
            </div>
        </div>

        <!-- Subscriptions needing attention -->
        <div v-if="duesoon_subs.length" class="glass rounded-2xl overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-ink-100">
                <h3 class="text-[15px] font-bold tracking-tight">{{ t('super_admin.subs_due_title') }} <span class="font-mono text-[11px] text-ink-400 ml-1">{{ duesoon_subs.length }}</span></h3>
            </div>
            <table class="w-full text-[13px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">{{ t('super_admin.th_org') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_plan') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_provider') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_status') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_period_ends') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_attempts') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_next_try') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="s in duesoon_subs" :key="s.id">
                        <td class="px-4 py-2.5 font-medium">{{ s.org }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ s.plan }}</td>
                        <td class="px-4 py-2.5 capitalize text-ink-700">{{ s.provider }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px]" :class="subStatusColor(s.status)">{{ s.status.toUpperCase() }}</td>
                        <td class="px-4 py-2.5 font-mono text-[12px]">{{ s.current_period_end }}</td>
                        <td class="px-4 py-2.5 font-mono">{{ s.attempts }} / 3</td>
                        <td class="px-4 py-2.5 font-mono text-[12px] text-ink-500">{{ s.next_attempt_at || '—' }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <button @click="forceRenew(s)" class="text-[12px] text-brand-indigo hover:underline">{{ t('super_admin.force_renew') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Organizations -->
        <div class="glass rounded-2xl overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-ink-100 flex items-center justify-between">
                <h3 class="text-[15px] font-bold tracking-tight">{{ t('super_admin.organizations') }} <span class="font-mono text-[11px] text-ink-400 ml-1">{{ orgs.length }}</span></h3>
            </div>
            <table class="w-full text-[13.5px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">{{ t('super_admin.th_name') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_slug') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_plan') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_users') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_seasons') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_players') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_created') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="o in orgs" :key="o.id" class="hover:bg-white/40">
                        <td class="px-4 py-2.5 font-medium">{{ o.name }}</td>
                        <td class="px-4 py-2.5 font-mono text-[12px] text-ink-500">{{ o.slug }}</td>
                        <td class="px-4 py-2.5">
                            <template v-if="editingPlan === o.id">
                                <select v-model="newPlan" class="rounded-lg border border-ink-200/70 bg-white/80 px-2 py-1 text-[12px] mr-1">
                                    <option v-for="p in plans" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <button @click="savePlan(o)" class="text-[11px] font-medium text-emerald-700 hover:underline">{{ t('super_admin.save') }}</button>
                                <button @click="editingPlan = null" class="ml-1 text-[11px] text-ink-500 hover:underline">{{ t('super_admin.cancel') }}</button>
                            </template>
                            <button v-else @click="startEdit(o)" class="cursor-pointer">
                                <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] uppercase border" :class="planColor(o.plan)">{{ o.plan }}</span>
                            </button>
                        </td>
                        <td class="px-4 py-2.5 font-mono">{{ o.users_count }}</td>
                        <td class="px-4 py-2.5 font-mono">{{ o.seasons_count }}</td>
                        <td class="px-4 py-2.5 font-mono">{{ o.players_count }}</td>
                        <td class="px-4 py-2.5 font-mono text-[12px] text-ink-500">{{ o.created_at }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <button @click="impersonate(o)" class="text-[12px] text-brand-indigo hover:underline">{{ t('super_admin.impersonate') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Recent transactions -->
        <div class="glass rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-ink-100">
                <h3 class="text-[15px] font-bold tracking-tight">{{ t('super_admin.recent_transactions') }}</h3>
            </div>
            <table class="w-full text-[13px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">{{ t('super_admin.th_ref') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_org') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_provider') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_plan') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_amount') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_status') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_when') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="t in recent_txns" :key="t.id">
                        <td class="px-4 py-2.5 font-mono text-[11.5px]">{{ t.id }}</td>
                        <td class="px-4 py-2.5">{{ t.org }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ t.provider }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ t.plan }}</td>
                        <td class="px-4 py-2.5 font-mono">{{ t.currency }} {{ t.amount }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px]" :class="txnStatus(t.status)">{{ t.status.toUpperCase() }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-500">{{ t.created_at }}</td>
                    </tr>
                    <tr v-if="recent_txns.length === 0">
                        <td colspan="7" class="px-4 py-6 text-center text-[13px] text-ink-500">{{ t('super_admin.no_transactions') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SuperAdminLayout>
</template>
