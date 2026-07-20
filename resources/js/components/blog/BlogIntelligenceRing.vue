<template>
    <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-start">
        <div
            class="relative shrink-0"
            :style="{ width: `${size}px`, height: `${size}px` }"
            role="img"
            :aria-label="`Blog intelligence ${scorePercent}% — ${data?.label || ''}`"
        >
            <svg
                :width="size"
                :height="size"
                :viewBox="`0 0 ${size} ${size}`"
                class="-rotate-90"
            >
                <circle
                    :cx="center"
                    :cy="center"
                    :r="radius"
                    fill="none"
                    class="stroke-slate-200 dark:stroke-slate-700"
                    :stroke-width="stroke"
                />
                <circle
                    :cx="center"
                    :cy="center"
                    :r="radius"
                    fill="none"
                    :class="ringClass"
                    :stroke-width="stroke"
                    stroke-linecap="round"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="dashOffset"
                    class="transition-[stroke-dashoffset] duration-700 ease-out"
                />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center rotate-0">
                <span
                    class="text-2xl font-bold tabular-nums leading-none"
                    :class="textClass"
                >
                    {{ scorePercent }}
                </span>
                <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    / 100
                </span>
            </div>
        </div>

        <div class="min-w-0 flex-1 space-y-2 text-center sm:text-left">
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    System intelligence
                </p>
                <p
                    class="mt-0.5 text-xs font-medium"
                    :class="textClass"
                >
                    {{ data?.label || '—' }}
                    <span
                        v-if="data?.label_bn"
                        class="font-normal text-slate-500 dark:text-slate-400"
                    >
                        · {{ data.label_bn }}
                    </span>
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    How ready the blog AI loop is (GSC + learning + competitors + analytics).
                </p>
            </div>

            <ul
                v-if="(data?.dimensions || []).length"
                class="space-y-1.5"
            >
                <li
                    v-for="dim in data.dimensions"
                    :key="dim.key"
                    class="flex items-center justify-between gap-2 text-xs"
                >
                    <span class="text-slate-600 dark:text-slate-300">{{ dim.label }}</span>
                    <span class="tabular-nums font-medium text-slate-800 dark:text-slate-100">
                        {{ dim.score }}/{{ dim.max }}
                    </span>
                </li>
            </ul>

            <ul
                v-if="(data?.next_steps || []).length && scorePercent < 100"
                class="space-y-1 border-t border-slate-200 pt-2 dark:border-slate-600"
            >
                <li
                    v-for="(step, idx) in data.next_steps.slice(0, 3)"
                    :key="idx"
                    class="text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                >
                    → {{ step }}
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: { type: Object, default: null },
    size: { type: Number, default: 112 },
    stroke: { type: Number, default: 8 },
});

const scorePercent = computed(() => {
    const n = Number(props.data?.score ?? 0);
    if (Number.isNaN(n)) return 0;
    return Math.max(0, Math.min(100, Math.round(n)));
});

const center = computed(() => props.size / 2);
const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const dashOffset = computed(
    () => circumference.value - (scorePercent.value / 100) * circumference.value,
);

const color = computed(() => props.data?.color || 'slate');

const ringClass = computed(() => {
    const map = {
        emerald: 'stroke-emerald-500',
        sky: 'stroke-sky-500',
        amber: 'stroke-amber-500',
        orange: 'stroke-orange-500',
        rose: 'stroke-rose-500',
        slate: 'stroke-slate-400',
    };
    return map[color.value] || map.slate;
});

const textClass = computed(() => {
    const map = {
        emerald: 'text-emerald-600 dark:text-emerald-400',
        sky: 'text-sky-600 dark:text-sky-400',
        amber: 'text-amber-700 dark:text-amber-300',
        orange: 'text-orange-600 dark:text-orange-400',
        rose: 'text-rose-600 dark:text-rose-400',
        slate: 'text-slate-700 dark:text-slate-200',
    };
    return map[color.value] || map.slate;
});
</script>
