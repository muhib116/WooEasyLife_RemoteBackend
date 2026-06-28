<template>
    <div
        v-if="plan"
        class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-slate-900/50"
    >
        <div
            class="border-b border-gray-100 bg-slate-50 px-4 py-3 dark:border-gray-800 dark:bg-slate-800/50"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">
                        {{ plan.title }}
                    </p>
                    <p
                        v-if="isCatalog"
                        class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Fixed-price subscription
                    </p>
                    <p v-else class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Legacy pay-per-order plan
                    </p>
                </div>
                <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="
                        isFree
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                            : 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'
                    "
                >
                    {{ priceLabel }}
                </span>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-3">
            <div
                v-if="isCatalog && plan.package_duration"
                class="bg-white px-4 py-3 dark:bg-slate-900/50"
            >
                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">
                    Duration
                </dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ packageDurationLabel(plan.package_duration) }}
                </dd>
            </div>
            <div
                v-if="isCatalog"
                class="bg-white px-4 py-3 dark:bg-slate-900/50"
            >
                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">
                    Order tokens
                </dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ (plan.order_rate_token ?? 0).toLocaleString() }}
                </dd>
            </div>
            <div
                v-if="!isCatalog"
                class="bg-white px-4 py-3 dark:bg-slate-900/50"
            >
                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">
                    Rate
                </dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ plan.per_order_rate ?? 0 }} TK / order
                </dd>
            </div>
            <div
                v-if="!isCatalog && orderLimit"
                class="bg-white px-4 py-3 dark:bg-slate-900/50"
            >
                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">
                    Order quota
                </dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ orderLimit.toLocaleString() }} orders
                </dd>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-slate-900/50">
                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">
                    Total
                </dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ totalLabel }}
                </dd>
            </div>
        </dl>

        <p
            v-if="isFree"
            class="border-t border-gray-100 px-4 py-2.5 text-xs text-emerald-700 dark:border-gray-800 dark:text-emerald-300"
        >
            No payment required — assign this trial plan directly.
        </p>
    </div>
</template>

<script setup lang="ts">
import {
    isCatalogPackage,
    packageDurationLabel,
} from "@/data/packageCatalogDraft";
import { computed } from "vue";

const props = defineProps<{
    plan: {
        title: string;
        plan_type?: string | null;
        package_duration?: string | null;
        package_price?: number | null;
        order_rate_token?: number | null;
        per_order_rate?: number | null;
    } | null;
    orderLimit?: number | null;
    totalCost?: number | null;
}>();

const isCatalog = computed(() =>
    props.plan ? isCatalogPackage(props.plan) : false,
);

const isFree = computed(() => Number(props.totalCost ?? props.plan?.package_price ?? 0) === 0);

const priceLabel = computed(() => {
    if (!props.plan) {
        return "—";
    }

    if (isCatalog.value) {
        const amount = Number(props.plan.package_price ?? 0);
        return amount === 0 ? "Free" : `${amount.toLocaleString()} TK`;
    }

    return `${props.plan.per_order_rate ?? 0} TK/order`;
});

const totalLabel = computed(() => {
    const amount = Number(props.totalCost ?? 0);
    return amount === 0 ? "Free" : `${amount.toLocaleString()} TK`;
});
</script>
