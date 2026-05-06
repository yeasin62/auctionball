<script setup>
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useFmt } from '@/composables/useFmt';

const props = defineProps({
    kpi:                     Object,
    visitors:                Object,
    visitors_timeline:       Array,
    top_pages:               Array,
    source_kinds:            Object,
    top_sources:             Array,
    top_campaigns:           Array,
    signups_30d:             Array,
    users_30d:               Array,
    bids_30d:                Array,
    revenue_6m:              Array,
    revenue_by_provider:     Array,
    plan_distribution:       Object,
    sport_distribution:      Object,
    sub_status_distribution: Object,
    top_active_orgs:         Array,
});

// ============== Real-time auto-refresh ==============
// Re-fetches `visitors` every 30 seconds via Inertia partial reload so the
// "right now" count stays live. Other props refresh on the same tick — small
// queries, total payload tiny.
let pollHandle = null;
const lastRefresh = ref(new Date());

onMounted(() => {
    pollHandle = setInterval(() => {
        router.reload({
            only: ['visitors', 'kpi'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { lastRefresh.value = new Date(); },
        });
    }, 30000);
});
onBeforeUnmount(() => { if (pollHandle) clearInterval(pollHandle); });

const refreshAge = ref('just now');
setInterval(() => {
    const s = Math.floor((Date.now() - lastRefresh.value.getTime()) / 1000);
    refreshAge.value = s < 5 ? 'just now' : s < 60 ? `${s}s ago` : `${Math.floor(s/60)}m ago`;
}, 1000);

const fmt  = useFmt().money;
const fmtN = useFmt().number;

// ============== Sparkline / line chart helpers ==============
// SVG path string for an array of {date, count}, normalised to a 200x40 viewBox.
const sparkPath = (series) => {
    if (! series?.length) return '';
    const max = Math.max(1, ...series.map(p => p.count));
    const w = 200, h = 40, pad = 2;
    const dx = (w - pad * 2) / Math.max(1, series.length - 1);
    return series.map((p, i) => {
        const x = pad + i * dx;
        const y = h - pad - (p.count / max) * (h - pad * 2);
        return `${i === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`;
    }).join(' ');
};
// Same series → bar chart (200x60 viewBox)
const bars = (series) => {
    if (! series?.length) return [];
    const max = Math.max(1, ...series.map(p => p.count));
    const w = 200, h = 60, pad = 2;
    const bw = (w - pad * 2) / series.length;
    return series.map((p, i) => ({
        x: pad + i * bw + 1,
        y: h - pad - (p.count / max) * (h - pad * 2),
        w: Math.max(1, bw - 2),
        h: (p.count / max) * (h - pad * 2),
        label: p.date,
        value: p.count,
    }));
};
const sumSeries = (s) => s?.reduce((a, b) => a + b.count, 0) ?? 0;

// ============== Distribution helpers (donut) ==============
const planEntries = computed(() => Object.entries(props.plan_distribution || {}));
const sportEntries = computed(() => Object.entries(props.sport_distribution || {}));
const subEntries = computed(() => Object.entries(props.sub_status_distribution || {}));

const planColors = { free: '#94a3b8', starter: '#3b82f6', pro: '#8b5cf6', enterprise: '#f59e0b' };
const sportColors = { cricket: '#10b981', football: '#f43f5e' };
const subColors = { active: '#10b981', past_due: '#f59e0b', expired: '#ef4444', canceled: '#94a3b8' };

const donutArcs = (entries, colorMap) => {
    const total = entries.reduce((s, [, c]) => s + c, 0) || 1;
    let cursor = 0;
    return entries.map(([key, count]) => {
        const pct = count / total;
        const start = cursor; cursor += pct;
        return { key, count, pct, start, end: cursor, color: colorMap[key] || '#cbd5e1' };
    });
};
const planArcs  = computed(() => donutArcs(planEntries.value,  planColors));
const sportArcs = computed(() => donutArcs(sportEntries.value, sportColors));
const subArcs   = computed(() => donutArcs(subEntries.value,   subColors));

// Traffic source kind donut
const sourceKindEntries = computed(() => Object.entries(props.source_kinds || {}).filter(([, c]) => c > 0));
const sourceKindColors = {
    direct:   '#94a3b8',
    search:   '#3b82f6',
    social:   '#ec4899',
    referral: '#10b981',
    campaign: '#8b5cf6',
};
const sourceKindArcs = computed(() => donutArcs(sourceKindEntries.value, sourceKindColors));

const sourceKindBadgeColor = (kind) => ({
    direct:   'bg-ink-100 text-ink-600 border-ink-200',
    search:   'bg-blue-50 text-blue-700 border-blue-100',
    social:   'bg-pink-50 text-pink-700 border-pink-100',
    referral: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    campaign: 'bg-violet-50 text-violet-700 border-violet-100',
}[kind] || 'bg-ink-100 text-ink-500');

const polar = (frac) => {
    const a = (frac * 2 * Math.PI) - Math.PI / 2;
    return [50 + 36 * Math.cos(a), 50 + 36 * Math.sin(a)];
};
const arcPath = (a) => {
    const [x1, y1] = polar(a.start);
    const [x2, y2] = polar(a.end);
    const large = (a.end - a.start) > 0.5 ? 1 : 0;
    return `M 50 50 L ${x1} ${y1} A 36 36 0 ${large} 1 ${x2} ${y2} Z`;
};

// ============== Revenue 6-month bars ==============
const revenueBars = computed(() => {
    if (! props.revenue_6m?.length) return [];
    const max = Math.max(1, ...props.revenue_6m.map(r => r.amount));
    const w = 320, h = 100, pad = 4;
    const bw = (w - pad * 2) / props.revenue_6m.length;
    return props.revenue_6m.map((r, i) => ({
        x: pad + i * bw + 1,
        y: h - pad - (r.amount / max) * (h - pad * 2),
        w: Math.max(2, bw - 4),
        h: (r.amount / max) * (h - pad * 2),
        label: r.month,
        amount: r.amount,
        count: r.count,
    }));
});

// ============== Plan badge color ==============
const planColor = (p) => ({
    free:       'bg-ink-100 text-ink-700 border-ink-200',
    starter:    'bg-blue-50 text-blue-700 border-blue-100',
    pro:        'bg-violet-50 text-violet-700 border-violet-100',
    enterprise: 'bg-amber-50 text-amber-800 border-amber-100',
}[p] || 'bg-ink-100 text-ink-500');
</script>

<template>
    <Head title="Platform analytics" />
    <SuperAdminLayout title="Platform analytics">

        <!-- ============== Visitor KPIs (real-time) ============== -->
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <div class="glass rounded-2xl p-5 bg-gradient-to-br from-emerald-50 to-cyan-50 border border-emerald-100 relative">
                <div class="font-mono text-[10.5px] tracking-widest text-emerald-700">RIGHT NOW</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1 text-emerald-700 flex items-baseline gap-2">
                    {{ fmtN(visitors.realtime_now) }}
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                </div>
                <div class="text-[11.5px] font-mono text-emerald-700 mt-0.5">active in last 5 min · refreshed {{ refreshAge }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">TODAY</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ fmtN(visitors.today_unique) }}</div>
                <div class="text-[11.5px] font-mono text-ink-500 mt-0.5">unique visitors today</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">UNIQUE 30D</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ fmtN(visitors.unique_30d) }}</div>
                <div class="text-[11.5px] font-mono text-ink-500 mt-0.5">distinct visitors</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">ALL-TIME</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ fmtN(visitors.total_unique) }}</div>
                <div class="text-[11.5px] font-mono text-ink-500 mt-0.5">{{ fmtN(visitors.total_pageviews) }} page views</div>
            </div>
        </div>

        <!-- ============== Visitor timeline (30 days, daily uniques) ============== -->
        <div class="glass rounded-2xl p-5 mb-5">
            <div class="flex items-baseline justify-between mb-4">
                <h3 class="text-[14px] font-bold tracking-tight">Unique visitors per day · 30 days</h3>
                <span class="font-mono text-[11px] text-ink-500">total {{ fmtN(sumSeries(visitors_timeline)) }}</span>
            </div>
            <svg viewBox="0 0 200 60" class="w-full h-32">
                <rect v-for="(b, i) in bars(visitors_timeline)" :key="i"
                      :x="b.x" :y="b.y" :width="b.w" :height="b.h"
                      fill="#10b981" rx="0.5">
                    <title>{{ b.label }}: {{ b.value }} unique visitors</title>
                </rect>
            </svg>
            <div class="flex justify-between font-mono text-[10px] text-ink-400 mt-2">
                <span>{{ visitors_timeline?.[0]?.date }}</span>
                <span>{{ visitors_timeline?.[visitors_timeline.length - 1]?.date }}</span>
            </div>
        </div>

        <!-- ============== Traffic sources (last 30 days) ============== -->
        <div class="grid lg:grid-cols-3 gap-4 mb-5">
            <!-- Donut: kind breakdown -->
            <div class="glass rounded-2xl p-5">
                <h3 class="text-[14px] font-bold tracking-tight mb-3">Traffic by source · 30d</h3>
                <div v-if="sourceKindArcs.length === 0" class="text-center py-8 text-[13px] text-ink-500">
                    No traffic data yet — referrer + UTM tags start tracking from now.
                </div>
                <div v-else class="flex items-center gap-4">
                    <svg viewBox="0 0 100 100" class="h-32 w-32 shrink-0">
                        <circle cx="50" cy="50" r="36" fill="white" />
                        <path v-for="(a, i) in sourceKindArcs" :key="i" :d="arcPath(a)" :fill="a.color" />
                        <circle cx="50" cy="50" r="22" fill="white" />
                    </svg>
                    <ul class="space-y-1.5 flex-1 text-[12.5px]">
                        <li v-for="a in sourceKindArcs" :key="a.key" class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ background: a.color }"></span>
                                <span class="capitalize">{{ a.key }}</span>
                            </span>
                            <span class="font-mono text-ink-700"><strong>{{ a.count }}</strong> · {{ Math.round(a.pct * 100) }}%</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Top sources table (spans 2 cols) -->
            <div class="glass rounded-2xl overflow-hidden lg:col-span-2">
                <div class="px-5 py-3 border-b border-ink-100 flex items-center justify-between">
                    <h3 class="text-[14px] font-bold tracking-tight">Top sources · 30d</h3>
                    <span class="font-mono text-[10.5px] tracking-wide text-ink-500">attributed to session entry</span>
                </div>
                <table class="w-full text-[13.5px]">
                    <thead class="bg-white/40">
                        <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                            <th class="px-4 py-2.5">#</th>
                            <th class="px-4 py-2.5">SOURCE</th>
                            <th class="px-4 py-2.5">KIND</th>
                            <th class="px-4 py-2.5 text-right">VISITORS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        <tr v-for="(s, i) in top_sources" :key="i" class="hover:bg-white/40">
                            <td class="px-4 py-2.5 font-mono text-[12px] text-ink-500">{{ i + 1 }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ s.host }}</td>
                            <td class="px-4 py-2.5">
                                <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] uppercase border" :class="sourceKindBadgeColor(s.kind)">{{ s.kind }}</span>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-right font-semibold">{{ fmtN(s.visitors) }}</td>
                        </tr>
                        <tr v-if="!top_sources || top_sources.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-[13px] text-ink-500">
                                No source data — visit again with referrers / UTM links to populate.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============== Top campaigns (UTM-tagged links only) ============== -->
        <div v-if="top_campaigns && top_campaigns.length" class="glass rounded-2xl overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-ink-100 flex items-center justify-between">
                <h3 class="text-[14px] font-bold tracking-tight">UTM campaigns · 30d</h3>
                <span class="font-mono text-[10.5px] tracking-wide text-ink-500">utm_source / utm_medium / utm_campaign</span>
            </div>
            <table class="w-full text-[13.5px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">SOURCE</th>
                        <th class="px-4 py-2.5">MEDIUM</th>
                        <th class="px-4 py-2.5">CAMPAIGN</th>
                        <th class="px-4 py-2.5 text-right">VISITORS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="(c, i) in top_campaigns" :key="i" class="hover:bg-white/40">
                        <td class="px-4 py-2.5 font-mono">{{ c.utm_source }}</td>
                        <td class="px-4 py-2.5 font-mono text-ink-600">{{ c.utm_medium || '—' }}</td>
                        <td class="px-4 py-2.5 font-mono text-ink-600">{{ c.utm_campaign || '—' }}</td>
                        <td class="px-4 py-2.5 font-mono text-right font-semibold">{{ fmtN(c.visitors) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ============== Top KPIs ============== -->
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">ORGANIZATIONS</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ fmtN(kpi.orgs_total) }}</div>
                <div class="text-[11.5px] font-mono text-emerald-600 mt-0.5">+{{ fmtN(kpi.orgs_new_30d) }} in 30d</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">USERS</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ fmtN(kpi.users_total) }}</div>
                <div class="text-[11.5px] font-mono text-emerald-600 mt-0.5">+{{ fmtN(kpi.users_new_30d) }} in 30d</div>
            </div>
            <div class="glass rounded-2xl p-5 bg-gradient-to-br from-blue-50 to-violet-50 border border-violet-100">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">EST. MRR</div>
                <div class="text-[24px] font-extrabold tracking-tight mt-1 text-grad">{{ fmt(kpi.mrr_estimate) }}</div>
                <div class="text-[11.5px] font-mono text-ink-500 mt-0.5">{{ fmtN(kpi.subs_active) }} active subs</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">REVENUE 30D</div>
                <div class="text-[24px] font-extrabold tracking-tight mt-1">{{ fmt(kpi.revenue_30d) }}</div>
                <div class="text-[11.5px] font-mono text-ink-500 mt-0.5">all-time {{ fmt(kpi.revenue_total) }}</div>
            </div>
        </div>

        <!-- ============== Activity KPIs ============== -->
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">RUNNING NOW</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1 text-emerald-600">{{ fmtN(kpi.auctions_running_now) }}</div>
                <div class="text-[11.5px] font-mono text-ink-500 mt-0.5">{{ fmtN(kpi.seasons_active) }} active seasons</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">BIDS PLACED</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ fmtN(kpi.bids_30d) }}</div>
                <div class="text-[11.5px] font-mono text-ink-500 mt-0.5">last 30d · all-time {{ fmtN(kpi.bids_total) }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">PLAYERS SOLD</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1">{{ fmtN(kpi.players_sold_total) }}</div>
                <div class="text-[11.5px] font-mono text-ink-500 mt-0.5">GMV {{ fmt(kpi.gmv_sold_total) }}</div>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="font-mono text-[10.5px] tracking-widest text-ink-500">PAST DUE / EXPIRING</div>
                <div class="text-[28px] font-extrabold tracking-tight mt-1" :class="kpi.subs_past_due > 0 ? 'text-amber-600' : 'text-ink-400'">{{ fmtN(kpi.subs_past_due) }}</div>
                <div class="text-[11.5px] font-mono text-ink-500 mt-0.5">subs in dunning</div>
            </div>
        </div>

        <!-- ============== Sparkline trio: signups / users / bids ============== -->
        <div class="grid md:grid-cols-3 gap-4 mb-5">
            <div class="glass rounded-2xl p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">ORG SIGNUPS · 30 DAYS</div>
                    <div class="text-[15px] font-extrabold">{{ fmtN(sumSeries(signups_30d)) }}</div>
                </div>
                <svg viewBox="0 0 200 40" class="w-full h-12">
                    <path :d="sparkPath(signups_30d)" fill="none" stroke="#3b82f6" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">USER SIGNUPS · 30 DAYS</div>
                    <div class="text-[15px] font-extrabold">{{ fmtN(sumSeries(users_30d)) }}</div>
                </div>
                <svg viewBox="0 0 200 40" class="w-full h-12">
                    <path :d="sparkPath(users_30d)" fill="none" stroke="#10b981" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
            <div class="glass rounded-2xl p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <div class="font-mono text-[10.5px] tracking-widest text-ink-500">BIDS · 30 DAYS</div>
                    <div class="text-[15px] font-extrabold">{{ fmtN(sumSeries(bids_30d)) }}</div>
                </div>
                <svg viewBox="0 0 200 40" class="w-full h-12">
                    <path :d="sparkPath(bids_30d)" fill="none" stroke="#8b5cf6" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
        </div>

        <!-- ============== Bid activity bar chart ============== -->
        <div class="glass rounded-2xl p-5 mb-5">
            <div class="flex items-baseline justify-between mb-4">
                <h3 class="text-[14px] font-bold tracking-tight">Bids per day · 30 days</h3>
                <span class="font-mono text-[11px] text-ink-500">total {{ fmtN(sumSeries(bids_30d)) }}</span>
            </div>
            <svg viewBox="0 0 200 60" class="w-full h-32">
                <rect v-for="(b, i) in bars(bids_30d)" :key="i"
                      :x="b.x" :y="b.y" :width="b.w" :height="b.h"
                      fill="#8b5cf6" rx="0.5">
                    <title>{{ b.label }}: {{ b.value }} bids</title>
                </rect>
            </svg>
            <div class="flex justify-between font-mono text-[10px] text-ink-400 mt-2">
                <span>{{ bids_30d?.[0]?.date }}</span>
                <span>{{ bids_30d?.[bids_30d.length - 1]?.date }}</span>
            </div>
        </div>

        <!-- ============== Revenue 6 months ============== -->
        <div class="glass rounded-2xl p-5 mb-5">
            <div class="flex items-baseline justify-between mb-4">
                <h3 class="text-[14px] font-bold tracking-tight">Revenue · last 6 months</h3>
                <span class="font-mono text-[11px] text-ink-500">all-time {{ fmt(kpi.revenue_total) }}</span>
            </div>
            <div v-if="revenueBars.length === 0" class="text-center py-8 text-[13px] text-ink-500">
                No completed payments yet.
            </div>
            <svg v-else viewBox="0 0 320 100" class="w-full h-40">
                <rect v-for="(b, i) in revenueBars" :key="i"
                      :x="b.x" :y="b.y" :width="b.w" :height="b.h"
                      fill="url(#revGrad)" rx="1">
                    <title>{{ b.label }}: {{ fmt(b.amount) }} · {{ b.count }} txns</title>
                </rect>
                <defs>
                    <linearGradient id="revGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#22d3ee" />
                        <stop offset="100%" stop-color="#a78bfa" />
                    </linearGradient>
                </defs>
            </svg>
            <div v-if="revenueBars.length" class="grid grid-cols-6 gap-1 mt-2 text-center font-mono text-[10px] text-ink-500">
                <div v-for="r in revenue_6m" :key="r.month">
                    <div>{{ r.month?.slice(5) }}/{{ r.month?.slice(2,4) }}</div>
                    <div class="text-ink-700 mt-0.5">{{ fmt(r.amount) }}</div>
                </div>
            </div>
        </div>

        <!-- ============== Distribution donuts ============== -->
        <div class="grid md:grid-cols-3 gap-4 mb-5">
            <!-- Plan distribution -->
            <div class="glass rounded-2xl p-5">
                <h3 class="text-[14px] font-bold tracking-tight mb-3">Orgs by plan</h3>
                <div v-if="planArcs.length === 0" class="text-center py-6 text-[12px] text-ink-500">No orgs yet.</div>
                <div v-else class="flex items-center gap-4">
                    <svg viewBox="0 0 100 100" class="h-28 w-28 shrink-0">
                        <circle cx="50" cy="50" r="36" fill="white" />
                        <path v-for="(a, i) in planArcs" :key="i" :d="arcPath(a)" :fill="a.color" />
                        <circle cx="50" cy="50" r="22" fill="white" />
                    </svg>
                    <ul class="space-y-1.5 flex-1 text-[12.5px]">
                        <li v-for="a in planArcs" :key="a.key" class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ background: a.color }"></span>
                                <span class="capitalize">{{ a.key }}</span>
                            </span>
                            <span class="font-mono text-ink-700"><strong>{{ a.count }}</strong> · {{ Math.round(a.pct * 100) }}%</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Sport distribution -->
            <div class="glass rounded-2xl p-5">
                <h3 class="text-[14px] font-bold tracking-tight mb-3">Seasons by sport</h3>
                <div v-if="sportArcs.length === 0" class="text-center py-6 text-[12px] text-ink-500">No seasons yet.</div>
                <div v-else class="flex items-center gap-4">
                    <svg viewBox="0 0 100 100" class="h-28 w-28 shrink-0">
                        <circle cx="50" cy="50" r="36" fill="white" />
                        <path v-for="(a, i) in sportArcs" :key="i" :d="arcPath(a)" :fill="a.color" />
                        <circle cx="50" cy="50" r="22" fill="white" />
                    </svg>
                    <ul class="space-y-1.5 flex-1 text-[12.5px]">
                        <li v-for="a in sportArcs" :key="a.key" class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ background: a.color }"></span>
                                <span class="capitalize">{{ a.key || 'unset' }}</span>
                            </span>
                            <span class="font-mono text-ink-700"><strong>{{ a.count }}</strong> · {{ Math.round(a.pct * 100) }}%</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Sub status -->
            <div class="glass rounded-2xl p-5">
                <h3 class="text-[14px] font-bold tracking-tight mb-3">Subs by status</h3>
                <div v-if="subArcs.length === 0" class="text-center py-6 text-[12px] text-ink-500">No subscriptions yet.</div>
                <div v-else class="flex items-center gap-4">
                    <svg viewBox="0 0 100 100" class="h-28 w-28 shrink-0">
                        <circle cx="50" cy="50" r="36" fill="white" />
                        <path v-for="(a, i) in subArcs" :key="i" :d="arcPath(a)" :fill="a.color" />
                        <circle cx="50" cy="50" r="22" fill="white" />
                    </svg>
                    <ul class="space-y-1.5 flex-1 text-[12.5px]">
                        <li v-for="a in subArcs" :key="a.key" class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ background: a.color }"></span>
                                <span class="capitalize">{{ a.key.replace('_',' ') }}</span>
                            </span>
                            <span class="font-mono text-ink-700"><strong>{{ a.count }}</strong> · {{ Math.round(a.pct * 100) }}%</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ============== Top active orgs (last 30 days by bid count) ============== -->
        <div class="glass rounded-2xl overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-ink-100 flex items-center justify-between">
                <h3 class="text-[14px] font-bold tracking-tight">Most active orgs · last 30 days</h3>
                <span class="font-mono text-[10.5px] tracking-wide text-ink-500">by bids placed</span>
            </div>
            <table class="w-full text-[13.5px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">#</th>
                        <th class="px-4 py-2.5">ORG</th>
                        <th class="px-4 py-2.5">PLAN</th>
                        <th class="px-4 py-2.5 text-right">BIDS (30D)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="(o, i) in top_active_orgs" :key="o.id" class="hover:bg-white/40">
                        <td class="px-4 py-2.5 font-mono text-[12px] text-ink-500">{{ i + 1 }}</td>
                        <td class="px-4 py-2.5">
                            <div class="font-medium leading-tight">{{ o.name }}</div>
                            <div class="font-mono text-[10.5px] text-ink-500">{{ o.slug }}</div>
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="px-2 py-0.5 rounded-full font-mono text-[10.5px] uppercase border" :class="planColor(o.plan)">{{ o.plan }}</span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-right font-semibold">{{ fmtN(o.bids) }}</td>
                    </tr>
                    <tr v-if="top_active_orgs.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-[13px] text-ink-500">No bid activity in the last 30 days.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ============== Top pages (last 30 days) ============== -->
        <div class="glass rounded-2xl overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-ink-100 flex items-center justify-between">
                <h3 class="text-[14px] font-bold tracking-tight">Top pages · last 30 days</h3>
                <span class="font-mono text-[10.5px] tracking-wide text-ink-500">by views</span>
            </div>
            <table class="w-full text-[13.5px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">#</th>
                        <th class="px-4 py-2.5">PATH</th>
                        <th class="px-4 py-2.5 text-right">VIEWS</th>
                        <th class="px-4 py-2.5 text-right">UNIQUE VISITORS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="(p, i) in top_pages" :key="i" class="hover:bg-white/40">
                        <td class="px-4 py-2.5 font-mono text-[12px] text-ink-500">{{ i + 1 }}</td>
                        <td class="px-4 py-2.5 font-mono text-[12.5px]">{{ p.path }}</td>
                        <td class="px-4 py-2.5 font-mono text-right font-semibold">{{ fmtN(p.views) }}</td>
                        <td class="px-4 py-2.5 font-mono text-right text-ink-700">{{ fmtN(p.visitors) }}</td>
                    </tr>
                    <tr v-if="!top_pages || top_pages.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-[13px] text-ink-500">
                            No visitor data yet — visits will start counting from now on.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ============== Revenue by provider ============== -->
        <div class="glass rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-ink-100">
                <h3 class="text-[14px] font-bold tracking-tight">Revenue by payment provider · all time</h3>
            </div>
            <table class="w-full text-[13.5px]">
                <thead class="bg-white/40">
                    <tr class="text-left font-mono text-[10px] tracking-widest text-ink-500">
                        <th class="px-4 py-2.5">PROVIDER</th>
                        <th class="px-4 py-2.5 text-right">TXNS</th>
                        <th class="px-4 py-2.5 text-right">REVENUE</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <tr v-for="r in revenue_by_provider" :key="r.provider">
                        <td class="px-4 py-2.5 capitalize font-medium">{{ r.provider }}</td>
                        <td class="px-4 py-2.5 font-mono text-right">{{ fmtN(r.count) }}</td>
                        <td class="px-4 py-2.5 font-mono text-right font-semibold">{{ fmt(r.amount) }}</td>
                    </tr>
                    <tr v-if="revenue_by_provider.length === 0">
                        <td colspan="3" class="px-4 py-8 text-center text-[13px] text-ink-500">No completed payments yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </SuperAdminLayout>
</template>
