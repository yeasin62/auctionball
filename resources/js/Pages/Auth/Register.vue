<script setup>
import AuthShell from '@/Layouts/AuthShell.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n, I18nT } from 'vue-i18n';

const { t } = useI18n();

const appDomain = computed(() => usePage().props.appDomain || 'auctionball.com');

const props = defineProps({
    plans: { type: Array, default: () => ['free', 'starter', 'pro', 'enterprise'] },
});

const planFromUrl = (() => {
    const v = new URLSearchParams(window.location.search).get('plan');
    return v && ['free', 'starter', 'pro', 'enterprise'].includes(v) ? v : 'free';
})();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    org_name: '',
    org_slug: '',
    plan: planFromUrl,
});

const slugify = (s) =>
    s.toLowerCase().trim()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60);

let slugTouched = false;
watch(() => form.org_name, (v) => {
    if (!slugTouched) form.org_slug = slugify(v);
});
const onSlugInput = (v) => { slugTouched = true; form.org_slug = slugify(v); };

const planMeta = computed(() => ({
    free:       { label: t('plans.free'),       price: t('auth.plan_free_price'),       meta: t('auth.plan_free_meta') },
    starter:    { label: t('plans.starter'),    price: t('auth.plan_starter_price'),    meta: t('auth.plan_starter_meta') },
    pro:        { label: t('plans.pro'),        price: t('auth.plan_pro_price'),        meta: t('auth.plan_pro_meta') },
    enterprise: { label: t('plans.enterprise'), price: t('auth.plan_enterprise_price'), meta: t('auth.plan_enterprise_meta') },
}));

const visiblePlans = computed(() => props.plans.filter(p => planMeta.value[p]));

const submit = () => form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <Head :title="t('auth.head_create_org')" />
    <AuthShell :title="t('auth.create_org_title')" :subtitle="t('auth.create_org_subtitle')">
        <form @submit.prevent="submit" class="space-y-5">

            <!-- Org block -->
            <div class="space-y-4">
                <div class="font-mono text-[11px] tracking-widest text-ink-500">{{ t('auth.section_organization') }}</div>
                <Field :label="t('auth.org_name')" :error="form.errors.org_name" required>
                    <TextField v-model="form.org_name" :placeholder="t('auth.org_name_placeholder')" autofocus autocomplete="organization" />
                </Field>
                <Field :label="t('auth.subdomain')" :hint="t('auth.subdomain_hint')" :error="form.errors.org_slug" required>
                    <TextField :modelValue="form.org_slug" @update:modelValue="onSlugInput"
                               leading="" :trailing="`.${appDomain}`" placeholder="bpl-2026" autocomplete="off" />
                </Field>
            </div>

            <!-- Admin block -->
            <div class="space-y-4 pt-3 border-t border-ink-200/60">
                <div class="font-mono text-[11px] tracking-widest text-ink-500">{{ t('auth.section_admin') }}</div>
                <Field :label="t('auth.your_name')" :error="form.errors.name" required>
                    <TextField v-model="form.name" :placeholder="t('auth.your_name_placeholder')" autocomplete="name" />
                </Field>
                <Field :label="t('common.email')" :error="form.errors.email" required>
                    <TextField v-model="form.email" type="email" :placeholder="t('auth.email_placeholder')" autocomplete="username" />
                </Field>
                <div class="grid grid-cols-2 gap-3">
                    <Field :label="t('common.password')" :error="form.errors.password" required>
                        <TextField v-model="form.password" type="password" autocomplete="new-password" />
                    </Field>
                    <Field :label="t('common.confirm_password')" :error="form.errors.password_confirmation" required>
                        <TextField v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                    </Field>
                </div>
            </div>

            <!-- Plan block -->
            <div class="space-y-3 pt-3 border-t border-ink-200/60">
                <div class="font-mono text-[11px] tracking-widest text-ink-500">{{ t('auth.section_plan') }}</div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <label v-for="p in visiblePlans" :key="p"
                           class="cursor-pointer rounded-xl border p-3 transition"
                           :class="form.plan === p
                                ? 'border-brand-indigo bg-white shadow-cta'
                                : 'border-ink-200/70 bg-white/60 hover:bg-white'">
                        <input type="radio" :value="p" v-model="form.plan" class="sr-only" />
                        <div class="flex items-center justify-between">
                            <span class="text-[13.5px] font-semibold">{{ planMeta[p].label }}</span>
                            <span v-if="form.plan === p" class="h-4 w-4 rounded-full bg-gradient-brand"></span>
                            <span v-else class="h-4 w-4 rounded-full border border-ink-300"></span>
                        </div>
                        <div class="mt-1 text-[12.5px] font-mono text-ink-700">{{ planMeta[p].price }}</div>
                        <div class="text-[11px] text-ink-500 leading-snug mt-1">{{ planMeta[p].meta }}</div>
                    </label>
                </div>
                <p v-if="form.errors.plan" class="text-[12.5px] text-rose-500">{{ form.errors.plan }}</p>
            </div>

            <button type="submit" class="btn-primary w-full py-3"
                    :class="{ 'opacity-60 pointer-events-none': form.processing }"
                    :disabled="form.processing">
                {{ form.processing ? t('auth.creating') : t('auth.create_org_button') }}
            </button>

            <I18nT keypath="auth.terms_agreement" tag="p" class="text-center text-[12.5px] text-ink-500 pt-1">
                <template #terms><Link href="/terms" class="text-ink-700 underline">{{ t('auth.terms') }}</Link></template>
                <template #privacy><Link href="/privacy" class="text-ink-700 underline">{{ t('auth.privacy') }}</Link></template>
            </I18nT>
        </form>

        <template #footer>
            <p class="text-[13.5px] text-ink-500">
                {{ t('auth.already_have_account') }}
                <Link :href="route('login')" class="text-ink-900 font-medium hover:underline">{{ t('auth.login_button') }}</Link>
            </p>
        </template>
    </AuthShell>
</template>
