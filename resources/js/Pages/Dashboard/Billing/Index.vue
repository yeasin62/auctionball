<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Toggle from '@/Components/Toggle.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useFmt } from '@/composables/useFmt';
import { useConfirm } from '@/composables/useConfirm';

const confirm = useConfirm();

const { t } = useI18n();
const _fmt = useFmt();

const toggleAutoRenew = () => router.post(route('dashboard.billing.auto-renew'), {}, { preserveScroll: true });
const cancelSub = async () => {
    if (! await confirm({
        title: t('billing.confirm_cancel'),
        description: 'You\'ll keep your current plan until the period ends, then your account moves to Free. Your data, players and seasons are preserved.',
        variant: 'danger',
        confirmText: t('billing.cancel_subscription'),
    })) return;
    router.post(route('dashboard.billing.cancel'), {}, { preserveScroll: true });
};
const renewNow = () => router.post(route('dashboard.billing.renew-now'), {}, { preserveScroll: true });

const subStatusBadge = (s) => ({
    active:    'bg-emerald-50 text-emerald-700 border-emerald-100',
    past_due:  'bg-amber-50 text-amber-700 border-amber-100',
    expired:   'bg-rose-50 text-rose-700 border-rose-100',
    canceled:  'bg-ink-100 text-ink-500 border-ink-200',
}[s] || 'bg-ink-100 text-ink-500');

const props = defineProps({
    org:           Object,
    usage:         Object,
    plans:         Array,
    subscription:  Object,
    transactions:  Array,
    bkash_manual:  { type: Object, default: () => ({}) },
    pending_bkash: { type: Object, default: () => null },
});

// Limits >= 999_999_999 (PlanPricing::UNLIMITED) or PHP_INT_MAX surface as
// the translated "Unlimited" label rather than a raw 9-digit number.
const UNLIMITED_THRESHOLD = 999_999_999;
const isUnlimited = (n) => n >= UNLIMITED_THRESHOLD;
const cap = (n) => isUnlimited(n) ? t('billing.unlimited') : n;
const pct = (used, max) => isUnlimited(max) ? 0 : Math.min(100, Math.round(used / max * 100));
const fmt = _fmt.money;

const checkoutPlan = ref(null);
const wantAutoRenew = ref(true);

const form = useForm({ plan: '', provider: '', auto_renew: true });
const checkout = (plan, provider) => {
    form.plan = plan; form.provider = provider; form.auto_renew = wantAutoRenew.value;
    form.post(route('dashboard.billing.checkout'));
};

// --- Manual bKash modal: customer pays out-of-band, then submits TrxID here. ---
const bkashModal = ref(null);                     // selected plan object or null
const bkashForm = useForm({ plan: '', trx_id: '', sender_number: '' });
const openBkash = (plan) => {
    bkashForm.reset();
    bkashForm.plan = plan.plan;
    bkashModal.value = plan;
};
const closeBkash = () => { bkashModal.value = null; bkashForm.reset(); bkashForm.clearErrors(); };
const submitBkash = () => bkashForm.post(route('dashboard.billing.bkash-manual'), {
    onSuccess: closeBkash,
});
const copyMerchant = () => {
    navigator.clipboard.writeText(props.bkash_manual?.merchant_number || '');
};

const txnStatusColor = (s) => ({
    completed: 'text-emerald-700',
    pending:   'text-amber-600',
    failed:    'text-rose-600',
    refunded:  'text-ink-500',
}[s] || 'text-ink-700');

const txnStatusLabel = (s) => ({
    completed: 'SUCCESSFUL',
    pending:   'PENDING',
    failed:    'FAILED',
    refunded:  'REFUNDED',
}[s] || String(s || '').toUpperCase());

const planLabel = (p) => p?.charAt(0).toUpperCase() + p?.slice(1);
const isCurrent = (plan) => org.plan === plan.plan;

// Make `org` available in template helper above
const org = props.org;
</script>

<template>
    <DashboardLayout :title="t('billing.title')">

        <!-- Pending bKash review banner -->
        <div v-if="pending_bkash"
             class="mb-5 rounded-2xl bg-amber-50 border border-amber-200 px-5 py-4">
            <div class="flex items-start gap-3">
                <span class="grid place-items-center h-9 w-9 rounded-full bg-amber-200/60 shrink-0">
                    <svg class="h-4 w-4 text-amber-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2M12 22a10 10 0 100-20 10 10 0 000 20z"/></svg>
                </span>
                <div class="flex-1">
                    <div class="text-[14px] font-semibold text-amber-900">
                        {{ t('billing.bkash_awaiting') }}
                    </div>
                    <div class="mt-1 text-[12.5px] text-amber-800 leading-relaxed">
                        {{ t('billing.bkash_awaiting_body', { plan: pending_bkash.plan, amount: pending_bkash.amount.toLocaleString(), trx: pending_bkash.trx_id, when: pending_bkash.submitted_at, hours: bkash_manual.manual_review_hours }) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Current plan + usage -->
        <div class="mb-5">
            <div class="glass rounded-2xl p-6">
                <div class="flex items-start justify-between mb-5 gap-4 flex-wrap">
                    <div>
                        <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('billing.current_plan') }}</div>
                        <div class="mt-1 text-[28px] font-extrabold tracking-tight capitalize">{{ org.plan }}</div>
                        <div v-if="subscription" class="mt-2 flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] uppercase border" :class="subStatusBadge(subscription.status)">{{ subscription.status }}</span>
                            <span class="text-[12px] font-mono text-ink-500">
                                <template v-if="subscription.auto_renew">{{ t('billing.renews_on', { date: subscription.until }) }}</template>
                                <template v-else>{{ t('billing.access_until', { date: subscription.until }) }}</template>
                            </span>
                            <span class="text-[12px] font-mono text-ink-400">· {{ subscription.provider }}</span>
                        </div>
                    </div>
                    <div v-if="subscription" class="text-right space-y-1.5 min-w-[180px]">
                        <div class="flex justify-end">
                            <Toggle :model-value="!!subscription.auto_renew"
                                    @update:model-value="toggleAutoRenew"
                                    :on-label="t('billing.auto_renew_on')"
                                    :off-label="t('billing.auto_renew_off')" />
                        </div>
                        <div v-if="subscription.status === 'past_due'" class="space-x-2">
                            <button @click="renewNow" class="text-[12px] text-brand-indigo hover:underline">{{ t('billing.retry_now') }}</button>
                        </div>
                        <button v-if="subscription.auto_renew" @click="cancelSub" class="text-[11.5px] text-rose-500 hover:text-rose-700 block ml-auto">{{ t('billing.cancel_subscription') }}</button>
                    </div>
                </div>

                <!-- Past-due / dunning banner -->
                <div v-if="subscription && subscription.status === 'past_due'"
                     class="mb-5 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-[13px] text-amber-900">
                    <strong>{{ t('billing.dunning_intro') }}</strong>
                    {{ t('billing.attempt_n_of_3', { n: subscription.renewal_attempts }) }}
                    <span v-if="subscription.last_failure" class="block font-mono text-[11.5px] mt-0.5">{{ t('billing.reason', { r: subscription.last_failure }) }}</span>
                    <span v-if="subscription.next_attempt_at" class="block text-[11.5px] mt-0.5">{{ t('billing.next_retry', { t: subscription.next_attempt_at }) }}</span>
                    <span v-if="subscription.grace_until" class="block text-[11.5px] mt-0.5">{{ t('billing.downgrades_on', { d: subscription.grace_until }) }}</span>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-[12.5px] mb-1.5">
                            <span class="text-ink-600">{{ t('billing.usage_seasons') }}</span>
                            <span class="font-mono">{{ usage.seasons }} / {{ cap(org.limits.seasons) }}</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" :style="{ width: pct(usage.seasons, org.limits.seasons) + '%' }"></div></div>
                    </div>
                    <div>
                        <div class="flex justify-between text-[12.5px] mb-1.5">
                            <span class="text-ink-600">{{ t('billing.usage_players') }}</span>
                            <span class="font-mono">{{ usage.players }} / {{ cap(org.limits.players) }}</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" :style="{ width: pct(usage.players, org.limits.players) + '%' }"></div></div>
                    </div>
                    <div>
                        <div class="flex justify-between text-[12.5px] mb-1.5">
                            <span class="text-ink-600">{{ t('billing.usage_teams') }}</span>
                            <span class="font-mono">{{ usage.teams }} / {{ cap(org.limits.teams) }}</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" :style="{ width: pct(usage.teams, org.limits.teams) + '%' }"></div></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Plan grid -->
        <div class="flex items-center justify-between mb-3 flex-wrap gap-3">
            <h3 class="text-[16px] font-bold tracking-tight">{{ t('billing.upgrade_plan_heading') }}</h3>
            <Toggle v-model="wantAutoRenew"
                    :on-label="t('billing.auto_renew_label')"
                    :off-label="t('billing.pay_once_label')" />
        </div>
        <div class="grid md:grid-cols-3 gap-4 mb-5">
            <div v-for="p in plans" :key="p.plan"
                 class="rounded-2xl p-6 transition flex flex-col"
                 :class="org.plan === p.plan ? 'glass-strong shadow-pricing-pop' : 'glass'">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[18px] font-bold tracking-tight">{{ planLabel(p.plan) }}</div>
                    <span v-if="org.plan === p.plan" class="px-2 py-0.5 rounded-full font-mono text-[9.5px] tracking-widest bg-emerald-50 text-emerald-700 border border-emerald-100">{{ t('billing.badge_current') }}</span>
                </div>
                <div class="flex items-baseline gap-1 mb-1">
                    <span class="text-[18px] text-ink-700 font-semibold">৳</span>
                    <span class="text-[34px] font-extrabold tracking-tight leading-none">{{ p.amount_bdt.toLocaleString() }}</span>
                    <span class="text-[12px] text-ink-500 ml-1">{{ t('billing.per_month') }}</span>
                </div>
                <div class="font-mono text-[10.5px] text-ink-500 mb-5">{{ t('billing.or_paypal_usd', { usd: p.amount_usd }) }}</div>

                <div v-if="org.plan === p.plan" class="mt-auto text-center">
                    <span class="text-[12.5px] text-ink-500">{{ t('billing.youre_on_plan') }}</span>
                </div>
                <div v-else class="mt-auto space-y-2">
                    <button @click="openBkash(p)" class="w-full btn-primary py-2.5 text-[13px]">
                        {{ t('billing.pay_bdt_bkash', { amount: p.amount_bdt.toLocaleString() }) }}
                    </button>
                    <button @click="checkout(p.plan, 'paypal')" class="w-full btn-ghost py-2.5 text-[13px]">
                        {{ t('billing.pay_usd_paypal', { amount: p.amount_usd }) }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Transactions -->
        <div class="glass rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-ink-100">
                <h3 class="text-[15px] font-bold tracking-tight">{{ t('billing.payment_history') }}</h3>
            </div>
            <table class="w-full text-[13px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">{{ t('billing.th_ref') }}</th>
                        <th class="px-4 py-2.5">{{ t('billing.th_provider') }}</th>
                        <th class="px-4 py-2.5">{{ t('billing.th_plan') }}</th>
                        <th class="px-4 py-2.5">{{ t('billing.th_amount') }}</th>
                        <th class="px-4 py-2.5">{{ t('billing.th_status') }}</th>
                        <th class="px-4 py-2.5">{{ t('billing.th_when') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="t in transactions" :key="t.id">
                        <td class="px-4 py-2.5 font-mono text-[11.5px]">{{ t.local_ref }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ t.provider }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ t.plan }}</td>
                        <td class="px-4 py-2.5 font-mono">{{ t.currency }} {{ t.amount }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px]" :class="txnStatusColor(t.status)">{{ txnStatusLabel(t.status) }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-500">{{ t.created_at }}</td>
                    </tr>
                    <tr v-if="transactions.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-[13px] text-ink-500">{{ t('billing.no_payments') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Manual bKash payment modal -->
        <div v-if="bkashModal" class="fixed inset-0 z-50 grid place-items-center bg-ink-900/50 backdrop-blur-sm p-4" @click.self="closeBkash">
            <div class="glass-strong rounded-2xl max-w-md w-full p-6 shadow-glass-lg">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('billing.modal_pay_bkash') }}</div>
                        <div class="mt-1 text-[18px] font-bold tracking-tight capitalize">{{ bkashModal.plan }} · ৳{{ bkashModal.amount_bdt.toLocaleString() }}/mo</div>
                    </div>
                    <button @click="closeBkash" class="text-ink-400 hover:text-ink-700 text-[18px]">×</button>
                </div>

                <!-- Step 1: send money -->
                <div class="rounded-xl bg-pink-50 border border-pink-200 px-4 py-3 mb-4">
                    <div class="font-mono text-[10.5px] tracking-widest text-pink-700 mb-1.5">{{ t('billing.modal_step1') }}</div>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 font-mono text-[15px] font-bold text-ink-900 bg-white px-3 py-2 rounded-lg border border-pink-200">
                            {{ bkash_manual.merchant_number }}
                        </code>
                        <button type="button" @click="copyMerchant" class="btn-ghost py-2 px-3 text-[12px] whitespace-nowrap">{{ t('billing.modal_copy') }}</button>
                    </div>
                    <div class="mt-2 text-[11.5px] text-pink-700 font-mono">{{ t('billing.modal_send_money_line', { type: bkash_manual.account_type, amount: bkashModal.amount_bdt.toLocaleString() }) }}</div>
                </div>

                <!-- Instructions -->
                <p v-if="bkash_manual.instructions" class="text-[12.5px] text-ink-600 leading-relaxed mb-4 whitespace-pre-line">
                    {{ bkash_manual.instructions }}
                </p>

                <!-- Step 2: TrxID -->
                <form @submit.prevent="submitBkash" class="space-y-3">
                    <div>
                        <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('billing.modal_step2') }}</label>
                        <input v-model="bkashForm.trx_id" type="text" :placeholder="t('billing.modal_trx_placeholder')" autofocus
                               class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                        <p v-if="bkashForm.errors.trx_id" class="mt-1 text-[12px] text-rose-500">{{ bkashForm.errors.trx_id }}</p>
                        <p class="mt-1 text-[11.5px] text-ink-500">{{ t('billing.modal_trx_hint') }}</p>
                    </div>

                    <div>
                        <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('billing.modal_step3') }}</label>
                        <input v-model="bkashForm.sender_number" type="tel" inputmode="tel"
                               :placeholder="t('billing.modal_sender_placeholder')"
                               class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                        <p v-if="bkashForm.errors.sender_number" class="mt-1 text-[12px] text-rose-500">{{ bkashForm.errors.sender_number }}</p>
                        <p class="mt-1 text-[11.5px] text-ink-500">{{ t('billing.modal_sender_hint') }}</p>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-2.5 text-[12.5px] text-amber-800">
                        {{ t('billing.modal_after_submit_note', { hours: bkash_manual.manual_review_hours }) }}
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button type="button" @click="closeBkash" class="btn-ghost py-2 px-4 text-[13px] flex-1">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary py-2 px-4 text-[13px] flex-1" :disabled="bkashForm.processing">
                            {{ bkashForm.processing ? t('billing.modal_submitting') : t('billing.modal_submit_payment') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
