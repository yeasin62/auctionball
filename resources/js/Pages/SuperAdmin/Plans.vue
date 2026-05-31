<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n, I18nT } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    plans:     { type: Array,  required: true },
    unlimited: { type: Number, default: 999_999_999 },
});

const editing = ref(null);
const drafts  = ref({});

const startEdit = (p) => {
    editing.value = p.slug;
    drafts.value[p.slug] = useForm({
        price_bdt:     p.price_bdt,
        seasons_limit: p.seasons_limit,
        players_limit: p.players_limit,
        teams_limit:   p.teams_limit,
        watermark:     p.watermark,
        export_csv:    p.export_csv,
        export_pdf:    p.export_pdf,
        is_popular:    p.is_popular,
    });
};

const cancelEdit = () => { editing.value = null; };

const submit = (p) => {
    drafts.value[p.slug].patch(route('admin.plans.update', p.id), {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
};

const setUnlimited = (slug, field) => {
    drafts.value[slug][field] = props.unlimited;
};

const fmtLimit = (v) => v >= props.unlimited ? '∞' : new Intl.NumberFormat().format(v);
const fmtMoney = (v) => '৳' + new Intl.NumberFormat().format(v);
</script>

<template>
    <Head :title="t('super_admin.plans_head')" />
    <SuperAdminLayout :title="t('super_admin.plans_title')">
        <div class="mb-5 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-[13px] text-amber-800">
            <I18nT keypath="super_admin.plans_intro">
                <template #inf><strong>∞</strong></template>
            </I18nT>
        </div>

        <div class="grid md:grid-cols-2 gap-5">
            <div v-for="p in plans" :key="p.slug" class="glass rounded-2xl p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <div class="text-[18px] font-bold tracking-tight capitalize">{{ p.slug }}</div>
                        <div class="font-mono text-[11.5px] text-ink-500 mt-0.5">
                            {{ t('super_admin.plans_n_orgs_active', { count: p.orgs_count }) }}
                        </div>
                    </div>
                    <div v-if="editing !== p.slug" class="text-right">
                        <div class="text-[24px] font-extrabold tracking-tight">{{ fmtMoney(p.price_bdt) }}</div>
                        <div class="font-mono text-[10.5px] text-ink-400">{{ t('super_admin.plans_per_month') }}</div>
                    </div>
                </div>

                <!-- Read mode -->
                <div v-if="editing !== p.slug" class="space-y-2 text-[13px]">
                    <div class="flex justify-between"><span class="text-ink-500">{{ t('super_admin.plans_field_seasons') }}</span><span class="font-mono font-semibold">{{ fmtLimit(p.seasons_limit) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">{{ t('super_admin.plans_field_players_per') }}</span><span class="font-mono font-semibold">{{ fmtLimit(p.players_limit) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">{{ t('super_admin.plans_field_teams_per') }}</span><span class="font-mono font-semibold">{{ fmtLimit(p.teams_limit) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">{{ t('super_admin.plans_field_watermark') }}</span><span class="font-mono">{{ p.watermark ? t('super_admin.plans_yes') : t('super_admin.plans_no') }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">{{ t('super_admin.plans_field_csv') }}</span><span class="font-mono">{{ p.export_csv ? t('super_admin.plans_yes') : t('super_admin.plans_no') }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">{{ t('super_admin.plans_field_pdf') }}</span><span class="font-mono">{{ p.export_pdf ? t('super_admin.plans_yes') : t('super_admin.plans_no') }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">{{ t('super_admin.plans_field_popular') }}</span><span class="font-mono">{{ p.is_popular ? t('super_admin.plans_yes') : t('super_admin.plans_no') }}</span></div>

                    <div class="pt-3">
                        <button @click="startEdit(p)" class="btn-ghost py-2 px-4 text-[13px] w-full">{{ t('super_admin.plans_edit') }}</button>
                    </div>
                </div>

                <!-- Edit mode -->
                <form v-else @submit.prevent="submit(p)" class="space-y-3">
                    <div>
                        <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('super_admin.plans_price_label') }}</label>
                        <input v-model.number="drafts[p.slug].price_bdt" type="number" min="0"
                               class="mt-1 w-full rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                    </div>

                    <div v-for="field in [
                        { key: 'seasons_limit', label: t('super_admin.plans_seasons_label') },
                        { key: 'players_limit', label: t('super_admin.plans_players_label') },
                        { key: 'teams_limit',   label: t('super_admin.plans_teams_label') },
                    ]" :key="field.key">
                        <label class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ field.label }}</label>
                        <div class="mt-1 flex gap-2">
                            <input v-model.number="drafts[p.slug][field.key]" type="number" min="0"
                                   class="flex-1 rounded-xl border border-ink-200/70 bg-white/80 px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                            <button type="button" @click="setUnlimited(p.slug, field.key)"
                                    class="btn-ghost py-2 px-3 text-[12px] whitespace-nowrap">∞</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-2">
                        <label v-for="flag in [
                            { key: 'watermark',  label: t('super_admin.plans_flag_watermark') },
                            { key: 'export_csv', label: t('super_admin.plans_flag_csv') },
                            { key: 'export_pdf', label: t('super_admin.plans_flag_pdf') },
                            { key: 'is_popular', label: t('super_admin.plans_flag_popular') },
                        ]" :key="flag.key"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/60 border border-ink-200/60 cursor-pointer">
                            <input type="checkbox" v-model="drafts[p.slug][flag.key]" class="h-4 w-4" />
                            <span class="text-[12.5px]">{{ flag.label }}</span>
                        </label>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="cancelEdit" class="btn-ghost py-2 px-4 text-[13px] flex-1">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn-primary py-2 px-4 text-[13px] flex-1"
                                :disabled="drafts[p.slug].processing">
                            {{ drafts[p.slug].processing ? t('super_admin.saving_dots') : t('super_admin.plans_save_btn') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>
