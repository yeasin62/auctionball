<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, watch, ref } from 'vue';
import { useAuctionChannel } from '@/composables/useAuctionChannel';
import { useFmt } from '@/composables/useFmt';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    org:    Object,
    season: Object,
    state:  Object,
    teams:  { type: Array, default: () => [] },
});

const { state, player, bids, remainingSec, timerDisplay, lastReason }
    = useAuctionChannel(props.season?.org_id, props.season?.id, {
        state:  props.state,
        player: props.state?.player,
        bids:   [],
    });

const _fmt = useFmt();
const fmt  = _fmt.money;
const ld   = _fmt.localizeDigits;
const isWhiteLabel = computed(() => usePage().props.currentOrg?.is_white_label);

const showSold = ref(false);
const soldData = ref(null);

// Unsold stamp persists on the stage until the next lot or a reset — feels
// like a real ink stamp pressed onto the venue display rather than a popup
// that flashes away. Cleared when the lot changes or auction is reset.
const showUnsold = ref(false);

watch(lastReason, (reason) => {
    if (reason === 'auction.sold') {
        soldData.value = {
            player: player.value?.name,
            team:   state.value?.highest_bidder?.name,
            price:  state.value?.highest_bid,
        };
        showSold.value = true;
        showUnsold.value = false;
        return;
    }

    if (reason === 'auction.unsold') {
        showUnsold.value = true;
        showSold.value = false;
        return;
    }

    // New lot or full reset clears the persistent stamp.
    if (['player.changed', 'auction.reset', 'auction.started'].includes(reason)) {
        showSold.value = false;
        showUnsold.value = false;
    }
});

// Belt-and-braces: also clear the stamp when the player id changes by any
// other path (initial load with a different lot, manual setPlayer, etc.).
watch(() => player.value?.id, () => {
    showSold.value = false;
    showUnsold.value = false;
});

const isRunning = computed(() => state.value?.status === 'running');
</script>

<template>
    <Head :title="t('auction_page.bigscreen_head_title')" />
    <div class="min-h-screen text-white relative overflow-hidden"
         style="background:linear-gradient(135deg,#0a0e27 0%,#1a1f3a 50%,#1a0f3a 100%);">
        <div class="absolute inset-0 grid-dark-bg opacity-30 pointer-events-none"></div>
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] rounded-full pointer-events-none"
             style="background:radial-gradient(circle,rgba(99,102,241,.25),transparent 70%);"></div>
        <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] rounded-full pointer-events-none"
             style="background:radial-gradient(circle,rgba(139,92,246,.2),transparent 70%);"></div>

        <!-- Top bar -->
        <header class="relative px-10 py-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <!-- Logo: org's own (if uploaded), else AuctionBall mark — but we drop the AuctionBall mark on white-label -->
                <img v-if="org?.logo_url" :src="org.logo_url" :alt="org.name"
                     class="h-10 w-10 rounded-xl object-cover bg-white/10" />
                <div v-else-if="!isWhiteLabel" class="grid place-items-center h-10 w-10 rounded-xl bg-gradient-brand">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M8 8h5"/></svg>
                </div>
                <div>
                    <div class="text-[20px] sm:text-[22px] lg:text-[24px] font-bold tracking-tight">{{ org?.name }}</div>
                    <div class="font-mono text-[14px] sm:text-[15px] text-ink-400">{{ season?.name }}</div>
                </div>
            </div>
            <div class="flex items-center gap-2.5 px-5 py-2.5 rounded-full font-mono text-[16px] sm:text-[18px] tracking-wide bg-white/5 border border-white/10">
                <span class="h-2.5 w-2.5 rounded-full" :class="isRunning ? 'bg-emerald-400 animate-pulse' : 'bg-ink-400'"></span>
                {{ state?.status?.toUpperCase() || t('auction_page.bigscreen_status_waiting') }}
            </div>
        </header>

        <!-- Stage -->
        <main v-if="player" class="relative px-4 sm:px-6 lg:px-10 pb-6 lg:pb-10">
            <div class="grid lg:grid-cols-12 gap-4 sm:gap-6 lg:gap-8 mb-6 lg:mb-8">
            <!-- Player profile -->
            <div class="lg:col-span-4 rounded-3xl bg-white/[0.04] border border-white/10 p-5 sm:p-6 lg:p-8 backdrop-blur-md">
                <div class="font-mono text-[14px] sm:text-[16px] tracking-widest text-ink-400 mb-4">{{ t('auction_page.bigscreen_lot', { id: ld(player.id) }) }}</div>
                <div class="flex justify-center mb-5 sm:mb-6">
                    <img v-if="player.photo_url" :src="player.photo_url" :alt="player.name"
                         class="h-48 w-48 sm:h-64 sm:w-64 lg:h-72 lg:w-72 xl:h-80 xl:w-80 rounded-3xl object-cover border-2 border-white/20 shadow-2xl" />
                    <div v-else class="h-48 w-48 sm:h-64 sm:w-64 lg:h-72 lg:w-72 xl:h-80 xl:w-80 rounded-3xl grid place-items-center font-mono text-[48px] sm:text-[56px] lg:text-[64px] font-bold border-2 border-white/20"
                         style="background:linear-gradient(135deg,rgba(99,102,241,.4),rgba(139,92,246,.4));">
                        {{ player.name.split(' ').map(s => s[0]).slice(0,2).join('') }}
                    </div>
                </div>
                <div class="text-center">
                    <h2 class="font-extrabold tracking-tight leading-tight"
                        style="font-size: clamp(24px, 3.4vw, 38px);">{{ player.name }}</h2>
                    <div v-if="player.position"
                         class="mt-3 inline-block px-5 py-2 rounded-full font-mono text-[16px] sm:text-[18px] tracking-[0.2em] uppercase border border-white/15"
                         style="background:linear-gradient(135deg,rgba(99,102,241,.25),rgba(139,92,246,.25));">
                        {{ player.position }}
                    </div>
                    <div class="font-mono text-[16px] sm:text-[18px] lg:text-[20px] tracking-widest text-ink-400 mt-3">
                        {{ t('auction_page.bigscreen_category_base', { category: player.category?.toUpperCase(), base: fmt(player.base_price) }) }}
                    </div>
                </div>
                <div v-if="player.batting_style || player.bowling_style || player.jersey_no"
                     class="mt-6 pt-5 border-t border-white/10 space-y-2.5 text-[18px] sm:text-[20px] lg:text-[22px]">
                    <div v-if="season?.sport !== 'football' && player.batting_style" class="flex justify-between gap-4"><span class="text-ink-400">{{ t('auction_page.label_batting') }}</span><span class="font-medium text-right">{{ player.batting_style }}</span></div>
                    <div v-if="season?.sport !== 'football' && player.bowling_style" class="flex justify-between gap-4"><span class="text-ink-400">{{ t('auction_page.label_bowling') }}</span><span class="font-medium text-right">{{ player.bowling_style }}</span></div>
                    <div v-if="player.jersey_no" class="flex justify-between gap-4"><span class="text-ink-400">{{ t('auction_page.bigscreen_jersey') }}</span><span class="font-mono font-medium">#{{ ld(player.jersey_no) }}</span></div>
                </div>
            </div>

            <!-- Center -->
            <div class="lg:col-span-5 space-y-6">
                <div class="rounded-3xl p-5 sm:p-8 lg:p-10 text-center"
                     style="background:linear-gradient(135deg,rgba(99,102,241,.15),rgba(139,92,246,.15));border:1px solid rgba(255,255,255,.1);">
                    <div class="font-mono text-[16px] sm:text-[18px] lg:text-[20px] tracking-widest text-ink-400 mb-3">{{ t('auction_page.bigscreen_current_bid') }}</div>
                    <!-- Fluid bid amount: clamp 44 → 100px, prevents wrap on long
                         numbers like ৳1,25,00,000 in Bengali (extra digit-glyph width). -->
                    <div class="font-extrabold tracking-tight leading-none whitespace-nowrap tabular-nums bg-clip-text text-transparent"
                         style="font-size: clamp(44px, 9vw, 100px); background-image:linear-gradient(90deg,#67e8f9,#a78bfa);">
                        {{ fmt(state?.highest_bid || 0) }}
                    </div>
                    <div v-if="state?.highest_bidder" class="mt-4 sm:mt-5 text-[20px] sm:text-[24px] lg:text-[28px]">
                        <i18n-t keypath="auction_page.bigscreen_leading">
                            <template #team><span class="font-bold">{{ state.highest_bidder.name }}</span></template>
                        </i18n-t>
                    </div>
                </div>

                <div class="rounded-3xl bg-white/[0.04] border border-white/10 p-4 sm:p-6 lg:p-8 backdrop-blur-md">
                    <!-- Timer: fluid clamp() so MM : SS never wraps and scales smoothly
                         from phone (≈48px) to 4K big-screen (≈120px). Tighter tracking
                         than -widest because the spaces around `:` already separate digits. -->
                    <div class="text-center font-mono whitespace-nowrap leading-none tracking-tight tabular-nums"
                         style="font-size: clamp(48px, 11vw, 120px);"
                         :class="remainingSec <= 5 && isRunning ? 'text-rose-400 animate-pulse' : 'text-white'">
                        {{ ld(timerDisplay) }}
                    </div>
                </div>
            </div>

            <!-- Right: bid history (team budgets moved to bottom strip) -->
            <div class="lg:col-span-3">
                <div class="rounded-2xl bg-white/[0.04] border border-white/10 p-5 backdrop-blur-md h-full">
                    <div class="font-mono text-[14px] sm:text-[16px] tracking-widest text-ink-400 mb-4">{{ t('auction_page.bigscreen_bid_history') }}</div>
                    <ul v-if="bids.length" class="space-y-2.5 font-mono">
                        <li v-for="b in bids.slice(0, 8)" :key="b.id" class="flex justify-between items-center gap-3 px-3.5 py-2.5 rounded-lg"
                            :class="b.id === bids[0].id ? 'bg-white/15 text-white border border-white/10' : 'text-ink-300'">
                            <span class="text-[16px] sm:text-[18px] lg:text-[20px] truncate">
                                <span class="text-ink-400 mr-1.5">{{ ld(b.placed_at) }}</span>
                                <span class="font-semibold">{{ b.team }}</span>
                            </span>
                            <span class="text-[18px] sm:text-[20px] lg:text-[22px] font-semibold whitespace-nowrap">{{ fmt(b.amount) }}</span>
                        </li>
                    </ul>
                    <div v-else class="text-[16px] sm:text-[18px] text-ink-400">{{ t('auction_page.bigscreen_no_bids') }}</div>
                </div>
            </div>
            </div>

            <!-- ============== Team budgets — full-width bottom strip ============== -->
            <div class="rounded-3xl bg-white/[0.04] border border-white/10 p-5 sm:p-6 backdrop-blur-md">
                <div class="flex items-center justify-between mb-4">
                    <div class="font-mono text-[16px] sm:text-[18px] tracking-widest text-ink-400">{{ t('auction_page.bigscreen_team_budgets') }}</div>
                    <div class="font-mono text-[14px] sm:text-[15px] tracking-wide text-ink-500">{{ t('auction_page.bigscreen_live_n_teams', { count: ld(teams.length) }) }}</div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4">
                    <div v-for="team in teams" :key="team.id"
                         class="rounded-2xl bg-white/[0.06] border border-white/10 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-[22px] sm:text-[24px] lg:text-[26px] font-extrabold tracking-tight">{{ team.short_code || team.name?.slice(0,3).toUpperCase() }}</div>
                            <span class="font-mono text-[13px] sm:text-[14px] text-ink-400 uppercase">{{ team.initial_budget > 0 ? Math.round((team.initial_budget - team.remaining_budget) / team.initial_budget * 100) : 0 }}%</span>
                        </div>
                        <div class="text-[15px] sm:text-[16px] text-ink-300 leading-tight truncate mb-3">{{ team.name }}</div>
                        <div class="text-[24px] sm:text-[26px] lg:text-[28px] font-bold font-mono tracking-tight"
                             :style="{ color: team.remaining_budget < team.initial_budget * 0.2 ? '#fda4af' : '#a7f3d0' }">
                            {{ fmt(team.remaining_budget) }}
                        </div>
                        <div class="font-mono text-[13px] sm:text-[14px] text-ink-500 mt-1">{{ t('auction_page.remaining_label') }}</div>
                        <div class="mt-3 h-2 rounded-full bg-white/10 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700"
                                 style="background:linear-gradient(90deg,#22d3ee,#a78bfa);"
                                 :style="{ width: team.initial_budget > 0 ? ((team.initial_budget - team.remaining_budget) / team.initial_budget * 100) + '%' : '0%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Empty stage -->
        <main v-else class="relative px-10 pb-10 grid place-items-center min-h-[60vh]">
            <div class="text-center">
                <div class="inline-flex items-center gap-2.5 rounded-full px-5 py-2 font-mono text-[16px] sm:text-[18px] tracking-wide bg-white/5 border border-white/10 text-ink-200 mb-6">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    {{ t('auction_page.bigscreen_big_screen', { org: org?.name }) }}
                </div>
                <h1 class="text-[60px] leading-[1.05] font-extrabold tracking-tight">
                    {{ t('auction_page.bigscreen_waiting_heading_a') }}
                    <span class="bg-clip-text text-transparent" style="background-image:linear-gradient(90deg,#67e8f9,#a78bfa);">{{ t('auction_page.bigscreen_waiting_heading_b') }}</span>
                </h1>
                <p class="mt-6 text-[20px] sm:text-[22px] text-ink-300 leading-relaxed">
                    {{ t('auction_page.bigscreen_waiting_body') }}
                </p>
            </div>
        </main>

        <!-- Powered-by watermark — Free plan always shows it; Pro/Enterprise white-label hides it -->
        <div v-if="!isWhiteLabel"
             class="absolute bottom-3 right-4 font-mono text-[13px] tracking-wide text-ink-400/60 pointer-events-none">
            powered by AuctionBall
        </div>

        <!-- ============== UNSOLD stamp — slammed onto the stage and stays
             until the next lot or a reset. Pointer-events-none so the stage
             behind stays interactive (timer/budgets visible underneath). -->
        <Transition name="unsold-stamp">
            <div v-if="showUnsold" class="fixed inset-0 z-40 grid place-items-center pointer-events-none">
                <div class="unsold-mark">
                    <div class="unsold-ring">
                        <div class="unsold-inner">
                            <span class="unsold-text">{{ t('auction_page.bigscreen_unsold') }}</span>
                            <div class="unsold-rule"></div>
                            <span class="unsold-sub">{{ ld(new Date().toLocaleDateString('en-GB')) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- SOLD seal popup — dramatic stamp animation -->
        <Transition name="sold-overlay">
            <div v-if="showSold && soldData" class="fixed inset-0 z-50 grid place-items-center pointer-events-none">
                <!-- Backdrop wash -->
                <div class="absolute inset-0 sold-backdrop"></div>

                <div class="relative grid place-items-center px-4">
                    <!-- The stamp itself -->
                    <div class="sold-seal grid place-items-center">
                        <div class="seal-ring">
                            <div class="seal-inner">
                                <div class="seal-text">{{ t('auction_page.bigscreen_sold') }}</div>
                                <div class="seal-divider"></div>
                                <div class="seal-sub">৳ {{ ld(new Intl.NumberFormat('en-IN').format(soldData.price)) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Player + team caption below the seal -->
                    <div class="sold-caption text-center mt-8 sm:mt-10">
                        <div class="text-[36px] sm:text-[52px] lg:text-[64px] font-extrabold tracking-tight leading-tight">
                            {{ soldData.player }}
                        </div>
                        <div class="mt-2 sm:mt-3 text-[18px] sm:text-[22px] lg:text-[26px] text-ink-200">
                            <i18n-t keypath="auction_page.bigscreen_sold_to">
                                <template #team><span class="font-bold bg-clip-text text-transparent" style="background-image:linear-gradient(90deg,#67e8f9,#a78bfa);">{{ soldData.team }}</span></template>
                            </i18n-t>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* ============== Backdrop wash ============== */
.sold-backdrop {
    background:
        radial-gradient(circle at 50% 50%, rgba(34,197,94,.25), transparent 60%),
        rgba(10, 14, 39, 0.55);
    backdrop-filter: blur(6px);
}

/* ============== The seal — circular wax-stamp look ============== */
.sold-seal {
    width: 280px;
    height: 280px;
    transform-origin: center;
    animation: sealStamp 0.6s cubic-bezier(.2,.9,.3,1.4) forwards;
}
@media (min-width: 640px) {
    .sold-seal { width: 360px; height: 360px; }
}
@media (min-width: 1024px) {
    .sold-seal { width: 440px; height: 440px; }
}

.seal-ring {
    width: 100%;
    height: 100%;
    border-radius: 9999px;
    background:
        radial-gradient(circle at 30% 30%, rgba(248, 113, 113, 0.95), rgba(220, 38, 38, 0.95) 70%);
    border: 6px dashed rgba(255, 255, 255, 0.7);
    box-shadow:
        0 0 0 8px rgba(220, 38, 38, 0.3),
        0 30px 80px -10px rgba(220, 38, 38, 0.55),
        inset 0 0 40px rgba(255, 255, 255, 0.15);
    display: grid;
    place-items: center;
    transform: rotate(0);
}
.seal-inner {
    text-align: center;
    color: white;
    text-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
    padding: 0 1.5rem;
}
.seal-text {
    font-size: clamp(48px, 9vw, 110px);
    font-weight: 900;
    letter-spacing: 0.08em;
    line-height: 1;
}
.seal-divider {
    width: 60%;
    height: 3px;
    background: rgba(255, 255, 255, 0.7);
    margin: 0.5rem auto;
    border-radius: 9999px;
}
.seal-sub {
    font-family: 'JetBrains Mono', monospace;
    font-size: clamp(16px, 2.5vw, 28px);
    font-weight: 700;
    letter-spacing: 0.05em;
}

/* Caption beneath seal — slide up + fade */
.sold-caption {
    animation: captionRise 0.7s 0.25s cubic-bezier(.2,.9,.3,1) backwards;
}

/* ============== Animations ============== */
@keyframes sealStamp {
    0%   { opacity: 0; transform: scale(3.5) rotate(-30deg); filter: blur(4px); }
    50%  { opacity: 1; transform: scale(0.9) rotate(-8deg); filter: blur(0); }
    70%  { transform: scale(1.06) rotate(-13deg); }
    100% { opacity: 1; transform: scale(1) rotate(-10deg); }
}
@keyframes captionRise {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Overlay fade on enter/leave */
.sold-overlay-enter-active { transition: opacity .25s ease; }
.sold-overlay-leave-active { transition: opacity .5s ease; }
.sold-overlay-enter-from,
.sold-overlay-leave-to     { opacity: 0; }


/* ============== UNSOLD stamp — angled, persistent ink stamp ==============
   Bug fixed: previous version used `display: grid; place-items: center` on the
   ring which overlapped all three children in the same grid cell. Now uses a
   flex-column inner wrapper that stacks UNSOLD / rule / date neatly inside
   the circular border. */
.unsold-mark {
    /* Slam entry then settle at -15deg, like a real rubber stamp. */
    animation: unsoldSlam 0.7s cubic-bezier(.2,.9,.3,1.4) forwards;
    transform-origin: center;
    filter: drop-shadow(0 18px 38px rgba(220, 38, 38, .45));
}
.unsold-ring {
    width: 320px;
    height: 320px;
    border-radius: 9999px;
    border: 8px double rgba(220, 38, 38, .92);
    background-color: rgba(255, 255, 255, .04);
    /* Faint ink-grain so the stamp doesn't look flat — two offset dot grids. */
    background-image:
        radial-gradient(rgba(220, 38, 38, .14) 1px, transparent 1px),
        radial-gradient(rgba(220, 38, 38, .10) 1px, transparent 1px);
    background-size: 6px 6px, 11px 11px;
    background-position: 0 0, 3px 5px;
    box-shadow:
        0 0 0 4px rgba(220, 38, 38, .18),
        inset 0 0 32px rgba(220, 38, 38, .25);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(220, 38, 38, .96);
}
@media (min-width: 640px) {
    .unsold-ring { width: 380px; height: 380px; border-width: 10px; }
}
@media (min-width: 1024px) {
    .unsold-ring { width: 460px; height: 460px; border-width: 12px; }
}

/* Inner column — the actual stack of label + rule + date sits centered inside
   the circle with a bit of padding so it doesn't kiss the ring. */
.unsold-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0 1.25rem;
    text-align: center;
}
.unsold-text {
    font-size: clamp(48px, 9vw, 108px);
    font-weight: 900;
    letter-spacing: 0.14em;
    line-height: 1;
    text-shadow:
        0 0 18px rgba(220, 38, 38, .45),
        0 4px 0 rgba(220, 38, 38, .18);
    -webkit-text-stroke: 1px rgba(220, 38, 38, .35);
}
.unsold-rule {
    width: 70%;
    height: 4px;
    background: currentColor;
    border-radius: 9999px;
    opacity: 0.8;
}
.unsold-sub {
    font-family: 'JetBrains Mono', monospace;
    font-size: clamp(12px, 1.6vw, 18px);
    font-weight: 700;
    letter-spacing: 0.18em;
    color: rgba(220, 38, 38, .82);
}

@keyframes unsoldSlam {
    0%   { opacity: 0; transform: scale(3) rotate(-45deg) translateY(-30vh); filter: blur(8px); }
    55%  { opacity: 1; transform: scale(0.92) rotate(-10deg); filter: blur(0); }
    72%  { transform: scale(1.08) rotate(-18deg); }
    100% { opacity: 1; transform: scale(1) rotate(-15deg); }
}

/* Slight wobble on enter; on leave (when the lot changes) just fade away. */
.unsold-stamp-enter-active { transition: none; }
.unsold-stamp-leave-active { transition: opacity .35s ease, transform .35s ease; }
.unsold-stamp-leave-to     { opacity: 0; transform: scale(0.96); }
</style>
