<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useAuctionChannel } from '@/composables/useAuctionChannel';
import { useFmt } from '@/composables/useFmt';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    org:           Object,
    season:        Object,
    teams:         { type: Array,  default: () => [] },
    unsold_count:  { type: Number, default: 0 },
});

const { lastReason } = useAuctionChannel(props.season?.org_id, props.season?.id, {
    state: null, player: null, bids: [],
});

// When a SOLD event lands in the channel, do a partial reload of the teams
// payload so the new card slides into the right team without disturbing scroll.
watch(lastReason, (reason) => {
    if (reason === 'auction.sold' || reason === 'auction.reset') {
        router.reload({ only: ['teams', 'unsold_count'], preserveScroll: true });
    }
});

const _fmt = useFmt();
const fmt  = _fmt.money;
const ld   = _fmt.localizeDigits;
const isWhiteLabel = computed(() => usePage().props.currentOrg?.is_white_label);

const totalSoldPlayers = computed(() => props.teams.reduce((acc, t) => acc + t.players.length, 0));
const totalSpent       = computed(() => props.teams.reduce((acc, t) => acc + (t.initial_budget - t.remaining_budget), 0));

const initials = (name) => (name || '').split(' ').filter(Boolean).map(s => s[0]).slice(0, 2).join('').toUpperCase();

// Color cycle for team headers — keeps things visually distinct without per-team configuration.
const palette = [
    'from-cyan-400 to-blue-500',
    'from-indigo-400 to-violet-500',
    'from-emerald-400 to-teal-500',
    'from-amber-400 to-orange-500',
    'from-rose-400 to-pink-500',
    'from-fuchsia-400 to-purple-500',
];
const teamGradient = (idx) => palette[idx % palette.length];
</script>

<template>
    <Head :title="t('auction_page.rosters_head_title', { season: season?.name || '' })" />
    <div class="min-h-screen text-white relative overflow-x-hidden"
         style="background:linear-gradient(135deg,#0a0e27 0%,#1a1f3a 50%,#1a0f3a 100%);">
        <div class="absolute inset-0 grid-dark-bg opacity-30 pointer-events-none"></div>
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] rounded-full pointer-events-none"
             style="background:radial-gradient(circle,rgba(99,102,241,.25),transparent 70%);"></div>
        <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] rounded-full pointer-events-none"
             style="background:radial-gradient(circle,rgba(139,92,246,.2),transparent 70%);"></div>

        <!-- Top bar -->
        <header class="relative px-4 sm:px-6 lg:px-10 py-5 sm:py-6 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <img v-if="org?.logo_url" :src="org.logo_url" :alt="org.name"
                     class="h-10 w-10 rounded-xl object-cover bg-white/10" />
                <div v-else-if="!isWhiteLabel" class="grid place-items-center h-10 w-10 rounded-xl bg-gradient-brand">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M8 8h5"/></svg>
                </div>
                <div>
                    <div class="text-[18px] sm:text-[20px] font-bold tracking-tight">{{ org?.name }}</div>
                    <div class="font-mono text-[11.5px] sm:text-[12.5px] text-ink-400">{{ t('auction_page.rosters_subtitle', { season: season?.name }) }}</div>
                </div>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <div class="rounded-full px-4 py-2 font-mono text-[12px] tracking-wide bg-white/5 border border-white/10 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    {{ t('auction_page.live_summary', { sold: ld(totalSoldPlayers), spent: fmt(totalSpent) }) }}
                </div>
            </div>
        </header>

        <!-- Empty state -->
        <main v-if="!season || teams.length === 0" class="relative px-6 pb-10 grid place-items-center min-h-[60vh]">
            <div class="text-center">
                <div class="font-mono text-[12px] tracking-widest text-ink-400 mb-3">{{ t('auction_page.no_teams_label') }}</div>
                <h1 class="text-[36px] sm:text-[48px] font-extrabold tracking-tight">{{ t('auction_page.no_teams_heading') }}</h1>
                <p class="mt-4 text-[15px] text-ink-300">{{ t('auction_page.no_teams_body') }}</p>
            </div>
        </main>

        <!-- One team per row -->
        <main v-else class="relative px-4 sm:px-6 lg:px-10 pb-10 space-y-5 sm:space-y-6">
            <section v-for="(team, i) in teams" :key="team.id"
                     class="rounded-3xl bg-white/[0.04] border border-white/10 backdrop-blur-md overflow-hidden">

                <!-- Team header strip -->
                <header class="px-5 sm:px-6 py-4 sm:py-5 bg-gradient-to-r relative" :class="teamGradient(i)">
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="grid place-items-center h-14 w-14 sm:h-16 sm:w-16 rounded-2xl bg-white/15 backdrop-blur-sm font-mono text-[18px] sm:text-[22px] font-extrabold tracking-tight shrink-0">
                                {{ team.short_code || team.name?.slice(0,3).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-[22px] sm:text-[28px] font-extrabold tracking-tight truncate leading-tight">{{ team.name }}</h2>
                                <div class="font-mono text-[11.5px] sm:text-[12.5px] text-white/85 mt-0.5">
                                    {{ t('auction_page.team_players_summary', { count: ld(team.players.length), spent: fmt(team.initial_budget - team.remaining_budget) }) }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-[10.5px] tracking-widest text-white/75 uppercase">{{ t('auction_page.remaining_label') }}</div>
                            <div class="text-[24px] sm:text-[30px] font-extrabold font-mono leading-tight">{{ fmt(team.remaining_budget) }}</div>
                        </div>
                    </div>
                    <!-- Spent bar -->
                    <div class="mt-3 sm:mt-4 h-2 rounded-full bg-black/25 overflow-hidden">
                        <div class="h-full rounded-full bg-white/85 transition-all duration-700"
                             :style="{ width: team.initial_budget > 0 ? ((team.initial_budget - team.remaining_budget) / team.initial_budget * 100) + '%' : '0%' }"></div>
                    </div>
                </header>

                <!-- Player cards — horizontal grid, wider cards with full detail -->
                <div v-if="team.players.length" class="p-4 sm:p-5 grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
                    <article v-for="p in team.players" :key="p.id"
                             class="rounded-2xl bg-white/[0.05] border border-white/10 p-4 hover:bg-white/[0.08] transition-all">

                        <!-- Top row: photo + name + sold price -->
                        <div class="flex gap-3 mb-3">
                            <img v-if="p.photo_url" :src="p.photo_url" :alt="p.name"
                                 class="h-20 w-20 rounded-xl object-cover border border-white/15 shrink-0" />
                            <div v-else class="h-20 w-20 rounded-xl grid place-items-center font-mono text-[20px] font-bold border border-white/15 shrink-0"
                                 style="background:linear-gradient(135deg,rgba(99,102,241,.4),rgba(139,92,246,.4));">
                                {{ initials(p.name) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[15px] sm:text-[16px] font-bold leading-tight truncate">{{ p.name }}</div>
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    <span v-if="p.position"
                                          class="px-2 py-0.5 rounded-full font-mono text-[9.5px] tracking-widest uppercase border border-white/15"
                                          style="background:linear-gradient(135deg,rgba(99,102,241,.25),rgba(139,92,246,.25));">
                                        {{ p.position }}
                                    </span>
                                    <span v-if="p.category"
                                          class="px-2 py-0.5 rounded-full font-mono text-[9.5px] tracking-widest uppercase bg-white/10 border border-white/15">
                                        {{ p.category }}
                                    </span>
                                </div>
                                <div v-if="p.jersey_no" class="mt-1 font-mono text-[10.5px] text-ink-300">
                                    {{ t('auction_page.label_jersey', { n: ld(p.jersey_no) }) }}
                                </div>
                            </div>
                        </div>

                        <!-- Detail rows: registration_data + style if cricket + profession -->
                        <div v-if="(p.registration_data && Object.keys(p.registration_data).length) || p.batting_style || p.bowling_style || p.profession"
                             class="space-y-1 pt-3 border-t border-white/10 text-[12px] mb-3">
                            <div v-for="(entry, key) in (p.registration_data || {})" :key="key" class="flex justify-between gap-2">
                                <span class="text-ink-400 truncate">{{ entry.label }}</span>
                                <span class="text-ink-100 truncate text-right">{{ entry.value }}</span>
                            </div>
                            <div v-if="p.profession" class="flex justify-between gap-2">
                                <span class="text-ink-400">{{ t('auction_page.label_profession') }}</span>
                                <span class="text-ink-100 truncate text-right">{{ p.profession }}</span>
                            </div>
                            <div v-if="p.batting_style && season?.sport !== 'football'" class="flex justify-between gap-2">
                                <span class="text-ink-400">{{ t('auction_page.label_batting') }}</span>
                                <span class="text-ink-100 truncate text-right">{{ p.batting_style }}</span>
                            </div>
                            <div v-if="p.bowling_style && season?.sport !== 'football'" class="flex justify-between gap-2">
                                <span class="text-ink-400">{{ t('auction_page.label_bowling') }}</span>
                                <span class="text-ink-100 truncate text-right">{{ p.bowling_style }}</span>
                            </div>
                        </div>

                        <!-- Sold strip -->
                        <div class="rounded-xl px-3 py-2 flex items-center justify-between"
                             style="background:linear-gradient(90deg,rgba(34,197,94,.12),rgba(99,102,241,.12));border:1px solid rgba(255,255,255,.1);">
                            <span class="font-mono text-[10px] tracking-widest text-emerald-400 uppercase">{{ t('auction_page.sold_for') }}</span>
                            <span class="text-[18px] font-extrabold font-mono leading-none bg-clip-text text-transparent"
                                  style="background-image:linear-gradient(90deg,#67e8f9,#a78bfa);">
                                {{ fmt(p.sold_price) }}
                            </span>
                        </div>
                    </article>
                </div>

                <div v-else class="px-5 sm:px-6 py-8 text-center text-[13px] text-ink-400 font-mono">
                    {{ t('auction_page.no_picks_yet') }}
                </div>
            </section>

            <p v-if="unsold_count > 0" class="text-center font-mono text-[12px] text-ink-400 pt-2">
                {{ t('auction_page.unsold_so_far', { count: ld(unsold_count) }) }}
            </p>
        </main>

        <!-- Powered-by watermark — hidden on white-label plans -->
        <div v-if="!isWhiteLabel"
             class="absolute bottom-3 right-4 font-mono text-[10px] tracking-wide text-ink-400/60 pointer-events-none">
            powered by AuctionBall
        </div>
    </div>
</template>
