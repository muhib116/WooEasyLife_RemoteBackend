/** Shared range input classes: filled track comes from `rangeTrackStyle`. */
export const RANGE_SLIDER_CLASS =
    'mt-3 h-3 w-full cursor-pointer appearance-none rounded-full ' +
    '[&::-webkit-slider-runnable-track]:h-3 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-transparent ' +
    '[&::-webkit-slider-thumb]:relative [&::-webkit-slider-thumb]:-mt-1 [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:w-5 ' +
    '[&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-amber-400 ' +
    '[&::-webkit-slider-thumb]:shadow [&::-webkit-slider-thumb]:shadow-black/40 ' +
    '[&::-moz-range-track]:h-3 [&::-moz-range-track]:rounded-full [&::-moz-range-track]:border-0 [&::-moz-range-track]:bg-transparent ' +
    '[&::-moz-range-thumb]:h-5 [&::-moz-range-thumb]:w-5 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:bg-amber-400';

/**
 * Amber fill from min → value; muted track for the remainder.
 * Bind as `:style="rangeTrackStyle(value, min, max)"` on appearance-none range inputs.
 */
export function rangeTrackStyle(value, min, max) {
    const v = Number(value);
    const lo = Number(min);
    const hi = Number(max);
    const span = hi - lo;
    const pct = !Number.isFinite(span) || span <= 0
        ? 0
        : Math.max(0, Math.min(100, ((v - lo) / span) * 100));

    return {
        background: `linear-gradient(to right, #fbbf24 0%, #fbbf24 ${pct}%, rgba(255,255,255,0.10) ${pct}%, rgba(255,255,255,0.10) 100%)`,
    };
}
