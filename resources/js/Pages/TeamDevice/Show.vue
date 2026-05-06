<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuctionChannel } from '@/composables/useAuctionChannel';
import { useFmt } from '@/composables/useFmt';
import { useHaptics } from '@/composables/useHaptics';
import { useAlert } from '@/composables/useConfirm';
import LanguageToggle from '@/Components/LanguageToggle.vue';

const alertDialog = useAlert();

const props = defineProps({
    org:        Object,
    season:     Object,
    team:       Object,
    state:      Object,
    increments: { type: Array, default: () => [5000, 10000, 25000, 50000] },
    signed_in:  { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const _fmt = useFmt();
const fmt  = _fmt.money;
const ld   = _fmt.localizeDigits;
const currency = _fmt.currency;
const rate     = _fmt.rate;

// Per-currency, no conversion. BDT-display uses bid_increment as-is; USD-display
// uses bid_increment_usd (in USD units), translated to BDT at runtime so the
// internal bid math stays in canonical BDT.
const increment = computed(() => {
    if (currency.value === 'USD') {
        return (props.season?.bid_increment_usd ?? 10) * Math.max(1, rate.value);
    }
    return props.season?.bid_increment ?? 1000;
});
const haptic = useHaptics();

const { state, player, bids, lastReason, remainingSec, timerDisplay,
        connectionState, isOnline, isConnected }
    = useAuctionChannel(props.season?.org_id, props.season?.id, {
        state:  props.state,
        player: props.state?.player,
        bids:   [],
    });

const teamRemaining = ref(props.team.remaining_budget);

// Server-authoritative minimum: the smallest valid amount the team can submit.
// First bid on a lot only needs to match the base price. Subsequent bids must
// beat the current highest by at least `increment`.
const minBid = computed(() => {
    if (! player.value) return 0;
    const highest = state.value?.highest_bid || 0;
    const floor   = Math.max(highest, player.value.base_price);
    return highest > 0 ? floor + increment.value : floor;
});
const isRunning  = computed(() => state.value?.status === 'running');
const iAmLeading = computed(() => state.value?.highest_bidder?.id === props.team.id);
const authUser   = computed(() => page.props.auth?.user);

/* ----- Haptic feedback ----- */

const placeBid = (amount) => {
    if (amount > teamRemaining.value) return;
    haptic.tap();
    if (props.signed_in) {
        router.post(route('bid.place'), { amount }, { preserveScroll: true, preserveState: true });
    } else {
        router.post(route('team-device.bid', props.team.device_token), { amount },
            { preserveScroll: true, preserveState: true });
    }
};

// The amount currently shown in the field. Auto-tracks (current bid + increment)
// whenever the server pushes a new highest_bid, but the user can bump up/down
// with the +/- buttons. Submitting fires `placeBid(bidAmount)`.
const bidAmount = ref(0);
const syncFromMin = () => { bidAmount.value = minBid.value; };

// Re-seed whenever the lot, the highest bid, or the season's increment changes.
watch([() => state.value?.highest_bid, () => player.value?.id, increment], syncFromMin, { immediate: true });

const bumpUp   = () => { bidAmount.value = Math.min(bidAmount.value + increment.value, teamRemaining.value); haptic.tap(); };
const bumpDown = () => { bidAmount.value = Math.max(bidAmount.value - increment.value, minBid.value); haptic.tap(); };

const submitBid = () => {
    if (! Number.isFinite(bidAmount.value) || bidAmount.value < minBid.value) {
        haptic.alert();
        alertDialog({
            title: t('team_device.min_bid_placeholder', { amount: fmt(minBid.value) }),
            variant: 'warning',
        });
        return;
    }
    if (bidAmount.value > teamRemaining.value) {
        haptic.alert();
        alertDialog({
            title: 'Over budget',
            description: 'This bid exceeds your remaining budget. Lower the amount and try again.',
            variant: 'danger',
        });
        return;
    }
    placeBid(bidAmount.value);
};

// 2. Success / outbid pulses — fire when the leader changes, by watching state.
const previousLeaderId = ref(state.value?.highest_bidder?.id ?? null);
watch(() => state.value?.highest_bidder?.id, (newId) => {
    const prev = previousLeaderId.value;
    if (newId === props.team.id && prev !== props.team.id) haptic.success();    // we just took the lead
    else if (prev === props.team.id && newId && newId !== props.team.id) haptic.alert();   // someone outbid us
    previousLeaderId.value = newId;
});

// 3. Sold pulse — when the auction closes on this player.
watch(lastReason, (reason) => {
    if (reason === 'auction.sold') haptic.sold();
});

const logout = () => router.post(route('logout'));

const statusKey = computed(() => ({
    idle:    'auction.status_idle',
    running: 'auction.status_running',
    paused:  'auction.status_paused',
    sold:    'auction.status_sold',
    unsold:  'auction.status_unsold',
}[state.value?.status] || 'auction.status_idle'));

/* ----- Connection indicator ----- */

const conn = computed(() => {
    if (! isOnline.value)               return { color: 'rose',    label: t('team_device.offline') };
    if (connectionState.value === 'connected')          return { color: 'emerald', label: t('team_device.connected') };
    if (['connecting', 'initialized'].includes(connectionState.value)) return { color: 'amber', label: t('team_device.connecting') };
    if (['unavailable', 'disconnected'].includes(connectionState.value)) return { color: 'rose', label: t('team_device.offline') };
    if (connectionState.value === 'failed')             return { color: 'rose',    label: t('team_device.connection_failed') };
    return { color: 'amber', label: t('team_device.connecting') };
});
const connDotCls  = computed(() => ({
    emerald: 'bg-emerald-500',
    amber:   'bg-amber-500 animate-pulse',
    rose:    'bg-rose-500',
}[conn.value.color]));
const connTextCls = computed(() => ({
    emerald: 'text-emerald-700',
    amber:   'text-amber-700',
    rose:    'text-rose-600',
}[conn.value.color]));

/* ----- Bid history rendering ----- */

const recentBids = computed(() => bids.value.slice(0, 5));
const bidIsMine  = (b) => b.team === props.team.short_code;
</script>

<template>
    <Head :title="`${team.name} · ${t('team_device.bid')}`" />
    <div class="min-h-[100dvh] page-bg flex flex-col">

        <!-- Sticky header — team identity + remaining budget always visible -->
        <header class="sticky top-0 z-20 px-4 py-3.5 flex items-center justify-between bg-white/70 backdrop-blur-md border-b border-ink-200/60">
            <div class="flex items-center gap-3 min-w-0">
                <div class="h-11 w-11 rounded-xl bg-gradient-to-br from-cyan-200 to-violet-300 grid place-items-center font-mono text-[14px] font-bold text-indigo-700 shrink-0">
                    {{ team.short_code || team.name.slice(0, 3).toUpperCase() }}
                </div>
                <div class="min-w-0">
                    <div class="text-[17px] font-bold tracking-tight truncate leading-tight">{{ team.name }}</div>
                    <div class="font-mono text-[12px] text-ink-500 truncate">{{ org?.name }} · {{ season?.name }}</div>
                </div>
            </div>
            <div class="text-right shrink-0">
                <div class="font-mono text-[11px] tracking-widest text-ink-500">{{ t('team_device.remaining') }}</div>
                <div class="text-[19px] font-extrabold tracking-tight text-grad leading-none mt-0.5">{{ fmt(teamRemaining) }}</div>
            </div>
        </header>

        <!-- Auth bar — only for logged-in mode -->
        <div v-if="signed_in" class="px-4 py-2.5 flex items-center justify-between text-[13px] bg-white/40 border-b border-ink-100">
            <div class="flex items-center gap-2 min-w-0">
                <div class="h-7 w-7 rounded-full bg-gradient-to-br from-cyan-200 to-indigo-300 grid place-items-center font-mono text-[11px] font-bold text-indigo-700 shrink-0">
                    {{ authUser?.name?.[0]?.toUpperCase() }}
                </div>
                <span class="truncate text-ink-700">{{ authUser?.name }}</span>
                <!-- Connection dot — also shown for token-based mode in the next row -->
                <span class="inline-flex items-center gap-1.5 ml-2 font-mono text-[12px]" :class="connTextCls">
                    <span class="h-2 w-2 rounded-full" :class="connDotCls"></span>
                    {{ conn.label }}
                </span>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <LanguageToggle />
                <button @click="logout" class="text-[13px] text-rose-500 hover:text-rose-700 font-medium">
                    {{ t('nav.log_out') }}
                </button>
            </div>
        </div>

        <main class="flex-1 p-3 max-w-md mx-auto w-full space-y-3">

            <!-- Status pill + connection (token-based mode shows it here since there's no auth bar) -->
            <div class="flex items-center justify-center gap-3">
                <div class="flex items-center justify-center gap-2 py-2 px-4 rounded-full font-mono text-[13px] tracking-wide flex-1"
                     :class="isRunning ? 'bg-emerald-50 text-emerald-700 border border-emerald-100'
                           : state?.status === 'paused' ? 'bg-amber-50 text-amber-700 border border-amber-100'
                           : 'bg-ink-100 text-ink-600 border border-ink-200'">
                    <span class="h-2 w-2 rounded-full" :class="isRunning ? 'bg-emerald-500 animate-pulse' : 'bg-ink-400'"></span>
                    {{ t(statusKey) }}
                </div>
                <div v-if="!signed_in"
                     class="inline-flex items-center gap-1.5 py-2 px-3 rounded-full font-mono text-[12px] bg-white/60 border border-ink-200/60"
                     :class="connTextCls">
                    <span class="h-2 w-2 rounded-full" :class="connDotCls"></span>
                    {{ conn.label }}
                </div>
            </div>

            <!-- Player card -->
            <div v-if="player" class="glass-strong rounded-2xl p-5">
                <div class="flex items-center gap-3.5">
                    <img v-if="player.photo_url" :src="player.photo_url" :alt="player.name"
                         class="h-24 w-24 rounded-2xl object-cover border border-ink-200 shrink-0" />
                    <div v-else class="h-24 w-24 rounded-2xl grid place-items-center font-mono text-[22px] font-bold bg-gradient-to-br from-cyan-200 to-violet-300 text-indigo-700 shrink-0">
                        {{ player.name.split(' ').map(s => s[0]).slice(0,2).join('') }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-mono text-[12px] tracking-widest text-ink-500">{{ t('team_device.current_lot') }}</div>
                        <div class="text-[22px] font-bold tracking-tight leading-tight truncate mt-0.5">{{ player.name }}</div>
                        <div class="text-[14px] text-ink-500 truncate mt-1">
                            {{ player.category }} · {{ t('forms.players.base_price').split(' (')[0] }} {{ fmt(player.base_price) }}
                        </div>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-white/80 border border-white/80 p-4 text-center">
                        <div class="font-mono text-[12px] tracking-widest text-ink-500 mb-1.5">{{ t('team_device.current_bid') }}</div>
                        <div class="text-[28px] font-extrabold tracking-tight text-grad leading-none">{{ fmt(state?.highest_bid || 0) }}</div>
                        <div v-if="iAmLeading" class="mt-2 font-mono text-[12px] text-emerald-600 font-bold">{{ t('team_device.youre_leading') }}</div>
                        <div v-else-if="state?.highest_bidder" class="mt-2 font-mono text-[12px] text-ink-500">
                            {{ state.highest_bidder.short || state.highest_bidder.name }}
                        </div>
                        <div v-else class="mt-2 font-mono text-[12px] text-ink-400">—</div>
                    </div>
                    <div class="rounded-xl bg-white/80 border border-white/80 p-4 text-center">
                        <div class="font-mono text-[12px] tracking-widest text-ink-500 mb-1.5">{{ t('team_device.timer') }}</div>
                        <div class="font-mono text-[34px] tracking-widest leading-none mt-0.5"
                             :class="remainingSec <= 5 && isRunning ? 'text-rose-500' : 'text-ink-900'">
                            {{ ld(timerDisplay) }}
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="glass rounded-2xl p-8 text-center">
                <p class="text-[16px] text-ink-500">{{ t('team_device.waiting_for_player') }}</p>
            </div>

            <!-- Bid panel -->
            <div v-if="player" class="glass-strong rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3.5">
                    <div class="font-mono text-[12px] tracking-widest text-ink-500">{{ t('team_device.bid') }}</div>
                    <div class="font-mono text-[12px] tracking-wide text-ink-500">
                        + / − step: <strong class="text-ink-700">{{ fmt(increment) }}</strong>
                    </div>
                </div>

                <!-- Stepper: minus | amount | plus -->
                <div class="flex items-stretch gap-2.5 mb-4">
                    <button @click="bumpDown"
                            :disabled="!isRunning || iAmLeading || bidAmount - increment < minBid"
                            class="grid place-items-center w-16 rounded-xl bg-white/85 border border-ink-200/70 text-[28px] font-bold text-ink-700 active:scale-95 transition shadow-sm"
                            :class="{ 'opacity-40 pointer-events-none': !isRunning || iAmLeading || bidAmount - increment < minBid }">
                        −
                    </button>
                    <div class="flex-1 rounded-xl bg-white border border-ink-200/70 px-3 py-2.5 text-center shadow-sm">
                        <div class="font-mono text-[11.5px] tracking-widest text-ink-500">YOUR BID</div>
                        <div class="text-[32px] font-extrabold tracking-tight text-grad leading-none mt-1 tabular-nums whitespace-nowrap">
                            {{ fmt(bidAmount) }}
                        </div>
                    </div>
                    <button @click="bumpUp"
                            :disabled="!isRunning || iAmLeading || bidAmount + increment > teamRemaining"
                            class="grid place-items-center w-16 rounded-xl bg-white/85 border border-ink-200/70 text-[28px] font-bold text-ink-700 active:scale-95 transition shadow-sm"
                            :class="{ 'opacity-40 pointer-events-none': !isRunning || iAmLeading || bidAmount + increment > teamRemaining }">
                        +
                    </button>
                </div>

                <!-- Single big BID button -->
                <button @click="submitBid"
                        :disabled="!isRunning || iAmLeading || bidAmount < minBid || bidAmount > teamRemaining"
                        class="btn-primary w-full py-5 text-[19px] font-bold tracking-wide active:scale-[0.98]"
                        :class="{ 'opacity-40 pointer-events-none': !isRunning || iAmLeading || bidAmount < minBid || bidAmount > teamRemaining }">
                    {{ t('team_device.bid_button') }} · {{ fmt(bidAmount) }}
                </button>

                <p v-if="iAmLeading" class="mt-3.5 text-center text-[14px] font-mono text-emerald-600">
                    {{ t('team_device.leading_message') }}
                </p>
                <p v-else-if="!isRunning" class="mt-3.5 text-center text-[14px] font-mono text-ink-500">
                    {{ t('team_device.not_running_message') }}
                </p>
                <p v-else-if="bidAmount > teamRemaining" class="mt-3.5 text-center text-[14px] font-mono text-rose-500">
                    Over budget
                </p>
            </div>

            <!-- Last 5 bids — appears once any bid lands on this lot -->
            <div v-if="player && recentBids.length" class="glass rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-mono text-[12px] tracking-widest text-ink-500">{{ t('team_device.recent_bids') }}</div>
                    <span class="font-mono text-[12px] text-ink-400">{{ ld(recentBids.length) }}/5</span>
                </div>
                <ul class="space-y-1.5">
                    <li v-for="(b, i) in recentBids" :key="b.id"
                        class="flex items-center gap-2 px-2.5 py-2 rounded-lg text-[14.5px] font-mono"
                        :class="bidIsMine(b)
                            ? 'bg-emerald-50 text-emerald-800 border border-emerald-100'
                            : (i === 0 ? 'bg-blue-50/70 text-ink-900 border border-blue-100' : 'text-ink-700')">
                        <span class="text-[12px] text-ink-400 w-16 shrink-0">{{ ld(b.placed_at) }}</span>
                        <span class="flex-1 truncate">
                            {{ b.team }}
                            <span v-if="bidIsMine(b)" class="ml-1 text-[11px] uppercase tracking-widest text-emerald-600">
                                {{ t('team_device.my_bid') }}
                            </span>
                        </span>
                        <span class="font-semibold">{{ fmt(b.amount) }}</span>
                    </li>
                </ul>
            </div>

            <!-- Flash error from server-side bid rejection -->
            <div v-if="page.props.flash?.error"
                 class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-[14px] text-rose-700 text-center">
                {{ page.props.flash.error }}
            </div>
        </main>
    </div>
</template>
