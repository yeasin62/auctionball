<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const appDomain = computed(() => usePage().props.appDomain || 'auctionball.com');
import { useI18n } from 'vue-i18n';
import { useFmt } from '@/composables/useFmt';
import { useConfirm, useAlert } from '@/composables/useConfirm';

const confirmDialog = useConfirm();
const alertDialog   = useAlert();

const props = defineProps({ org: Object });

const { t } = useI18n();
const fmt = useFmt();
const sample = 125000;

const currencyForm = useForm({
    display_currency: props.org.display_currency || 'BDT',
    bdt_per_usd:      props.org.bdt_per_usd      || 110,
});
const saveCurrency = () => currencyForm.post(route('dashboard.settings.currency'), { preserveScroll: true });

const previewSymbol = () => currencyForm.display_currency === 'USD' ? '$' : '৳';
const previewAmount = () => {
    const n = currencyForm.display_currency === 'USD'
        ? Math.round(sample / Math.max(1, Number(currencyForm.bdt_per_usd) || 110))
        : sample;
    const loc = (fmt.locale.value === 'bn') ? 'bn-IN' : 'en-IN';
    return new Intl.NumberFormat(loc).format(n);
};

// ----- Custom domain -----
const domainForm = useForm({ custom_domain: props.org.custom_domain || '' });
const saveDomain = () => domainForm.post(route('dashboard.settings.domain'), { preserveScroll: true });
const verifyDomain = () => router.post(route('dashboard.settings.domain.verify'), {}, { preserveScroll: true });
const removeDomain = async () => {
    if (! await confirmDialog({
        title: t('settings.confirm_remove_domain'),
        variant: 'danger',
        confirmText: 'Remove domain',
    })) return;
    domainForm.custom_domain = '';
    saveDomain();
};

const domainSet = computed(() => !! props.org.custom_domain);
const domainVerified = computed(() => !! props.org.custom_domain_verified_at);
const txtHost = computed(() => '_auctionball.' + (props.org.custom_domain || 'your-domain.com'));
const txtValue = computed(() => 'ab-verify=' + (props.org.custom_domain_verification_token || ''));
const copyTxt = async () => {
    await navigator.clipboard.writeText(`${txtHost.value} TXT "${txtValue.value}"`);
    alertDialog({ title: t('settings.txt_copied'), variant: 'info' });
};
</script>

<template>
    <DashboardLayout :title="t('sidebar.settings')">
        <div class="max-w-2xl space-y-5">

            <!-- Organization (read-only) -->
            <div class="glass rounded-2xl p-6">
                <h3 class="text-[16px] font-bold tracking-tight mb-1">{{ t('settings.organization') }}</h3>
                <p class="text-[13px] text-ink-500 mb-5">{{ t('settings.organization_blurb') }}</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <Field :label="t('auth.org_name')"><TextField :modelValue="org.name" disabled /></Field>
                    <Field :label="t('auth.subdomain')"><TextField :modelValue="org.slug" :trailing="`.${appDomain}`" disabled /></Field>
                    <Field :label="t('settings_page.timezone')"><TextField :modelValue="org.timezone" disabled /></Field>
                    <Field :label="t('settings_page.plan_label')"><TextField :modelValue="org.plan" disabled /></Field>
                </div>
            </div>

            <!-- Display currency -->
            <div class="glass rounded-2xl p-6">
                <h3 class="text-[16px] font-bold tracking-tight mb-1">{{ t('settings.currency_title') }}</h3>
                <p class="text-[13px] text-ink-500 mb-5">{{ t('settings.currency_blurb') }}</p>
                <form @submit.prevent="saveCurrency" class="grid md:grid-cols-2 gap-4">
                    <Field :label="t('settings.show_prices_in')" :error="currencyForm.errors.display_currency" required>
                        <select v-model="currencyForm.display_currency"
                                class="w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30">
                            <option value="BDT">৳ — {{ t('settings.bdt_label') }}</option>
                            <option value="USD">$ — {{ t('settings.usd_label') }}</option>
                        </select>
                    </Field>
                    <Field :label="t('settings.conversion_rate')" :hint="t('settings.usd_only_hint')" :error="currencyForm.errors.bdt_per_usd" required>
                        <TextField v-model="currencyForm.bdt_per_usd" type="number" leading="৳" />
                    </Field>

                    <div class="md:col-span-2 rounded-xl bg-white/70 border border-ink-200/60 px-4 py-3 text-[13px]">
                        <span class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('settings.preview') }}</span>
                        <div class="mt-1.5 flex items-baseline gap-3">
                            <span class="text-ink-500">{{ t('settings.preview_phrase') }}</span>
                            <span class="text-[20px] font-extrabold tracking-tight text-grad">
                                {{ previewSymbol() }}{{ previewAmount() }}
                            </span>
                            <span class="font-mono text-[11px] text-ink-400">
                                ({{ fmt.locale.value === 'bn' ? 'বাংলা' : 'English' }} · {{ currencyForm.display_currency }})
                            </span>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex justify-end gap-2 pt-1">
                        <button type="submit" class="btn-primary py-2 px-4 text-[13px]" :disabled="currencyForm.processing">
                            {{ currencyForm.processing ? t('common.saving') : t('settings.save_currency') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Custom domain (white-label) -->
            <div class="glass rounded-2xl p-6">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-[16px] font-bold tracking-tight">{{ t('settings.custom_domain_title') }}</h3>
                    <span v-if="!org.is_white_label_eligible"
                          class="px-2 py-0.5 rounded-full font-mono text-[10px] tracking-widest bg-violet-50 text-violet-700 border border-violet-100">
                        {{ t('settings.pro_feature') }}
                    </span>
                    <span v-else-if="domainVerified"
                          class="px-2 py-0.5 rounded-full font-mono text-[10px] tracking-widest bg-emerald-50 text-emerald-700 border border-emerald-100">
                        {{ t('settings.verified') }}
                    </span>
                    <span v-else-if="domainSet"
                          class="px-2 py-0.5 rounded-full font-mono text-[10px] tracking-widest bg-amber-50 text-amber-700 border border-amber-100">
                        {{ t('settings.unverified') }}
                    </span>
                </div>
                <p class="text-[13px] text-ink-500 mb-5">{{ t('settings.custom_domain_blurb') }}</p>

                <div v-if="!org.is_white_label_eligible" class="rounded-xl bg-violet-50 border border-violet-200 p-4 text-[13px] text-violet-800">
                    {{ t('settings.upgrade_for_domain') }}
                    <a href="/dashboard/billing" class="underline font-medium ml-1">{{ t('sidebar.upgrade') }}</a>
                </div>

                <form v-else @submit.prevent="saveDomain" class="space-y-4">
                    <Field :label="t('settings.domain_field')" :hint="t('settings.domain_hint')" :error="domainForm.errors.custom_domain">
                        <TextField v-model="domainForm.custom_domain" placeholder="bpl-cup.example.com" autocomplete="off" />
                    </Field>

                    <div v-if="domainSet" class="rounded-xl bg-white/70 border border-ink-200/60 p-4 space-y-3">
                        <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('settings.dns_step1') }}</div>
                        <div class="grid grid-cols-3 gap-2 text-[12px] font-mono">
                            <div>
                                <div class="text-ink-400 text-[10px]">{{ t('settings.dns_type') }}</div>
                                <div class="bg-ink-100 px-2 py-1.5 rounded">TXT</div>
                            </div>
                            <div>
                                <div class="text-ink-400 text-[10px]">{{ t('settings.dns_host') }}</div>
                                <div class="bg-ink-100 px-2 py-1.5 rounded truncate" :title="txtHost">{{ txtHost }}</div>
                            </div>
                            <div>
                                <div class="text-ink-400 text-[10px]">{{ t('settings.dns_value') }}</div>
                                <div class="bg-ink-100 px-2 py-1.5 rounded truncate" :title="txtValue">{{ txtValue }}</div>
                            </div>
                        </div>
                        <div class="font-mono text-[10.5px] tracking-widest text-ink-500 pt-2">{{ t('settings.dns_step2') }}</div>
                        <div class="text-[12px] font-mono">
                            <span class="text-ink-500">CNAME</span>
                            <span class="ml-2">{{ org.custom_domain }} → cname.{{ appDomain }}</span>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="copyTxt" class="btn-ghost py-1.5 px-3 text-[12px]">{{ t('settings.copy_txt') }}</button>
                            <button type="button" @click="verifyDomain" class="btn-primary py-1.5 px-3 text-[12px]">
                                {{ domainVerified ? t('settings.reverify') : t('settings.verify_now') }}
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button v-if="domainSet" type="button" @click="removeDomain" class="text-[12px] text-rose-500 hover:text-rose-700 px-3 py-2">
                            {{ t('settings.remove_domain') }}
                        </button>
                        <button type="submit" class="btn-primary py-2 px-4 text-[13px]" :disabled="domainForm.processing">
                            {{ domainForm.processing ? t('common.saving') : t('settings.save_domain') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Danger zone -->
            <div class="glass rounded-2xl p-6 border border-rose-200/60">
                <h3 class="text-[16px] font-bold tracking-tight text-rose-700 mb-1">{{ t('settings.danger_zone') }}</h3>
                <p class="text-[13px] text-ink-500 mb-3">{{ t('settings.danger_blurb') }}</p>
                <button class="btn-ghost text-rose-600 border-rose-200 py-2 px-4 text-[13px] opacity-60" disabled>
                    {{ t('settings.delete_org_soon') }}
                </button>
            </div>
        </div>
    </DashboardLayout>
</template>
