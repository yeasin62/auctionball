<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Field from '@/Components/Field.vue';
import TextField from '@/Components/TextField.vue';
import ImageCropper from '@/Components/ImageCropper.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({ org: Object, season: Object });
const page = usePage();

const form = useForm({
    name: '',
    short_code: '',
    owner_name: '',
    logo: null,
    registration_txn_id: '',
});

const fee = computed(() => props.season.team_registration_fee);

const fmt = (n) => {
    const cur  = props.org?.display_currency ?? 'BDT';
    const rate = Math.max(1, props.org?.bdt_per_usd ?? 110);
    const lang = (page.props.locale === 'bn') ? 'bn-IN' : 'en-IN';
    const v    = Number(n) || 0;
    if (cur === 'USD') return '$' + new Intl.NumberFormat(lang).format(Math.round(v / rate));
    return '৳' + new Intl.NumberFormat(lang).format(v);
};

const submit = () => form.post(route('public-team-register.store', props.season.token), {
    forceFormData: true,
    onSuccess: () => form.reset(),
});

// Auto-suggest short code from name as the user types (only if not edited manually).
const onNameInput = () => {
    if (! form.short_code) {
        form.short_code = form.name.split(' ')
            .filter(Boolean)
            .map((s) => s[0])
            .slice(0, 3)
            .join('')
            .toUpperCase();
    }
};
</script>

<template>
    <Head :title="t('public_register.head_title_team', { org: org.name, season: season.name })" />
    <div class="page-bg min-h-screen">
        <header class="px-6 py-5 border-b border-ink-200/40 bg-white/40 backdrop-blur-md">
            <div class="max-w-2xl mx-auto flex items-center gap-3">
                <img v-if="org.logo_url" :src="org.logo_url" :alt="org.name"
                     class="h-9 w-9 rounded-lg object-cover bg-white border border-ink-200" />
                <span v-else class="grid place-items-center h-9 w-9 rounded-lg bg-gradient-brand shadow-cta">
                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M8 8h5"/></svg>
                </span>
                <div>
                    <div class="text-[15px] font-bold tracking-tight">{{ org.name }}</div>
                    <div class="font-mono text-[10.5px] text-ink-500">{{ t('public_register.subtitle_team', { season: season.name, year: season.year }) }}</div>
                </div>
            </div>
        </header>

        <main class="max-w-2xl mx-auto px-6 py-10">
            <div v-if="page.props.flash?.success" class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-[13.5px] text-emerald-800">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error" class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-[13.5px] text-rose-800">
                {{ page.props.flash.error }}
            </div>

            <div class="text-center mb-7">
                <h1 class="text-[34px] font-extrabold tracking-tight">{{ t('public_register.heading_team') }}</h1>
                <p class="mt-2 text-ink-500 max-w-md mx-auto text-[14px]">
                    {{ t('public_register.intro_team') }}
                </p>
            </div>

            <div v-if="season.team_registration_instructions" class="mb-5 rounded-xl bg-white/70 border border-ink-200/60 px-5 py-4 text-[13.5px] text-ink-700 whitespace-pre-line">
                {{ season.team_registration_instructions }}
            </div>

            <div v-if="fee > 0" class="mb-5 rounded-xl bg-gradient-to-r from-amber-50 to-amber-100/60 border border-amber-200 px-5 py-3 flex items-center gap-3">
                <span class="grid place-items-center h-9 w-9 rounded-lg bg-amber-200/70 shrink-0">
                    <svg class="h-4 w-4 text-amber-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3 1.34 3 3-1.34 3-3 3m0-12V6m0 12v2"/></svg>
                </span>
                <div class="flex-1">
                    <div class="font-mono text-[10.5px] tracking-widest text-amber-700">{{ t('public_register.team_fee_label') }}</div>
                    <div class="text-[20px] font-extrabold tracking-tight text-amber-900 leading-none">{{ fmt(fee) }}</div>
                </div>
            </div>

            <form @submit.prevent="submit" class="glass-strong rounded-2xl p-7 space-y-5">
                <div class="text-[15px] font-bold tracking-wider text-ink-800">{{ t('public_register.section_team_details') }}</div>

                <div class="grid md:grid-cols-2 gap-4">
                    <Field :label="t('public_register.team_name')" :error="form.errors.name" required>
                        <TextField v-model="form.name" @input="onNameInput" :placeholder="t('public_register.team_name_placeholder')" autofocus />
                    </Field>
                    <Field :label="t('public_register.team_short_code')" :error="form.errors.short_code" :hint="t('public_register.team_short_code_hint')">
                        <TextField v-model="form.short_code" :placeholder="t('public_register.team_short_code_placeholder')" />
                    </Field>
                    <Field :label="t('public_register.team_owner')" :error="form.errors.owner_name" required>
                        <TextField v-model="form.owner_name" :placeholder="t('public_register.team_owner_placeholder')" />
                    </Field>
                </div>

                <div class="pt-3 border-t border-ink-200/60">
                    <ImageCropper :size="400" :label="t('public_register.team_logo_label')" @update:file="form.logo = $event" />
                    <p v-if="form.errors.logo" class="mt-1.5 text-[12.5px] text-rose-500">{{ form.errors.logo }}</p>
                </div>

                <div v-if="fee > 0" class="pt-3 border-t border-ink-200/60">
                    <div class="text-[15px] font-bold tracking-wider text-ink-800 mb-3">{{ t('public_register.section_payment') }}</div>
                    <Field :label="t('public_register.trx_id_field_label', { fee: fmt(fee) })" :error="form.errors.registration_txn_id" required>
                        <TextField v-model="form.registration_txn_id" :placeholder="t('public_register.team_trx_placeholder')" />
                    </Field>
                </div>

                <button type="submit" class="btn-primary w-full py-3"
                        :disabled="form.processing"
                        :class="{ 'opacity-60 pointer-events-none': form.processing }">
                    {{ form.processing ? t('public_register.submitting') : t('public_register.submit_team') }}
                </button>
            </form>
        </main>
    </div>
</template>
