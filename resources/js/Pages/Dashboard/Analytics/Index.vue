<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useFmt } from '@/composables/useFmt';
import { useI18n, I18nT } from 'vue-i18n';

const { t } = useI18n();

const _fmt = useFmt();

const props = defineProps({
    season:  Object,
    summary: Object,
    plan:    String,
    gated:   Boolean,
});

const fmt  = _fmt.money;
const fmtN = _fmt.number;

const maxTeamSpend = computed(() =>
    Math.max(1, ...(props.summary?.spend_by_team?.map(t => t.spent) ?? [0]))
);
const maxBidBucket = computed(() =>
    Math.max(1, ...(props.summary?.bid_timeline?.map(t => t.bids) ?? [0]))
);
const maxCatSpend = computed(() =>
    Math.max(1, ...(props.summary?.spend_by_category?.map(c => c.spent) ?? [0]))
);

const statusColor = (s) => ({
    sold:   '#22c55e',
    unsold: '#94a3b8',
    queue:  '#3b82f6',
}[s]);

// Donut math for status breakdown
const arcs = computed(() => {
    if (! props.summary?.status_breakdown) return [];
    const items = props.summary.status_breakdown.filter(s => s.count > 0);
    const total = items.reduce((s, x) => s + x.count, 0) || 1;
    let cursor = 0;
    return items.map(s => {
        const pct = s.count / total;
        const start = cursor;
        cursor += pct;
        return { ...s, start, end: cursor };
    });
});
const polar = (frac) => {
    const a = (frac * 2 * Math.PI) - Math.PI / 2;
    return [50 + 36 * Math.cos(a), 50 + 36 * Math.sin(a)];
};
const arcPath = (s) => {
    const [x1, y1] = polar(s.start);
    const [x2, y2] = polar(s.end);
    const large = (s.end - s.start) > 0.5 ? 1 : 0;
    return `M 50 50 L ${x1} ${y1} A 36 36 0 ${large} 1 ${x2} ${y2} Z`;
};
</script>

<template>
    <DashboardLayout :title="t('analytics_page.title')">
        <!-- Plan gate -->
        <div v-if="gated" class="glass rounded-2xl p-10 text-center">
            <div class="font-mono text-[11px] tracking-widest text-ink-500 mb-3">{{ t('analytics_page.gate_label') }}</div>
            <h2 class="text-[26px] font-extrabold tracking-tight">{{ t('analytics_page.gate_heading') }}</h2>
            <p class="mt-2 text-ink-500 max-w-md mx-auto text-[14px]">
                <I18nT keypath="analytics_page.gate_body">
                    <template #plan><strong class="capitalize">{{ plan }}</strong></template>
                </I18nT>
            </p>
            <Link href="/dashboard/billing" class="btn-primary inline-flex mt-6 px-5">{{ t('analytics_page.upgrade_to_pro') }}</Link>
        </div>

        <div v-else-if="!season" class="glass rounded-2xl p-10 text-center">
            <p class="text-ink-500 text-[14px]">
                <I18nT keypath="analytics_page.no_active_season_msg">
                    <template #link><Link href="/dashboard/seasons" class="text-ink-900 underline">{{ t('analytics_page.create_or_activate_link') }}</Link></template>
                </I18nT>
            </p>
        </div>

        <div v-else-if="summary" class="space-y-5">

            <!-- Top totals -->
            <div class="grid md:grid-cols-4 gap-4">
                <div class="glass rounded-2xl p-5">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('analytics_page.total_spent') }}</div>
                    <div class="text-[24px] font-extrabold tracking-tight mt-1 text-grad">{{ fmt(summary.totals.spent) }}</div>
                    <div class="text-[11px] font-mono text-ink-500">{{ t('analytics_page.of_pool', { total: fmt(summary.totals.budget_total) }) }}</div>
                </div>
                <div class="glass rounded-2xl p-5">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('analytics_page.players_sold') }}</div>
                    <div class="text-[24px] font-extrabold tracking-tight mt-1">{{ summary.totals.sold }}</div>
                    <div class="text-[11px] font-mono text-ink-500">{{ t('analytics_page.unsold_queue', { unsold: summary.totals.unsold, queue: summary.totals.queue }) }}</div>
                </div>
                <div class="glass rounded-2xl p-5">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('analytics_page.avg_sold_price') }}</div>
                    <div class="text-[24px] font-extrabold tracking-tight mt-1">{{ fmt(summary.totals.avg_sold_price) }}</div>
                    <div class="text-[11px] font-mono text-ink-500">{{ t('analytics_page.per_sold_player') }}</div>
                </div>
                <div class="glass rounded-2xl p-5">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">{{ t('analytics_page.total_bids') }}</div>
                    <div class="text-[24px] font-extrabold tracking-tight mt-1">{{ fmtN(summary.totals.bids_total) }}</div>
                    <div class="text-[11px] font-mono text-ink-500">{{ t('analytics_page.across_season') }}</div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-5">
                <!-- Spend by team -->
                <div class="lg:col-span-2 glass rounded-2xl p-6">
                    <h3 class="text-[16px] font-bold tracking-tight mb-5">{{ t('analytics_page.spend_by_team') }}</h3>
                    <div v-if="summary.spend_by_team.length" class="space-y-3">
                        <div v-for="team in summary.spend_by_team" :key="team.team">
                            <div class="flex justify-between text-[12.5px] mb-1">
                                <div><span class="font-mono text-[10.5px] tracking-wider px-1.5 py-0.5 rounded bg-ink-100 mr-2">{{ team.team }}</span>{{ team.name }}</div>
                                <span class="font-mono">{{ fmt(team.spent) }} <span class="text-ink-400">· {{ team.players }}p</span></span>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill" :style="{ width: (team.spent / maxTeamSpend * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-[13px] text-ink-500">{{ t('analytics_page.no_spending') }}</p>
                </div>

                <!-- Status donut -->
                <div class="glass rounded-2xl p-6">
                    <h3 class="text-[16px] font-bold tracking-tight mb-3">{{ t('analytics_page.player_status') }}</h3>
                    <div v-if="summary.totals.players > 0" class="grid place-items-center">
                        <svg viewBox="0 0 100 100" class="h-44 w-44">
                            <circle cx="50" cy="50" r="36" fill="none" stroke="rgba(15,23,42,.06)" stroke-width="14"/>
                            <path v-for="s in arcs" :key="s.status" :d="arcPath(s)" :fill="statusColor(s.status)" :opacity="0.85" />
                            <circle cx="50" cy="50" r="22" fill="#ffffff"/>
                            <text x="50" y="48" text-anchor="middle" font-size="9" font-family="JetBrains Mono" fill="#64748b">{{ t('analytics_page.total_label_donut') }}</text>
                            <text x="50" y="60" text-anchor="middle" font-size="14" font-weight="700" fill="#0a0e27">{{ summary.totals.players }}</text>
                        </svg>
                        <ul class="w-full space-y-1.5 mt-3 text-[12.5px]">
                            <li v-for="s in summary.status_breakdown" :key="s.status" class="flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full" :style="{ background: statusColor(s.status) }"></span>
                                    <span>{{ t('analytics_page.status_' + s.status) }}</span>
                                </span>
                                <span class="font-mono">{{ s.count }} · {{ s.pct }}%</span>
                            </li>
                        </ul>
                    </div>
                    <p v-else class="text-[13px] text-ink-500">{{ t('analytics_page.no_players') }}</p>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-5">
                <!-- Spend by category -->
                <div class="glass rounded-2xl p-6">
                    <h3 class="text-[16px] font-bold tracking-tight mb-5">{{ t('analytics_page.spend_by_category') }}</h3>
                    <div class="space-y-3">
                        <div v-for="c in summary.spend_by_category" :key="c.category">
                            <div class="flex justify-between text-[12.5px] mb-1">
                                <span class="font-medium">{{ c.category }} <span class="text-ink-400 font-mono ml-1">{{ c.count }}p</span></span>
                                <span class="font-mono">{{ fmt(c.spent) }} <span v-if="c.avg" class="text-ink-400">{{ t('analytics_page.avg_prefix', { amount: fmt(c.avg) }) }}</span></span>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill" :style="{ width: (c.spent / maxCatSpend * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top players -->
                <div class="glass rounded-2xl p-6">
                    <h3 class="text-[16px] font-bold tracking-tight mb-5">{{ t('analytics_page.top_sold_players') }}</h3>
                    <ol v-if="summary.top_players.length" class="space-y-2 text-[13px]">
                        <li v-for="(p, i) in summary.top_players" :key="p.name" class="flex items-center gap-3">
                            <span class="w-5 text-right font-mono text-[11px] text-ink-400">{{ i + 1 }}.</span>
                            <span class="flex-1 truncate">{{ p.name }} <span class="text-ink-400 text-[11.5px]">· {{ p.category }} · {{ p.team }}</span></span>
                            <span class="font-mono font-semibold">{{ fmt(p.sold_price) }}</span>
                        </li>
                    </ol>
                    <p v-else class="text-[13px] text-ink-500">{{ t('analytics_page.no_sold_players') }}</p>
                </div>
            </div>

            <!-- Bid timeline -->
            <div class="glass rounded-2xl p-6">
                <h3 class="text-[16px] font-bold tracking-tight mb-5">{{ t('analytics_page.bid_activity') }}</h3>
                <div v-if="summary.bid_timeline.length" class="overflow-x-auto">
                    <div class="flex items-end gap-1 h-32 min-w-full">
                        <div v-for="b in summary.bid_timeline" :key="b.bucket" class="flex-1 min-w-[12px]">
                            <div class="w-full rounded-t bg-gradient-to-t from-cyan-400 via-indigo-500 to-violet-500"
                                 :style="{ height: (b.bids / maxBidBucket * 100) + '%' }"
                                 :title="`${b.bucket} — ${b.bids} bids · ${fmt(b.spend)}`"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-[10px] font-mono text-ink-400 mt-2">
                        <span>{{ summary.bid_timeline[0]?.bucket }}</span>
                        <span>{{ summary.bid_timeline[summary.bid_timeline.length - 1]?.bucket }}</span>
                    </div>
                </div>
                <p v-else class="text-[13px] text-ink-500">{{ t('analytics_page.no_bids_yet') }}</p>
            </div>
        </div>
    </DashboardLayout>
</template>
