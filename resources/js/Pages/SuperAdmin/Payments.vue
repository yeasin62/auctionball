<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm, usePrompt } from '@/composables/useConfirm';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const confirm = useConfirm();
const promptDialog = usePrompt();

// Platform logo upload — bypasses the JSON settings form (multipart) and uses
// its own POST/DELETE routes so we can stream the file directly.
const logoInput = ref(null);
const logoForm = useForm({ logo: null });
const onLogoPicked = (e) => {
    const file = e.target.files?.[0];
    if (! file) return;
    logoForm.logo = file;
    logoForm.post(route('admin.platform-settings.logo.upload'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { if (logoInput.value) logoInput.value.value = ''; logoForm.reset(); },
    });
};
const removeLogo = async () => {
    if (! await confirm({
        title: t('super_admin.remove_logo_title'),
        description: t('super_admin.remove_logo_body'),
        variant: 'warning',
        confirmText: t('super_admin.remove_logo_button'),
    })) return;
    router.delete(route('admin.platform-settings.logo.delete'), { preserveScroll: true });
};

const props = defineProps({
    pending:  { type: Array,  default: () => [] },
    recent:   { type: Array,  default: () => [] },
    settings: { type: Object, required: true },
    allLandingPaymentMethods: { type: Array, default: () => [] },
});

// Display labels for the landing-page payment toggles. Keys must match
// PlatformSettings::LANDING_PAYMENT_METHODS.
const paymentMethodLabels = {
    bkash:           'bKash',
    nagad:           'Nagad',
    rocket:          'Rocket',
    sslcommerz:      'SSLCommerz',
    paypal:          'PayPal',
    visa_mastercard: 'Visa / Mastercard',
    bank_transfer:   'Bank transfer',
};

const approve = async (txn) => {
    if (! await confirm({
        title: t('super_admin.payments_approve_title', { amount: txn.amount.toLocaleString(), org: txn.org_name }),
        description: t('super_admin.payments_approve_body', { plan: txn.plan, trx: txn.provider_txn_id }),
        variant: 'info',
        confirmText: t('super_admin.payments_approve_button'),
    })) return;
    router.post(route('admin.payments.approve', txn.id), {}, { preserveScroll: true });
};

const reject = async (txn) => {
    const reason = await promptDialog({
        title: t('super_admin.payments_reject_title', { org: txn.org_name }),
        description: t('super_admin.payments_reject_body', { trx: txn.provider_txn_id, amount: txn.amount.toLocaleString() }),
        variant: 'danger',
        confirmText: t('super_admin.payments_reject_button'),
        placeholder: t('super_admin.payments_reject_reason_placeholder'),
        inputRequired: false,
    });
    if (reason === null) return;
    router.post(route('admin.payments.reject', txn.id), { reason: reason || '' }, { preserveScroll: true });
};

const settingsForm = useForm({
    app_domain:              props.settings.app_domain            ?? 'auctionball.com',
    bkash_merchant_number:   props.settings.bkash_merchant_number ?? '',
    bkash_account_type:      props.settings.bkash_account_type    ?? 'Personal',
    bkash_instructions:      props.settings.bkash_instructions    ?? '',
    manual_review_hours:     props.settings.manual_review_hours   ?? 6,
    landing_payment_methods: [...(props.settings.landing_payment_methods ?? [])],
});
const saveSettings = () => settingsForm.patch(route('admin.platform-settings.update'), { preserveScroll: true });

const togglePaymentMethod = (key) => {
    const list = settingsForm.landing_payment_methods;
    const i = list.indexOf(key);
    if (i === -1) list.push(key);
    else          list.splice(i, 1);
};

const showSettings = ref(false);
</script>

<template>
    <Head :title="t('super_admin.payments_title')" />
    <SuperAdminLayout :title="t('super_admin.payments_title')">

        <!-- Settings panel -->
        <div class="glass rounded-2xl p-6 mb-5">
            <!-- Logo block -->
            <div class="flex items-center gap-4 pb-5 mb-5 border-b border-ink-100">
                <div class="h-16 w-16 rounded-xl border border-ink-200/60 bg-white grid place-items-center overflow-hidden shrink-0">
                    <img v-if="$page.props.appLogo" :src="$page.props.appLogo" alt="Platform logo" class="h-full w-full object-contain p-2" />
                    <span v-else class="grid place-items-center h-12 w-12 rounded-lg bg-gradient-brand">
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M8 8h5"/></svg>
                    </span>
                </div>
                <div class="flex-1">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.platform_logo') }}</div>
                    <div class="mt-1 text-[13px] text-ink-700">
                        {{ t('super_admin.platform_logo_help') }}
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="btn-primary py-2 px-4 text-[13px] cursor-pointer">
                        <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onLogoPicked" />
                        {{ $page.props.appLogo ? t('super_admin.replace') : t('super_admin.upload') }}
                    </label>
                    <button v-if="$page.props.appLogo" @click="removeLogo" type="button" class="text-[11.5px] text-rose-500 hover:text-rose-700">{{ t('super_admin.remove') }}</button>
                </div>
            </div>
            <p v-if="logoForm.errors.logo" class="-mt-3 mb-3 text-[12px] text-rose-500">{{ logoForm.errors.logo }}</p>

            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.platform_domain') }}</div>
                    <div class="mt-1 text-[20px] font-extrabold tracking-tight font-mono">{{ settings.app_domain }}</div>
                    <div class="mt-3 font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.bkash_account_label') }}</div>
                    <div class="mt-0.5 text-[15px] font-bold tracking-tight font-mono">{{ settings.bkash_merchant_number }}</div>
                    <div class="mt-0.5 text-[12px] text-ink-500">{{ t('super_admin.review_within_hours', { type: settings.bkash_account_type, hours: settings.manual_review_hours }) }}</div>
                </div>
                <button @click="showSettings = !showSettings" class="btn-ghost py-2 px-4 text-[13px]">
                    {{ showSettings ? t('super_admin.close') : t('super_admin.edit_settings') }}
                </button>
            </div>

            <form v-if="showSettings" @submit.prevent="saveSettings" class="mt-5 pt-5 border-t border-ink-100 grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.platform_domain') }}</label>
                    <input v-model="settingsForm.app_domain" type="text" placeholder="auctionball.com"
                           class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    <p v-if="settingsForm.errors.app_domain" class="mt-1 text-[12px] text-rose-500">{{ settingsForm.errors.app_domain }}</p>
                    <p class="mt-1 text-[11.5px] text-ink-500">{{ t('super_admin.label_domain_help') }}</p>
                </div>
                <div>
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.label_merchant_number') }}</label>
                    <input v-model="settingsForm.bkash_merchant_number" type="text"
                           class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    <p v-if="settingsForm.errors.bkash_merchant_number" class="mt-1 text-[12px] text-rose-500">{{ settingsForm.errors.bkash_merchant_number }}</p>
                </div>
                <div>
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.label_account_type') }}</label>
                    <select v-model="settingsForm.bkash_account_type"
                            class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                        <option>Personal</option>
                        <option>Merchant</option>
                        <option>Send Money</option>
                        <option>Agent</option>
                    </select>
                </div>
                <div>
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.label_review_hours') }}</label>
                    <input v-model.number="settingsForm.manual_review_hours" type="number" min="1" max="72"
                           class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                </div>
                <div class="md:col-span-2">
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.label_instructions') }}</label>
                    <textarea v-model="settingsForm.bkash_instructions" rows="3"
                              class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30"></textarea>
                </div>

                <div class="md:col-span-2 pt-2 border-t border-ink-100">
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.landing_payments_label') }}</label>
                    <p class="mt-1 text-[11.5px] text-ink-500">{{ t('super_admin.landing_payments_help') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <label v-for="key in allLandingPaymentMethods" :key="key"
                               class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[12.5px] cursor-pointer transition-colors"
                               :class="settingsForm.landing_payment_methods.includes(key)
                                    ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
                                    : 'bg-white/70 border-ink-200/70 text-ink-500 hover:bg-white'">
                            <input type="checkbox"
                                   :checked="settingsForm.landing_payment_methods.includes(key)"
                                   @change="togglePaymentMethod(key)"
                                   class="h-3.5 w-3.5 rounded border-ink-300 text-emerald-600 focus:ring-emerald-500" />
                            {{ paymentMethodLabels[key] || key }}
                        </label>
                    </div>
                    <p v-if="settingsForm.errors.landing_payment_methods" class="mt-1 text-[12px] text-rose-500">{{ settingsForm.errors.landing_payment_methods }}</p>
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="btn-primary py-2 px-4 text-[13px]" :disabled="settingsForm.processing">
                        {{ settingsForm.processing ? t('super_admin.saving') : t('super_admin.save_settings') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Pending payments -->
        <div class="glass rounded-2xl overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-ink-100 flex items-center justify-between">
                <h3 class="text-[15px] font-bold tracking-tight">{{ t('super_admin.payments_pending_title') }}</h3>
                <span class="font-mono text-[11px] text-ink-500">{{ t('super_admin.payments_pending_awaiting', { count: pending.length }) }}</span>
            </div>

            <table class="w-full text-[13px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">{{ t('super_admin.payments_th_org') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.payments_th_plan') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.payments_th_amount') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.payments_th_trxid') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.payments_th_submitted') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('super_admin.payments_th_action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="txn in pending" :key="txn.id" class="hover:bg-white/40">
                        <td class="px-4 py-3">
                            <div class="font-medium leading-tight">{{ txn.org_name }}</div>
                            <div class="font-mono text-[10.5px] text-ink-400">{{ txn.org_slug }} · {{ t('super_admin.payments_currently_on', { plan: txn.current_plan }) }}</div>
                        </td>
                        <td class="px-4 py-3 capitalize">
                            <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] tracking-widest bg-blue-50 text-blue-700 border border-blue-100">{{ txn.plan }}</span>
                        </td>
                        <td class="px-4 py-3 font-mono font-semibold">৳{{ txn.amount.toLocaleString() }}</td>
                        <td class="px-4 py-3 font-mono text-[12px]">
                            <div>{{ txn.provider_txn_id }}</div>
                            <div v-if="txn.sender_bkash_number" class="text-[10.5px] text-ink-500 mt-0.5">{{ t('super_admin.payments_from_label') }} {{ txn.sender_bkash_number }}</div>
                        </td>
                        <td class="px-4 py-3 font-mono text-[11.5px] text-ink-500">{{ txn.submitted_at }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button @click="approve(txn)" class="text-emerald-600 hover:text-emerald-700 text-[12px] font-semibold mr-3">{{ t('super_admin.payments_approve') }}</button>
                            <button @click="reject(txn)"  class="text-rose-500 hover:text-rose-700 text-[12px]">{{ t('super_admin.payments_reject') }}</button>
                        </td>
                    </tr>
                    <tr v-if="pending.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-ink-500 text-[13.5px]">
                            {{ t('super_admin.payments_no_pending') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Recent (decided) -->
        <div class="glass rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-ink-100">
                <h3 class="text-[15px] font-bold tracking-tight">{{ t('super_admin.payments_recent') }}</h3>
            </div>
            <table class="w-full text-[13px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">{{ t('super_admin.payments_th_org') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.payments_th_plan') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.payments_th_amount') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.payments_th_trxid') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.payments_recent_th_result') }}</th>
                        <th class="px-4 py-2.5">{{ t('super_admin.th_when') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="txn in recent" :key="txn.id">
                        <td class="px-4 py-2.5">{{ txn.org_name }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ txn.plan }}</td>
                        <td class="px-4 py-2.5 font-mono">৳{{ txn.amount.toLocaleString() }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px]">{{ txn.provider_txn_id }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px]"
                            :class="txn.status === 'completed' ? 'text-emerald-700' : 'text-rose-600'">
                            {{ txn.status === 'completed' ? t('super_admin.payments_result_approved') : t('super_admin.payments_result_rejected') }}
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-500">{{ txn.completed_at }}</td>
                    </tr>
                    <tr v-if="recent.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-[13px] text-ink-500">{{ t('super_admin.payments_no_history') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SuperAdminLayout>
</template>
