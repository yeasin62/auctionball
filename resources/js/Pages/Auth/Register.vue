<script setup>
import AuthShell from '@/Layouts/AuthShell.vue';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n, I18nT } from 'vue-i18n';

const { t, locale } = useI18n();

const appDomain = computed(() => usePage().props.appDomain || 'auctionball.com');

const props = defineProps({
    plans: { type: Array, default: () => ['free', 'starter', 'pro', 'enterprise'] },
    unlimited: { type: Number, default: 999999999 },
});

const normalizedPlans = computed(() => props.plans.map((plan) => {
    if (typeof plan === 'string') {
        return {
            slug: plan,
            price_bdt: null,
            seasons_limit: null,
            players_limit: null,
            teams_limit: null,
        };
    }

    return {
        slug: plan.slug,
        price_bdt: Number(plan.price_bdt ?? 0),
        seasons_limit: Number(plan.seasons_limit ?? 0),
        players_limit: Number(plan.players_limit ?? 0),
        teams_limit: Number(plan.teams_limit ?? 0),
    };
}).filter((plan) => plan.slug));

const planSlugs = computed(() => normalizedPlans.value.map((plan) => plan.slug));

const planFromUrl = (() => {
    const v = new URLSearchParams(window.location.search).get('plan');
    return v && planSlugs.value.includes(v) ? v : 'free';
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

const formatPrice = (amount) => {
    if (amount === null || amount === undefined) return null;
    if (Number(amount) === 0) return locale.value === 'bn' ? '৳০' : '৳0';

    const formatted = new Intl.NumberFormat(locale.value === 'bn' ? 'bn-BD' : 'en-US').format(Number(amount));
    return locale.value === 'bn' ? `৳${formatted}/মাস` : `৳${formatted}/mo`;
};

const limitText = (value, singular, plural) => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return null;
    if (Number(value) >= props.unlimited) return locale.value === 'bn' ? 'আনলিমিটেড' : 'Unlimited';

    const formatted = new Intl.NumberFormat(locale.value === 'bn' ? 'bn-BD' : 'en-US').format(Number(value));
    return `${formatted} ${Number(value) === 1 ? singular : plural}`;
};

const dynamicMeta = (plan) => {
    if (plan.seasons_limit === null || plan.players_limit === null || plan.teams_limit === null) {
        return null;
    }

    if (locale.value === 'bn') {
        return [
            limitText(plan.seasons_limit, 'সিজন', 'সিজন'),
            limitText(plan.players_limit, 'প্লেয়ার', 'প্লেয়ার'),
            limitText(plan.teams_limit, 'টিম', 'টিম'),
        ].filter(Boolean).join(' · ');
    }

    return [
        limitText(plan.seasons_limit, 'season', 'seasons'),
        limitText(plan.players_limit, 'player', 'players'),
        limitText(plan.teams_limit, 'team', 'teams'),
    ].filter(Boolean).join(' · ');
};

const fallbackMeta = computed(() => ({
    free: t('auth.plan_free_meta'),
    starter: t('auth.plan_starter_meta'),
    pro: t('auth.plan_pro_meta'),
    enterprise: t('auth.plan_enterprise_meta'),
}));

const planMeta = computed(() => Object.fromEntries(normalizedPlans.value.map((plan) => [
    plan.slug,
    {
        label: t(`plans.${plan.slug}`),
        price: formatPrice(plan.price_bdt) ?? t(`auth.plan_${plan.slug}_price`),
        meta: dynamicMeta(plan) ?? fallbackMeta.value[plan.slug] ?? '',
    },
])));

const visiblePlans = computed(() => normalizedPlans.value.filter((plan) => planMeta.value[plan.slug]));

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
                    <label v-for="p in visiblePlans" :key="p.slug"
                           class="cursor-pointer rounded-xl border p-3 transition"
                           :class="form.plan === p.slug
                                ? 'border-brand-indigo bg-white shadow-cta'
                                : 'border-ink-200/70 bg-white/60 hover:bg-white'">
                        <input type="radio" :value="p.slug" v-model="form.plan" class="sr-only" />
                        <div class="flex items-center justify-between">
                            <span class="text-[13.5px] font-semibold">{{ planMeta[p.slug].label }}</span>
                            <span v-if="form.plan === p.slug" class="h-4 w-4 rounded-full bg-gradient-brand"></span>
                            <span v-else class="h-4 w-4 rounded-full border border-ink-300"></span>
                        </div>
                        <div class="mt-1 text-[12.5px] font-mono text-ink-700">{{ planMeta[p.slug].price }}</div>
                        <div class="text-[11px] text-ink-500 leading-snug mt-1">{{ planMeta[p.slug].meta }}</div>
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
