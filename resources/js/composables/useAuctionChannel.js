/**
 * Subscribes to the private `auction.{orgId}.{seasonId}` channel
 * and exposes reactive state + a server-authoritative timer + the live
 * Pusher connection state so callers can show an offline indicator.
 *
 * The server emits a single event named `auction.event` whose payload
 * carries `reason`, `state`, `player`, `bids`, `extra`. We mutate local
 * refs accordingly; UIs render straight from these refs.
 */
import { onBeforeUnmount, onMounted, ref, computed } from 'vue';

export function useAuctionChannel(orgId, seasonId, initial = {}) {
    const state  = ref(initial.state ?? null);
    const player = ref(initial.player ?? null);
    const bids   = ref(initial.bids ?? []);
    const lastReason = ref(null);
    const now    = ref(Date.now());

    // Pusher connection state — one of: initialized / connecting / connected /
    // unavailable / failed / disconnected. Plus a raw `navigator.onLine` mirror.
    const connectionState = ref('connecting');
    const isOnline        = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
    const isConnected     = computed(() => isOnline.value && connectionState.value === 'connected');

    let tick;
    let channel;
    let pusherUnbind;
    let onlineHandler;
    let offlineHandler;

    const channelName = `auction.${orgId}.${seasonId}`;

    const remainingMs = computed(() => {
        if (! state.value?.timer_end) return 0;
        const end = new Date(state.value.timer_end).getTime();
        return Math.max(0, end - now.value);
    });
    const remainingSec  = computed(() => Math.ceil(remainingMs.value / 1000));
    const timerDisplay  = computed(() => {
        const s = remainingSec.value;
        const mm = String(Math.floor(s / 60)).padStart(2, '0');
        const ss = String(s % 60).padStart(2, '0');
        // Tight `MM:SS` — letter-spacing handled in CSS, not here. Adding spaces
        // around `:` looked airy on big-screen but pushed digits visually apart.
        return `${mm}:${ss}`;
    });

    onMounted(() => {
        if (! window.Echo) return;

        channel = window.Echo.private(channelName).listen('.auction.event', (e) => {
            lastReason.value = e.reason;
            if (e.state)  state.value  = e.state;
            if (e.player) player.value = e.player;
            if (e.bids)   bids.value   = e.bids;
            // Reset bids list when player changes
            if (e.reason === 'player.changed') bids.value = [];
        });

        // Pusher connection state — mirrored as a ref so templates can react.
        try {
            const conn = window.Echo.connector?.pusher?.connection;
            if (conn) {
                connectionState.value = conn.state ?? 'connecting';
                pusherUnbind = (states) => { connectionState.value = states.current; };
                conn.bind('state_change', pusherUnbind);
            }
        } catch { /* connector layout differs across drivers — ignore */ }

        // Raw browser online/offline events for a coarse fallback indicator.
        onlineHandler  = () => { isOnline.value = true; };
        offlineHandler = () => { isOnline.value = false; };
        window.addEventListener('online',  onlineHandler);
        window.addEventListener('offline', offlineHandler);

        // 100ms tick for smooth countdown
        tick = window.setInterval(() => { now.value = Date.now(); }, 100);
    });

    onBeforeUnmount(() => {
        clearInterval(tick);
        if (window.Echo && channel) window.Echo.leave(channelName);
        try {
            const conn = window.Echo?.connector?.pusher?.connection;
            if (conn && pusherUnbind) conn.unbind('state_change', pusherUnbind);
        } catch {}
        if (onlineHandler)  window.removeEventListener('online',  onlineHandler);
        if (offlineHandler) window.removeEventListener('offline', offlineHandler);
    });

    return {
        state, player, bids, lastReason,
        remainingMs, remainingSec, timerDisplay,
        connectionState, isOnline, isConnected,
    };
}
