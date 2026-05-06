/**
 * Tactile feedback helpers around the Vibration API.
 *
 * Feature-detected — silently no-ops on iOS Safari and desktop browsers
 * that don't expose navigator.vibrate. Patterns are tuned for bid UX:
 *
 *   tap      — single short buzz (button registered the touch)
 *   success  — two-stage tick-tick (your bid was accepted)
 *   alert    — long-short-long (you got outbid, attention please)
 *   sold     — single longer pulse (lot closed)
 */
const supported = () =>
    typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function';

export function useHaptics() {
    const buzz = (pattern) => {
        if (! supported()) return false;
        try { return navigator.vibrate(pattern); } catch { return false; }
    };

    return {
        tap:     () => buzz(15),
        success: () => buzz([20, 30, 50]),
        alert:   () => buzz([100, 60, 100]),
        sold:    () => buzz([180]),
        supported,
    };
}
