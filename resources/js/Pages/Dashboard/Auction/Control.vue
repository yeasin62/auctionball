<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Toggle from '@/Components/Toggle.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuctionChannel } from '@/composables/useAuctionChannel';
import { useFmt } from '@/composables/useFmt';
import { useConfirm } from '@/composables/useConfirm';

const confirmDialog = useConfirm();

const { t } = useI18n();
const _fmt = useFmt();

const props = defineProps({
    season:  Object,
    players: Array,
    teams:   Array,
    state:   Object,
});

const { state: liveState, player: livePlayer, bids: liveBids, remainingSec, timerDisplay, lastReason }
    = useAuctionChannel(props.season?.org_id, props.season?.id, {
        state:  props.state,
        player: props.state?.player,
        bids:   [],
    });

const filter   = ref('queue');
const duration = ref(60);
const customBid = ref('');
const selectedTeam = ref(null);

const fmt = _fmt.money;

const filteredPlayers = computed(() =>
    props.players.filter(p => filter.value === 'all' ? true : p.auction_status === filter.value)
);

const status = computed(() => liveState.value?.status ?? 'idle');
const isRunning = computed(() => status.value === 'running');

const liveTeam = (id) => props.teams.find(t => t.id === id);

const setPlayer = (p) => router.post(route('dashboard.auction.set-player'), { player_id: p.id }, { preserveScroll: true });
const start  = () => router.post(route('dashboard.auction.start'), { duration: duration.value }, { preserveScroll: true });
const pause  = () => router.post(route('dashboard.auction.pause'), {}, { preserveScroll: true });
const resume = () => router.post(route('dashboard.auction.resume'), {}, { preserveScroll: true });
const sold   = () => router.post(route('dashboard.auction.sold'), {}, { preserveScroll: true });
const unsold = () => router.post(route('dashboard.auction.unsold'), {}, { preserveScroll: true });
const reset  = async () => {
    if (! await confirmDialog({
        title: t('auction_page.control_confirm_reset_title'),
        description: t('auction_page.control_confirm_reset_body'),
        variant: 'warning',
        confirmText: t('auction_page.control_confirm_reset_button'),
    })) return;
    router.post(route('dashboard.auction.reset'), {}, { preserveScroll: true });
};
const extendTimer = (seconds) => {
    if (! seconds || seconds < 1) return;
    router.post(route('dashboard.auction.extend'), { seconds }, { preserveScroll: true, preserveState: true });
};
const customExtend = ref(null);
const submitCustomExtend = () => {
    const n = parseInt(customExtend.value, 10);
    if (Number.isFinite(n) && n > 0) extendTimer(n);
    customExtend.value = null;
};

// Auto-finalize: when toggle is ON and the timer hits 0 while running, server
// auto-marks SOLD (if any bid landed) or UNSOLD (no bids). When OFF, the lot
// stays in 'running' status past timer_end and the auctioneer clicks manually.
const autoFinalize = ref(!! props.season?.auto_finalize);
const setAutoFinalize = (v) => {
    autoFinalize.value = v;
    router.post(route('dashboard.auction.set-auto-finalize'), { auto_finalize: v }, {
        preserveScroll: true,
        preserveState: true,
    });
};

// Guard: only fire the finalize POST once per timer-expiry. Reset whenever a
// fresh state with a future timer arrives (new lot, anti-snipe extension, etc).
const finalizeFired = ref(false);
watch(() => liveState.value?.timer_end, () => { finalizeFired.value = false; });

watch(remainingSec, (sec) => {
    if (sec > 0) return;
    if (! autoFinalize.value) return;
    if (! isRunning.value) return;
    if (finalizeFired.value) return;
    finalizeFired.value = true;
    router.post(route('dashboard.auction.finalize'), {}, {
        preserveScroll: true,
        preserveState: true,
    });
});

const placeBid = (team, amount) => {
    router.post(route('dashboard.auction.bid'), { team_id: team.id, amount }, { preserveScroll: true });
};

const incrementsFor = (current) => {
    const c = Number(current) || 0;
    return [c + 5000, c + 10000, c + 25000, c + 50000];
};

// Keyboard shortcuts: Space=start/pause, S=sold, U=unsold, R=reset
const onKey = (e) => {
    const tag = (e.target?.tagName || '').toUpperCase();
    if (['INPUT','TEXTAREA','SELECT'].includes(tag)) return;
    if (e.code === 'Space') { e.preventDefault(); isRunning.value ? pause() : (status.value === 'paused' ? resume() : start()); }
    else if (e.key === 's' || e.key === 'S') sold();
    else if (e.key === 'u' || e.key === 'U') unsold();
    else if (e.key === 'r' || e.key === 'R') reset();
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

const statusBadge = computed(() => ({
    idle:    { text: t('auction.status_idle'),    cls: 'bg-ink-100 text-ink-600 border-ink-200' },
    running: { text: t('auction.status_running'), cls: 'bg-emerald-50 text-emerald-700 border-emerald-100' },
    paused:  { text: t('auction.status_paused'),  cls: 'bg-amber-50 text-amber-700 border-amber-100' },
    sold:    { text: t('auction.status_sold'),    cls: 'bg-blue-50 text-blue-700 border-blue-100' },
    unsold:  { text: t('auction.status_unsold'),  cls: 'bg-rose-50 text-rose-700 border-rose-100' },
}[status.value] || { text: t('auction.status_idle'), cls: 'bg-ink-100 text-ink-600' }));
</script>

<template>
    <DashboardLayout :title="t('auction.live_title')">
        <div v-if="!season" class="glass rounded-2xl p-10 text-center">
            <p class="text-ink-500 text-[16px]">{{ t('auction.no_active_season') }} <Link href="/dashboard/seasons" class="text-ink-900 underline">{{ t('auction.create_or_activate_one') }}</Link>.</p>
        </div>

        <div v-else class="grid xl:grid-cols-12 gap-5">
            <!-- LEFT: Player queue -->
            <aside class="xl:col-span-3 glass rounded-2xl p-4 max-h-[82vh] flex flex-col">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-mono text-[12.5px] tracking-widest text-ink-500">{{ t('auction_page.control_label_players') }}</div>
                    <select v-model="filter" class="text-[13px] font-mono bg-transparent border-0 text-ink-700 focus:ring-0">
                        <option value="all">{{ t('auction_page.control_filter_all') }}</option>
                        <option value="queue">{{ t('auction_page.control_filter_queue') }}</option>
                        <option value="sold">{{ t('auction_page.control_filter_sold') }}</option>
                        <option value="unsold">{{ t('auction_page.control_filter_unsold') }}</option>
                    </select>
                </div>
                <ul class="space-y-1.5 overflow-y-auto flex-1">
                    <li v-for="p in filteredPlayers" :key="p.id"
                        @click="setPlayer(p)"
                        class="flex items-center gap-2.5 px-2 py-2 rounded-lg cursor-pointer transition"
                        :class="livePlayer?.id === p.id ? 'bg-blue-50 border border-blue-100' : 'hover:bg-white/60'">
                        <img v-if="p.photo_url" :src="p.photo_url" class="h-9 w-9 rounded-full object-cover border border-ink-200 shrink-0" />
                        <div v-else class="avatar text-[12px] shrink-0">{{ p.name.split(' ').map(s => s[0]).slice(0,2).join('') }}</div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[14.5px] font-medium leading-tight truncate">{{ p.name }}</div>
                            <div class="text-[12px] font-mono text-ink-500">{{ p.category }} · {{ fmt(p.base_price) }}</div>
                        </div>
                        <span class="font-mono text-[11px] uppercase px-1.5 py-0.5 rounded"
                              :class="p.auction_status === 'sold' ? 'bg-emerald-50 text-emerald-700'
                                    : p.auction_status === 'unsold' ? 'bg-ink-100 text-ink-500'
                                    : 'bg-blue-50 text-blue-700'">{{ p.auction_status }}</span>
                    </li>
                </ul>
            </aside>

            <!-- CENTER: Live panel -->
            <section class="xl:col-span-6 glass-strong rounded-2xl p-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-mono text-[12.5px] tracking-widest text-ink-500">{{ t('auction_page.control_current_player') }}</div>
                    <span class="px-3.5 py-1 rounded-full font-mono text-[12.5px] tracking-widest border" :class="statusBadge.cls">{{ statusBadge.text }}</span>
                </div>

                <!-- Empty placeholder -->
                <div v-if="!livePlayer" class="rounded-2xl bg-white/60 border border-dashed border-ink-300/60 px-6 py-12 text-center">
                    <h2 class="text-[24px] font-bold tracking-tight text-ink-700">{{ t('auction_page.control_pick_heading') }}</h2>
                    <p class="mt-2 text-[15px] text-ink-500">{{ t('auction_page.control_pick_body') }}</p>
                </div>

                <!-- Player profile card — photo + name + meta -->
                <div v-else class="rounded-2xl bg-white/70 border border-ink-200/60 p-4 sm:p-5 mb-5 flex flex-col sm:flex-row gap-4 sm:gap-5">
                    <!-- Photo -->
                    <div class="relative shrink-0 mx-auto sm:mx-0">
                        <img v-if="livePlayer.photo_url" :src="livePlayer.photo_url" :alt="livePlayer.name"
                             class="h-28 w-28 sm:h-32 sm:w-32 rounded-2xl object-cover border-2 border-white shadow-md" />
                        <div v-else class="h-28 w-28 sm:h-32 sm:w-32 rounded-2xl grid place-items-center font-extrabold text-[32px] border-2 border-white shadow-md text-indigo-700"
                             style="background:linear-gradient(135deg,rgba(186,219,255,.7),rgba(232,213,255,.85));">
                            {{ livePlayer.name.split(' ').map(s => s[0]).slice(0,2).join('') }}
                        </div>
                        <span v-if="livePlayer.jersey_no"
                              class="absolute -bottom-1.5 -right-1.5 grid place-items-center h-9 w-9 rounded-full bg-ink-900 text-white font-mono text-[13px] font-bold shadow-lg">
                            #{{ livePlayer.jersey_no }}
                        </span>
                    </div>

                    <!-- Meta -->
                    <div class="flex-1 min-w-0 text-center sm:text-left">
                        <h2 class="text-[22px] sm:text-[26px] font-extrabold tracking-tight leading-tight">{{ livePlayer.name }}</h2>
                        <div class="mt-2.5 flex flex-wrap gap-1.5 justify-center sm:justify-start">
                            <span v-if="livePlayer.position"
                                  class="px-3 py-1 rounded-full font-mono text-[12px] tracking-widest uppercase border border-indigo-200 text-indigo-700"
                                  style="background:linear-gradient(135deg,rgba(186,219,255,.55),rgba(232,213,255,.65));">
                                {{ livePlayer.position }}
                            </span>
                            <span class="px-3 py-1 rounded-full font-mono text-[12px] tracking-widest uppercase bg-ink-100 text-ink-700 border border-ink-200">
                                {{ livePlayer.category }}
                            </span>
                            <span v-if="livePlayer.player_type" class="px-3 py-1 rounded-full font-mono text-[12px] tracking-widest uppercase bg-ink-100 text-ink-700 border border-ink-200">
                                {{ livePlayer.player_type }}
                            </span>
                        </div>
                        <div class="mt-3.5 grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1.5 text-[14.5px]">
                            <div class="flex justify-between sm:block">
                                <span class="text-ink-500 sm:block sm:font-mono sm:text-[11.5px] sm:tracking-widest sm:uppercase">{{ t('auction_page.control_label_base') }}</span>
                                <span class="font-mono font-semibold text-ink-900">{{ fmt(livePlayer.base_price) }}</span>
                            </div>
                            <div v-if="livePlayer.profession" class="flex justify-between sm:block">
                                <span class="text-ink-500 sm:block sm:font-mono sm:text-[11.5px] sm:tracking-widest sm:uppercase">{{ t('auction_page.control_label_profession') }}</span>
                                <span class="font-medium text-ink-900 truncate ml-2 sm:ml-0 text-right sm:text-left">{{ livePlayer.profession }}</span>
                            </div>
                            <div v-if="livePlayer.batting_style" class="flex justify-between sm:block">
                                <span class="text-ink-500 sm:block sm:font-mono sm:text-[11.5px] sm:tracking-widest sm:uppercase">{{ t('auction_page.control_label_batting') }}</span>
                                <span class="font-medium text-ink-900 truncate ml-2 sm:ml-0 text-right sm:text-left">{{ livePlayer.batting_style }}</span>
                            </div>
                            <div v-if="livePlayer.bowling_style" class="flex justify-between sm:block">
                                <span class="text-ink-500 sm:block sm:font-mono sm:text-[11.5px] sm:tracking-widest sm:uppercase">{{ t('auction_page.control_label_bowling') }}</span>
                                <span class="font-medium text-ink-900 truncate ml-2 sm:ml-0 text-right sm:text-left">{{ livePlayer.bowling_style }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="livePlayer" class="space-y-5">

                    <!-- ============== STATS ROW: Current bid + Timer (equal weight) ============== -->
                    <div class="grid sm:grid-cols-2 gap-3 sm:gap-4">
                        <!-- Current bid -->
                        <div class="rounded-2xl px-5 py-5 text-center"
                             style="background:linear-gradient(135deg,rgba(186,219,255,.45),rgba(232,213,255,.55));border:1px solid rgba(255,255,255,.7);">
                            <div class="font-mono text-[12.5px] tracking-widest text-ink-500 mb-2">{{ t('auction_page.control_label_current_bid') }}</div>
                            <div class="font-extrabold tracking-tight text-grad leading-none whitespace-nowrap tabular-nums"
                                 style="font-size: clamp(36px, 5.5vw, 58px);">
                                {{ fmt(liveState?.highest_bid || 0) }}
                            </div>
                            <div v-if="liveState?.highest_bidder" class="mt-2.5 text-[15px] text-ink-700">
                                Leading: <span class="font-bold text-ink-900">{{ liveState.highest_bidder.name }}</span>
                            </div>
                            <div v-else class="mt-2.5 text-[15px] text-ink-500">
                                No bids yet · Floor {{ fmt(livePlayer.base_price) }}
                            </div>
                        </div>

                        <!-- Timer -->
                        <div class="rounded-2xl bg-white border border-ink-200/60 px-5 py-5 text-center">
                            <div class="font-mono text-[12.5px] tracking-widest text-ink-500 mb-2">{{ t('auction_page.control_label_timer') }}</div>
                            <div class="font-mono whitespace-nowrap leading-none tracking-tight tabular-nums"
                                 style="font-size: clamp(40px, 6vw, 66px);"
                                 :class="remainingSec <= 5 && isRunning ? 'text-rose-500 animate-pulse' : 'text-ink-900'">
                                {{ timerDisplay }}
                            </div>
                            <div class="mt-2.5 inline-flex items-center gap-1.5 text-[13px] font-mono text-ink-500">
                                <span>{{ t('auction_page.control_label_next_player') }}</span>
                                <select v-model.number="duration" class="bg-transparent border-0 focus:ring-0 text-[13px] font-mono pr-4">
                                    <option :value="30">30s</option>
                                    <option :value="60">60s</option>
                                    <option :value="90">90s</option>
                                    <option :value="120">120s</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ============== PRIMARY ACTIONS ============== -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <button v-if="!isRunning && status !== 'paused'" @click="start" class="btn-primary py-3.5 text-[15.5px] font-semibold col-span-2 sm:col-span-1">▶ {{ t('auction.btn_start') }}</button>
                        <button v-else-if="isRunning" @click="pause" class="btn-ghost py-3.5 text-[15.5px] col-span-2 sm:col-span-1">⏸ {{ t('auction.btn_pause') }}</button>
                        <button v-else-if="status === 'paused'" @click="resume" class="btn-primary py-3.5 text-[15.5px] font-semibold col-span-2 sm:col-span-1">▶ {{ t('auction.btn_resume') }}</button>

                        <button @click="sold" :disabled="!liveState?.highest_bidder"
                                class="btn-primary py-3.5 text-[15.5px] font-semibold"
                                :class="{ 'opacity-40 pointer-events-none': !liveState?.highest_bidder }">
                            ✓ {{ t('auction.btn_sold') }}
                        </button>
                        <button @click="unsold" class="rounded-xl py-3.5 text-[15.5px] font-medium bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition-colors">
                            ✗ {{ t('auction.btn_unsold') }}
                        </button>
                        <button @click="reset" class="btn-ghost py-3.5 text-[15.5px]">↺ {{ t('auction.btn_reset') }}</button>
                    </div>

                    <!-- ============== TIMER CONTROLS + AUTO-FINALIZE strip ============== -->
                    <div class="rounded-2xl bg-white/70 border border-ink-200/60 p-4 grid lg:grid-cols-[1fr_auto] gap-3 lg:gap-5 items-center">
                        <!-- Extend +N seconds -->
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="font-mono text-[12.5px] tracking-widest text-ink-500 shrink-0">{{ t('auction_page.control_label_extend_timer') }}</span>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <button v-for="s in [10, 30, 60]" :key="s"
                                        @click="extendTimer(s)" :disabled="!livePlayer"
                                        class="rounded-lg bg-white border border-ink-200/70 hover:border-brand-indigo/50 hover:bg-brand-indigo/5 transition-colors px-3.5 py-2 text-[14px] font-mono font-semibold text-ink-700"
                                        :class="{ 'opacity-40 pointer-events-none': !livePlayer }">
                                    + {{ s }}s
                                </button>
                                <div class="inline-flex items-center gap-1">
                                    <input v-model.number="customExtend" type="number" min="1" max="600"
                                           placeholder="custom"
                                           @keyup.enter="submitCustomExtend"
                                           class="w-24 rounded-md border border-ink-200/70 bg-white px-2 py-2 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-brand-indigo/30" />
                                    <button @click="submitCustomExtend" :disabled="!livePlayer || !customExtend"
                                            class="rounded-md bg-brand-indigo text-white hover:bg-brand-indigo/90 transition-colors px-3 py-2 text-[14px] font-mono font-bold"
                                            :class="{ 'opacity-40 pointer-events-none': !livePlayer || !customExtend }">
                                        +
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Auto-finalize toggle -->
                        <div class="flex items-center gap-3 lg:border-l lg:border-ink-200/60 lg:pl-5">
                            <div class="text-right lg:text-left">
                                <div class="font-mono text-[12.5px] tracking-widest text-ink-500">AUTO-FINALIZE</div>
                                <div class="text-[13px] text-ink-600">
                                    {{ autoFinalize ? 'Auto SOLD/UNSOLD on timer end' : 'Manual sold/unsold' }}
                                </div>
                            </div>
                            <Toggle :model-value="autoFinalize" @update:model-value="setAutoFinalize"
                                    :on-label="autoFinalize ? 'AUTO' : 'MANUAL'" />
                        </div>
                    </div>

                    <!-- ============== RECENT BIDS ============== -->
                    <div class="rounded-2xl bg-white/70 border border-ink-200/60 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="font-mono text-[12.5px] tracking-widest text-ink-500">{{ t('auction_page.control_label_recent_bids') }}</div>
                            <span class="font-mono text-[12.5px] text-ink-400">{{ liveBids.length }} on this lot</span>
                        </div>
                        <ul v-if="liveBids.length" class="space-y-1.5 text-[14.5px] font-mono max-h-48 overflow-y-auto">
                            <li v-for="(b, i) in liveBids" :key="b.id"
                                class="flex justify-between items-center px-3 py-2 rounded-lg"
                                :class="i === 0 ? 'bg-emerald-50 border border-emerald-100' : 'bg-white/60'">
                                <span>
                                    <span class="text-ink-400">{{ b.placed_at }}</span>
                                    <span class="text-ink-900 ml-2 font-semibold">{{ b.team }}</span>
                                </span>
                                <span class="font-bold text-ink-900">{{ fmt(b.amount) }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-center py-4 text-[14px] text-ink-500 font-mono">{{ t('auction_page.control_no_bids') }}</p>
                    </div>

                    <!-- Footer: keyboard hint -->
                    <p class="text-[12.5px] font-mono text-ink-400 text-center">
                        <i18n-t keypath="auction_page.control_shortcuts">
                            <template #space><strong class="text-ink-700">Space</strong></template>
                            <template #s><strong class="text-ink-700">S</strong></template>
                            <template #u><strong class="text-ink-700">U</strong></template>
                            <template #r><strong class="text-ink-700">R</strong></template>
                        </i18n-t>
                    </p>
                </div>

                <div v-else class="grid place-items-center min-h-[40vh] text-center">
                    <div>
                        <div class="font-mono text-[12.5px] tracking-widest text-ink-500 mb-3">/ idle</div>
                        <p class="text-[16px] text-ink-500">{{ t('auction_page.control_empty_body') }}</p>
                    </div>
                </div>
            </section>

            <!-- RIGHT: Team budgets + bid panel -->
            <aside class="xl:col-span-3 glass rounded-2xl p-4 max-h-[82vh] flex flex-col">
                <div class="font-mono text-[12.5px] tracking-widest text-ink-500 mb-3">{{ t('auction_page.control_label_team_budgets') }}</div>
                <div class="space-y-3 overflow-y-auto flex-1">
                    <div v-for="t in teams" :key="t.id"
                         @click="selectedTeam = selectedTeam?.id === t.id ? null : t"
                         class="rounded-lg p-3 cursor-pointer transition"
                         :class="selectedTeam?.id === t.id ? 'bg-blue-50 border border-blue-100' : 'hover:bg-white/60'">
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-[14.5px] font-medium">{{ t.short_code || t.name }}</span>
                            <span class="font-mono text-[13.5px] font-semibold">{{ fmt(t.remaining_budget) }}</span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill"
                                 :style="{ width: t.initial_budget > 0
                                    ? ((t.initial_budget - t.remaining_budget) / t.initial_budget * 100) + '%'
                                    : '0%' }"></div>
                        </div>

                        <div v-if="selectedTeam?.id === t.id && livePlayer && isRunning" class="mt-3 grid grid-cols-2 gap-1.5">
                            <button v-for="amt in incrementsFor(liveState?.highest_bid || livePlayer.base_price)" :key="amt"
                                    @click.stop="placeBid(t, amt)"
                                    class="btn-ghost py-2 text-[13px] font-mono"
                                    :disabled="amt > t.remaining_budget">
                                {{ fmt(amt) }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-ink-200/60 text-[13px] font-mono text-ink-500">
                    Click a team → tap an increment to place a bid as that team.
                </div>
            </aside>
        </div>
    </DashboardLayout>
</template>
