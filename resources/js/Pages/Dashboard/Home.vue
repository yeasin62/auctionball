<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Link } from '@inertiajs/vue3';
import { useFmt } from '@/composables/useFmt';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    season:     Object,
    stats:      Object,
    teams:      Array,
    recentBids: Array,
});

const fmt = useFmt().money;
</script>

<template>
    <DashboardLayout :title="t('dashboard.title')">
        <template #actions>
            <Link href="/dashboard/auction" class="btn-primary py-2 px-4 text-[13px]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 4l14 8-14 8V4z"/></svg>
                {{ t('dashboard.open_auction') }}
            </Link>
        </template>

        <!-- No active season -->
        <div v-if="!season" class="glass rounded-2xl p-10 text-center">
            <div class="font-mono text-[11px] tracking-widest text-ink-500 mb-3">{{ t('dashboard.get_started') }}</div>
            <h2 class="text-[26px] font-extrabold tracking-tight">{{ t('dashboard.no_active_season_title') }}</h2>
            <p class="mt-2 text-ink-500 max-w-md mx-auto text-[14px]">
                {{ t('dashboard.no_active_season_body') }}
            </p>
            <Link href="/dashboard/seasons" class="btn-primary inline-flex mt-6 px-5">{{ t('dashboard.create_season_cta') }}</Link>
        </div>

        <div v-else class="space-y-6">

            <!-- Active season header -->
            <div class="glass-strong rounded-2xl p-5 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[11px] tracking-widest text-ink-500">{{ t('dashboard.active_season_label') }}</div>
                    <div class="mt-1 text-[20px] font-bold tracking-tight">{{ season.name }} <span class="text-ink-400 font-normal">· {{ season.year }}</span></div>
                </div>
                <span class="px-3 py-1 rounded-full font-mono text-[10.5px] tracking-widest"
                      :class="season.status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100'
                                                         : 'bg-ink-100 text-ink-600 border border-ink-200'">
                    {{ season.status?.toUpperCase() }}
                </span>
            </div>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="glass rounded-2xl p-5">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('dashboard.stat_players') }}</div>
                    <div class="mt-2 text-[28px] font-extrabold tracking-tight">{{ stats.players_total }}</div>
                    <div class="mt-1 flex gap-3 text-[11.5px] font-mono">
                        <span class="text-emerald-600">{{ stats.players_sold }} {{ t('dashboard.sold') }}</span>
                        <span class="text-ink-400">{{ stats.players_unsold }} {{ t('dashboard.unsold') }}</span>
                        <span class="text-blue-600">{{ stats.players_queue }} {{ t('dashboard.queue') }}</span>
                    </div>
                </div>
                <div class="glass rounded-2xl p-5">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('dashboard.stat_teams') }}</div>
                    <div class="mt-2 text-[28px] font-extrabold tracking-tight">{{ stats.teams_total }}</div>
                    <div class="mt-1 text-[11.5px] font-mono text-ink-500">{{ t('dashboard.in_this_season') }}</div>
                </div>
                <div class="glass rounded-2xl p-5">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('dashboard.stat_bids') }}</div>
                    <div class="mt-2 text-[28px] font-extrabold tracking-tight">{{ stats.bids_total }}</div>
                    <div class="mt-1 text-[11.5px] font-mono text-ink-500">{{ t('dashboard.total_this_season') }}</div>
                </div>
                <div class="glass rounded-2xl p-5">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('dashboard.stat_budget_left') }}</div>
                    <div class="mt-2 text-[20px] font-extrabold tracking-tight text-grad">{{ fmt(stats.budget_left) }}</div>
                    <div class="mt-1 text-[11.5px] font-mono text-ink-500">{{ t('dashboard.of_total', { total: fmt(stats.budget_total) }) }}</div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-5">
                <!-- Team budgets -->
                <div class="lg:col-span-2 glass rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-[16px] font-bold tracking-tight">{{ t('dashboard.team_budgets') }}</h3>
                        <Link href="/dashboard/teams" class="text-[12.5px] font-mono text-brand-indigo hover:underline">{{ t('dashboard.view_all') }}</Link>
                    </div>

                    <div v-if="teams.length === 0" class="text-center py-10 text-ink-500 text-[13.5px]">
                        {{ t('dashboard.no_teams') }} <Link href="/dashboard/teams" class="text-ink-900 underline">{{ t('dashboard.add_team_link') }}</Link>
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="team in teams" :key="team.id">
                            <div class="flex justify-between mb-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[10.5px] tracking-wider px-1.5 py-0.5 rounded bg-ink-100">{{ team.short }}</span>
                                    <span class="text-[13.5px] font-medium">{{ team.name }}</span>
                                </div>
                                <span class="font-mono text-[12px] text-ink-700">
                                    {{ fmt(team.spent) }} <span class="text-ink-400">/ {{ fmt(team.initial) }}</span>
                                </span>
                            </div>
                            <div class="bar-track"><div class="bar-fill" :style="{ width: team.pct + '%' }"></div></div>
                        </div>
                    </div>
                </div>

                <!-- Recent bids -->
                <div class="glass rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-[16px] font-bold tracking-tight">{{ t('dashboard.recent_bids') }}</h3>
                    </div>
                    <ul v-if="recentBids.length" class="space-y-3">
                        <li v-for="b in recentBids" :key="b.id" class="flex items-center gap-3 text-[12.5px]">
                            <span class="font-mono text-ink-400 w-16 shrink-0">{{ b.placed_at }}</span>
                            <span class="flex-1 truncate">
                                <span class="font-semibold">{{ b.team }}</span>
                                <span class="text-ink-500">{{ t('dashboard.bid_on') }}</span>
                                <span class="text-ink-900">{{ b.player }}</span>
                            </span>
                            <span class="font-mono font-semibold text-ink-900">{{ fmt(b.amount) }}</span>
                        </li>
                    </ul>
                    <div v-else class="text-center py-8 text-[13px] text-ink-500">{{ t('dashboard.no_bids') }}</div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
