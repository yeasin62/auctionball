<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { useFmt } from '@/composables/useFmt';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    preview: Object,
    limits:  Object,
    used:    Number,
    season:  Object,
});

const form = useForm({ token: props.preview.token });
const confirm = () => form.post(route('dashboard.players.import.confirm'));

const fmt = useFmt().money;
</script>

<template>
    <DashboardLayout :title="t('players_import.title')">
        <template #actions>
            <Link href="/dashboard/players" class="btn-ghost py-2 px-4 text-[13px]">{{ t('common.cancel') }}</Link>
        </template>

        <div class="grid md:grid-cols-4 gap-4 mb-5">
            <div class="glass rounded-2xl p-4">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('players_import.valid_rows') }}</div>
                <div class="text-[28px] font-extrabold text-emerald-700 tracking-tight mt-1">{{ preview.valid_count }}</div>
            </div>
            <div class="glass rounded-2xl p-4">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('players_import.invalid') }}</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1" :class="preview.invalid_count > 0 ? 'text-rose-600' : 'text-ink-400'">{{ preview.invalid_count }}</div>
            </div>
            <div class="glass rounded-2xl p-4">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('players_import.plan_headroom') }}</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ preview.headroom }}</div>
                <div class="text-[11px] font-mono text-ink-500">{{ t('players_import.used_count', { used, limit: limits.players === 9223372036854775807 ? '∞' : limits.players }) }}</div>
            </div>
            <div class="glass rounded-2xl p-4 bg-gradient-to-br from-blue-50 to-violet-50 border border-violet-100">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('players_import.will_import') }}</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1 text-grad">{{ preview.will_import }}</div>
                <div v-if="preview.skipped_for_limit > 0" class="text-[11px] font-mono text-amber-700">{{ t('players_import.skipped_for_limit', { count: preview.skipped_for_limit }) }}</div>
            </div>
        </div>

        <!-- Invalid rows -->
        <div v-if="preview.invalid_count > 0" class="glass rounded-2xl p-5 mb-5 border border-rose-200/60">
            <h3 class="text-[14px] font-bold text-rose-700 mb-3">{{ t('players_import.errors_heading', { count: preview.invalid_count }) }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead><tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-2 py-1.5">{{ t('players_import.th_row') }}</th><th class="px-2 py-1.5">{{ t('players_import.th_name') }}</th><th class="px-2 py-1.5">{{ t('players_import.th_errors') }}</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="bad in preview.invalid" :key="bad.row" class="border-t border-ink-100">
                            <td class="px-2 py-1.5 font-mono text-ink-500">{{ bad.row }}</td>
                            <td class="px-2 py-1.5">{{ bad.data.name || t('players_import.empty_name') }}</td>
                            <td class="px-2 py-1.5 text-rose-600">
                                <div v-for="(e, i) in bad.errors" :key="i">{{ e }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Valid preview -->
        <div class="glass rounded-2xl overflow-hidden mb-5">
            <div class="px-5 py-3 flex items-center justify-between">
                <h3 class="text-[14px] font-bold tracking-tight">{{ t('players_import.preview_heading') }}</h3>
                <span class="text-[11px] font-mono text-ink-500">{{ t('players_import.preview_shown', { shown: preview.valid.length, total: preview.valid_count }) }}</span>
            </div>
            <table class="w-full text-[12.5px]">
                <thead class="bg-white/50">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-3 py-2">{{ t('players_import.th_name') }}</th>
                        <th class="px-3 py-2">{{ t('players_import.th_cat') }}</th>
                        <th class="px-3 py-2">{{ t('players_import.th_type') }}</th>
                        <th class="px-3 py-2">{{ t('players_import.th_base') }}</th>
                        <th class="px-3 py-2">{{ t('players_import.th_jersey') }}</th>
                        <th class="px-3 py-2">{{ t('players_import.th_batting') }}</th>
                        <th class="px-3 py-2">{{ t('players_import.th_bowling') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="(r, i) in preview.valid" :key="i">
                        <td class="px-3 py-2 font-medium">{{ r.name }}</td>
                        <td class="px-3 py-2">{{ r.category }}</td>
                        <td class="px-3 py-2">{{ r.player_type }}</td>
                        <td class="px-3 py-2 font-mono">{{ fmt(r.base_price) }}</td>
                        <td class="px-3 py-2 font-mono text-ink-500">{{ r.jersey_no || '—' }}</td>
                        <td class="px-3 py-2 text-ink-700">{{ r.batting_style || '—' }}</td>
                        <td class="px-3 py-2 text-ink-700">{{ r.bowling_style || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end gap-2">
            <Link href="/dashboard/players" class="btn-ghost py-2.5 px-5 text-[13px]">{{ t('common.cancel') }}</Link>
            <button @click="confirm" class="btn-primary py-2.5 px-5 text-[13px]"
                    :disabled="form.processing || preview.will_import === 0">
                {{ form.processing ? t('players_import.importing') : t('players_import.import_n_players', { count: preview.will_import }) }}
            </button>
        </div>
    </DashboardLayout>
</template>
