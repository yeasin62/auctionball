<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const confirm = useConfirm();

const props = defineProps({ subs: Object, filters: Object, counts: Object });

const f = ref({ ...props.filters });
const apply = () => router.get(route('admin.subscriptions.index'), f.value, { preserveState: true });
const clear = () => { f.value = { q: '', status: '' }; apply(); };

const forceRenew = async (s) => {
    if (! await confirm({
        title: t('super_admin.force_renew_title', { org: s.org }),
        description: t('super_admin.force_renew_body', { id: s.id, plan: s.plan, provider: s.provider }),
        variant: 'warning',
        confirmText: t('super_admin.force_renew_button'),
    })) return;
    router.post(route('admin.subs.force-renew', s.id), {}, { preserveScroll: true });
};
const cancelSub = async (s) => {
    if (! await confirm({
        title: t('super_admin.subs_cancel_title', { org: s.org }),
        description: t('super_admin.subs_cancel_body'),
        variant: 'danger',
        confirmText: t('super_admin.subs_cancel_button'),
    })) return;
    router.post(route('admin.subs.cancel', s.id), {}, { preserveScroll: true });
};

const statusColor = (s) => ({
    active:    'bg-emerald-50 text-emerald-700 border-emerald-100',
    past_due:  'bg-amber-50 text-amber-700 border-amber-100',
    expired:   'bg-rose-50 text-rose-700 border-rose-100',
    canceled:  'bg-ink-100 text-ink-500 border-ink-200',
}[s] || 'bg-ink-100 text-ink-500');

const fmtMoney = (amount, currency) => {
    const sym = currency === 'USD' ? '$' : '৳';
    return sym + Number(amount || 0).toLocaleString('en-IN');
};
</script>

<template>
    <SuperAdminLayout :title="t('super_admin.subs_title')">

        <!-- Status chips -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button v-for="status in ['', 'active', 'past_due', 'expired', 'canceled']" :key="status"
                    @click="f.status = status; apply()"
                    class="px-3 py-1.5 rounded-full text-[12px] font-mono border transition"
                    :class="f.status === status
                        ? 'bg-gradient-brand text-white border-transparent shadow-cta'
                        : 'bg-white/70 text-ink-600 border-ink-200/60'">
                {{ status || t('super_admin.audit_all') }}
                <span class="ml-1 opacity-70">{{ status ? (counts[status] || 0) : Object.values(counts).reduce((a, b) => a + b, 0) }}</span>
            </button>
        </div>

        <div class="glass rounded-2xl p-4 mb-4 flex flex-wrap items-center gap-2">
            <input v-model="f.q" @keyup.enter="apply"
                   :placeholder="t('super_admin.subs_search_placeholder')"
                   class="flex-1 min-w-[220px] rounded-lg border border-ink-200/70 bg-white/80 px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
            <button @click="apply" class="btn-primary py-2 px-4 text-[13px]">{{ t('super_admin.users_apply') }}</button>
            <button @click="clear" class="btn-ghost py-2 px-3 text-[12px]">{{ t('super_admin.reset') }}</button>
            <span class="text-[12px] font-mono text-ink-500 ml-auto">{{ t('super_admin.n_total', { n: subs.total }) }}</span>
        </div>

        <!-- Table -->
        <div class="glass rounded-2xl overflow-hidden">
            <table class="w-full text-[13px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">#</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_org') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_plan') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_provider') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.subs_th_amount') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_status') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.subs_th_renew') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.subs_th_period_end') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_attempts') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="s in subs.data" :key="s.id" class="hover:bg-white/40">
                        <td class="px-4 py-2.5 font-mono text-ink-500">{{ s.id }}</td>
                        <td class="px-4 py-2.5 font-medium">{{ s.org }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ s.plan }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ s.provider }}</td>
                        <td class="px-4 py-2.5 font-mono">{{ fmtMoney(s.amount, s.currency) }}</td>
                        <td class="px-4 py-2.5">
                            <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] uppercase border" :class="statusColor(s.status)">{{ s.status }}</span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px]">
                            <span :class="s.auto_renew ? 'text-emerald-700' : 'text-ink-400'">{{ s.auto_renew ? t('super_admin.subs_auto_on') : t('super_admin.subs_auto_off') }}</span>
                            <span v-if="s.is_recurring" class="ml-1 text-[9px] text-violet-600">tok</span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px]">{{ s.current_period_end || '—' }}</td>
                        <td class="px-4 py-2.5 font-mono">{{ s.attempts }}/3</td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            <button @click="forceRenew(s)" class="text-[11.5px] text-brand-indigo hover:underline mr-3">{{ t('super_admin.force_renew') }}</button>
                            <button v-if="s.status !== 'canceled' && s.status !== 'expired'"
                                    @click="cancelSub(s)" class="text-[11.5px] text-rose-500 hover:text-rose-700">{{ t('super_admin.subs_cancel_action') }}</button>
                        </td>
                    </tr>
                    <tr v-if="subs.data.length === 0">
                        <td colspan="10" class="px-4 py-12 text-center text-[13px] text-ink-500">{{ t('super_admin.subs_no_match') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="subs.last_page > 1" class="mt-4 flex justify-center gap-1">
            <Link v-for="link in subs.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                  class="px-3 py-1.5 rounded-lg text-[13px] font-mono"
                  :class="link.active ? 'bg-gradient-brand text-white' : link.url ? 'text-ink-700 hover:bg-white/60' : 'text-ink-300'"
                  :preserve-scroll="true" />
        </div>
    </SuperAdminLayout>
</template>
