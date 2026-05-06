<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm, usePrompt } from '@/composables/useConfirm';

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
        title: 'Remove the platform logo?',
        description: 'The default gradient mark will show in headers and emails until you upload a new one.',
        variant: 'warning',
        confirmText: 'Remove logo',
    })) return;
    router.delete(route('admin.platform-settings.logo.delete'), { preserveScroll: true });
};

const props = defineProps({
    pending:  { type: Array,  default: () => [] },
    recent:   { type: Array,  default: () => [] },
    settings: { type: Object, required: true },
});

const approve = async (txn) => {
    if (! await confirm({
        title: `Approve ৳${txn.amount.toLocaleString()} from ${txn.org_name}?`,
        description: `Their plan will switch to ${txn.plan} immediately. The customer gets a notification email confirming activation. TrxID: ${txn.provider_txn_id}.`,
        variant: 'info',
        confirmText: 'Approve & activate',
    })) return;
    router.post(route('admin.payments.approve', txn.id), {}, { preserveScroll: true });
};

const reject = async (txn) => {
    // Single dialog: confirms intent AND captures the optional reason in one go.
    const reason = await promptDialog({
        title: `Reject payment from ${txn.org_name}?`,
        description: `TrxID ${txn.provider_txn_id} (৳${txn.amount.toLocaleString()}) will be marked failed and the customer will be notified by email. They can resubmit later.\n\nReason (optional, shown in the email):`,
        variant: 'danger',
        confirmText: 'Reject payment',
        placeholder: 'e.g. TrxID not found in our bKash records',
        inputRequired: false,
    });
    if (reason === null) return;
    router.post(route('admin.payments.reject', txn.id), { reason: reason || '' }, { preserveScroll: true });
};

const settingsForm = useForm({
    app_domain:            props.settings.app_domain            ?? 'auctionball.com',
    bkash_merchant_number: props.settings.bkash_merchant_number ?? '',
    bkash_account_type:    props.settings.bkash_account_type    ?? 'Personal',
    bkash_instructions:    props.settings.bkash_instructions    ?? '',
    manual_review_hours:   props.settings.manual_review_hours   ?? 6,
});
const saveSettings = () => settingsForm.patch(route('admin.platform-settings.update'), { preserveScroll: true });

const showSettings = ref(false);
</script>

<template>
    <Head title="Payments — Super admin" />
    <SuperAdminLayout title="Manual bKash payments">

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
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">PLATFORM LOGO</div>
                    <div class="mt-1 text-[13px] text-ink-700">
                        Shows in landing header, dashboard sidebar, login pages, and emails. PNG / JPG / WEBP up to 2 MB. (SVG disabled for security.)
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="btn-primary py-2 px-4 text-[13px] cursor-pointer">
                        <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onLogoPicked" />
                        {{ $page.props.appLogo ? 'Replace' : 'Upload' }}
                    </label>
                    <button v-if="$page.props.appLogo" @click="removeLogo" type="button" class="text-[11.5px] text-rose-500 hover:text-rose-700">Remove</button>
                </div>
            </div>
            <p v-if="logoForm.errors.logo" class="-mt-3 mb-3 text-[12px] text-rose-500">{{ logoForm.errors.logo }}</p>

            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">PLATFORM DOMAIN</div>
                    <div class="mt-1 text-[20px] font-extrabold tracking-tight font-mono">{{ settings.app_domain }}</div>
                    <div class="mt-3 font-mono text-[10.5px] tracking-widest text-ink-500">bKash MERCHANT</div>
                    <div class="mt-0.5 text-[15px] font-bold tracking-tight font-mono">{{ settings.bkash_merchant_number }}</div>
                    <div class="mt-0.5 text-[12px] text-ink-500">{{ settings.bkash_account_type }} · review within {{ settings.manual_review_hours }} hours</div>
                </div>
                <button @click="showSettings = !showSettings" class="btn-ghost py-2 px-4 text-[13px]">
                    {{ showSettings ? 'Close' : 'Edit settings' }}
                </button>
            </div>

            <form v-if="showSettings" @submit.prevent="saveSettings" class="mt-5 pt-5 border-t border-ink-100 grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">PLATFORM DOMAIN</label>
                    <input v-model="settingsForm.app_domain" type="text" placeholder="auctionball.com"
                           class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    <p v-if="settingsForm.errors.app_domain" class="mt-1 text-[12px] text-rose-500">{{ settingsForm.errors.app_domain }}</p>
                    <p class="mt-1 text-[11.5px] text-ink-500">Hostname only (no http:// or path). Reflects everywhere — landing, register, dashboard, PDF footers, FAQ.</p>
                </div>
                <div>
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">MERCHANT NUMBER</label>
                    <input v-model="settingsForm.bkash_merchant_number" type="text"
                           class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    <p v-if="settingsForm.errors.bkash_merchant_number" class="mt-1 text-[12px] text-rose-500">{{ settingsForm.errors.bkash_merchant_number }}</p>
                </div>
                <div>
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">ACCOUNT TYPE</label>
                    <select v-model="settingsForm.bkash_account_type"
                            class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                        <option>Personal</option>
                        <option>Merchant</option>
                        <option>Send Money</option>
                        <option>Agent</option>
                    </select>
                </div>
                <div>
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">REVIEW WINDOW (HOURS)</label>
                    <input v-model.number="settingsForm.manual_review_hours" type="number" min="1" max="72"
                           class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                </div>
                <div class="md:col-span-2">
                    <label class="font-mono text-[10.5px] tracking-widest text-ink-500">CUSTOMER INSTRUCTIONS</label>
                    <textarea v-model="settingsForm.bkash_instructions" rows="3"
                              class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[13.5px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30"></textarea>
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="btn-primary py-2 px-4 text-[13px]" :disabled="settingsForm.processing">
                        {{ settingsForm.processing ? 'Saving…' : 'Save settings' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Pending payments -->
        <div class="glass rounded-2xl overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-ink-100 flex items-center justify-between">
                <h3 class="text-[15px] font-bold tracking-tight">Pending bKash payments</h3>
                <span class="font-mono text-[11px] text-ink-500">{{ pending.length }} awaiting verification</span>
            </div>

            <table class="w-full text-[13px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">ORG</th>
                        <th class="px-4 py-2.5">PLAN</th>
                        <th class="px-4 py-2.5">AMOUNT</th>
                        <th class="px-4 py-2.5">bKash TrxID</th>
                        <th class="px-4 py-2.5">SUBMITTED</th>
                        <th class="px-4 py-2.5 text-right">ACTION</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="t in pending" :key="t.id" class="hover:bg-white/40">
                        <td class="px-4 py-3">
                            <div class="font-medium leading-tight">{{ t.org_name }}</div>
                            <div class="font-mono text-[10.5px] text-ink-400">{{ t.org_slug }} · currently {{ t.current_plan }}</div>
                        </td>
                        <td class="px-4 py-3 capitalize">
                            <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] tracking-widest bg-blue-50 text-blue-700 border border-blue-100">{{ t.plan }}</span>
                        </td>
                        <td class="px-4 py-3 font-mono font-semibold">৳{{ t.amount.toLocaleString() }}</td>
                        <td class="px-4 py-3 font-mono text-[12px]">{{ t.provider_txn_id }}</td>
                        <td class="px-4 py-3 font-mono text-[11.5px] text-ink-500">{{ t.submitted_at }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button @click="approve(t)" class="text-emerald-600 hover:text-emerald-700 text-[12px] font-semibold mr-3">Approve</button>
                            <button @click="reject(t)"  class="text-rose-500 hover:text-rose-700 text-[12px]">Reject</button>
                        </td>
                    </tr>
                    <tr v-if="pending.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-ink-500 text-[13.5px]">
                            No pending payments. 🎉
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Recent (decided) -->
        <div class="glass rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-ink-100">
                <h3 class="text-[15px] font-bold tracking-tight">Recent decisions</h3>
            </div>
            <table class="w-full text-[13px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">ORG</th>
                        <th class="px-4 py-2.5">PLAN</th>
                        <th class="px-4 py-2.5">AMOUNT</th>
                        <th class="px-4 py-2.5">TrxID</th>
                        <th class="px-4 py-2.5">RESULT</th>
                        <th class="px-4 py-2.5">WHEN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="t in recent" :key="t.id">
                        <td class="px-4 py-2.5">{{ t.org_name }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ t.plan }}</td>
                        <td class="px-4 py-2.5 font-mono">৳{{ t.amount.toLocaleString() }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px]">{{ t.provider_txn_id }}</td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px]"
                            :class="t.status === 'completed' ? 'text-emerald-700' : 'text-rose-600'">
                            {{ t.status === 'completed' ? 'APPROVED' : 'REJECTED' }}
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[11.5px] text-ink-500">{{ t.completed_at }}</td>
                    </tr>
                    <tr v-if="recent.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-[13px] text-ink-500">No history yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SuperAdminLayout>
</template>
