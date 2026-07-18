<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Google rank opportunities
                </p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    GSC query×page analysis — what to improve first
                    <span v-if="data?.refreshed_at"> · synced {{ formatDate(data.refreshed_at) }}</span>
                </p>
            </div>
            <div
                v-if="summaryChips.length"
                class="flex flex-wrap gap-1.5"
            >
                <span
                    v-for="chip in summaryChips"
                    :key="chip.key"
                    class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                    :class="chip.className"
                >
                    {{ chip.label }} {{ chip.count }}
                </span>
            </div>
        </div>

        <div
            v-if="!data?.configured"
            class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
        >
            Set <code class="text-[11px]">SEO_GSC_SITE_URL</code>, then use
            <strong>Connect Search Console</strong> above (token saves automatically), then run
            <strong>Blog learning insights</strong> to sync ranks.
        </div>

        <div
            v-else-if="!data?.table_ready"
            class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
        >
            Run migrations to create <code class="text-[11px]">blog_gsc_query_metrics</code>, then sync learning.
        </div>

        <div
            v-else-if="!(data?.items || []).length"
            class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-slate-600 dark:text-slate-400"
        >
            No urgent rank opportunities yet. Run <strong>Blog learning insights</strong> after GSC credentials are set.
        </div>

        <ul
            v-else
            class="max-h-72 space-y-2 overflow-auto"
        >
            <li
                v-for="(item, idx) in data.items"
                :key="`${item.query}-${item.slug || item.page_url}-${idx}`"
                class="rounded-xl border border-slate-200 px-3 py-2.5 dark:border-slate-600"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ item.query }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                            <span v-if="item.slug">/blog/{{ item.slug }}</span>
                            <span v-else class="break-all">{{ item.page_url }}</span>
                            · pos {{ formatNum(item.position_28d) }}
                            · impr {{ item.impressions_28d }}
                            · clicks {{ item.clicks_28d }}
                            · CTR {{ formatPct(item.ctr_28d) }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="bucketClass(item.bucket)"
                    >
                        {{ item.bucket_label || item.bucket }}
                    </span>
                </div>
                <p
                    v-if="item.improvement_hint"
                    class="mt-1.5 text-xs text-slate-600 dark:text-slate-300"
                >
                    {{ item.improvement_hint }}
                </p>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: { type: Object, default: null },
});

const CHIP_META = {
    striking_distance: {
        label: 'Striking',
        className: 'bg-sky-500/15 text-sky-800 dark:text-sky-200',
    },
    fix_ctr: {
        label: 'Fix CTR',
        className: 'bg-amber-500/15 text-amber-900 dark:text-amber-200',
    },
    defend: {
        label: 'Defend',
        className: 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-200',
    },
    buried: {
        label: 'Buried',
        className: 'bg-slate-500/15 text-slate-700 dark:text-slate-300',
    },
    cannibalized: {
        label: 'Cannibalized',
        className: 'bg-rose-500/15 text-rose-800 dark:text-rose-200',
    },
};

const summaryChips = computed(() => {
    const summary = props.data?.summary || {};
    return Object.entries(CHIP_META)
        .filter(([key]) => Number(summary[key] || 0) > 0)
        .map(([key, meta]) => ({
            key,
            label: meta.label,
            className: meta.className,
            count: Number(summary[key] || 0),
        }));
});

const formatDate = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const formatNum = (value) => {
    if (value == null || Number.isNaN(Number(value))) return '—';
    return Number(value).toFixed(1);
};

const formatPct = (value) => {
    if (value == null || Number.isNaN(Number(value))) return '—';
    return `${(Number(value) * 100).toFixed(1)}%`;
};

const bucketClass = (bucket) => CHIP_META[bucket]?.className || 'bg-slate-500/15 text-slate-700';
</script>
