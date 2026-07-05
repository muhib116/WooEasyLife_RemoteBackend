<template>
    <div class="flex max-h-[min(36rem,calc(90vh-9rem))] flex-col">
        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto pr-1">
        <div
            :class="[
                'relative overflow-hidden rounded-2xl border-2 p-6 shadow-sm',
                isSpecial
                    ? 'border-amber-300 bg-gradient-to-br from-amber-50 via-white to-orange-50 dark:border-amber-500/40 dark:from-amber-500/10 dark:via-slate-900 dark:to-orange-500/5'
                    : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-slate-900',
            ]"
        >
            <div
                v-if="isSpecial"
                class="absolute right-0 top-0 rounded-bl-xl bg-amber-400 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white"
            >
                Special
            </div>

            <div class="space-y-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                        {{ isLegacy ? "Legacy plan" : "Catalog plan" }}
                    </p>
                    <h3 class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ pkg.title || "Untitled" }}
                    </h3>
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    <template v-if="!isLegacy">
                        <span class="text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                            {{ formatPrice(pkg.package_price) }}
                        </span>
                        <span class="pb-1 text-sm text-gray-500 dark:text-gray-400">
                            / {{ durationLabel }}
                        </span>
                    </template>
                    <template v-else>
                        <span class="text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                            {{ pkg.per_order_rate }}
                        </span>
                        <span class="pb-1 text-sm text-gray-500 dark:text-gray-400">
                            TK per order
                        </span>
                    </template>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        class="rounded-xl border border-gray-100 bg-gray-50/80 px-4 py-3 dark:border-gray-800 dark:bg-slate-950/50"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Duration
                        </p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ durationLabel }}
                        </p>
                        <p
                            v-if="pkg.package_duration === 'free_trial' && pkg.trial_days"
                            class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ pkg.trial_days }} day trial
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-100 bg-gray-50/80 px-4 py-3 dark:border-gray-800 dark:bg-slate-950/50"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Tokens
                        </p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            <template v-if="pkg.order_rate_token != null">
                                <span class="block text-lg font-bold">
                                    {{ Number(pkg.order_rate_token).toLocaleString() }}
                                </span>
                                <span class="mt-0.5 block text-xs font-normal text-gray-500 dark:text-gray-400">
                                    order rate tokens
                                </span>
                            </template>
                            <template v-else-if="isLegacy">
                                Order quota based
                            </template>
                            <template v-else>—</template>
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-100 bg-gray-50/80 px-4 py-3 dark:border-gray-800 dark:bg-slate-950/50"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            App Connect
                        </p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ websiteConnectLabel(pkg.total_website_connect, pkg.app_connect) }}
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-100 bg-gray-50/80 px-4 py-3 dark:border-gray-800 dark:bg-slate-950/50"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Subscriptions
                        </p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            <span class="block text-lg font-bold">
                                {{ pkg.subscriptions_count ?? 0 }}
                            </span>
                            <span class="mt-0.5 block text-xs font-normal text-gray-500 dark:text-gray-400">
                                {{ pkg.active_subscriptions_count ?? 0 }} active
                            </span>
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-gray-100 bg-gray-50/80 px-4 py-3 dark:border-gray-800 dark:bg-slate-950/50"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Features enabled
                        </p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ featureSummary.total }} total
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            power features enabled
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <StatusBadge
                        :label="pkg.is_active ? 'Active' : 'Disabled'"
                        :variant="pkg.is_active ? 'success' : 'neutral'"
                    />
                    <StatusBadge
                        v-if="pkg.deleted_at"
                        label="Deleted"
                        variant="danger"
                    />
                    <StatusBadge
                        v-if="pkg.app_connect"
                        label="App included"
                        variant="info"
                    />
                </div>
            </div>
        </div>

        <div
            v-if="pkg.description"
            class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-slate-900"
        >
            <p class="mb-2 text-sm font-semibold text-gray-800 dark:text-gray-200">
                Description
            </p>
            <div
                class="prose prose-sm max-w-none text-gray-600 dark:prose-invert dark:text-gray-300"
                v-html="pkg.description"
            />
        </div>

        <div
            v-if="!isLegacy && featureSummary.labels.length"
            class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-slate-900"
        >
            <p class="mb-3 shrink-0 text-sm font-semibold text-gray-800 dark:text-gray-200">
                Power Full Features
                <span class="ml-1 font-normal text-gray-500 dark:text-gray-400">
                    ({{ featureSummary.total }})
                </span>
            </p>
            <ul class="max-h-52 space-y-2 overflow-y-auto pr-1">
                <li
                    v-for="label in featureSummary.labels"
                    :key="label"
                    class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300"
                >
                    <i class="pi pi-check-circle mt-0.5 shrink-0 text-emerald-500" />
                    <span>{{ label }}</span>
                </li>
            </ul>
        </div>

        <div
            v-else-if="isLegacy"
            class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-gray-600 dark:bg-slate-900/50 dark:text-gray-400"
        >
            Legacy order-rate plan. Feature catalog is not attached to this package.
        </div>
        </div>

        <div class="mt-4 flex shrink-0 justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
            <Button
                label="Close"
                severity="secondary"
                outlined
                @click="$emit('close')"
            />
            <Button
                v-if="canEdit"
                label="Edit package"
                icon="pi pi-pencil"
                @click="$emit('edit')"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    enabledPowerFeatureLabels,
    isCatalogPackage,
    packageDurationLabel,
    websiteConnectLabel,
} from "@/data/packageCatalogDraft";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import { computed } from "vue";

const props = defineProps<{
    pkg: Record<string, any>;
}>();

defineEmits<{
    close: [];
    edit: [];
}>();

const isLegacy = computed(() => !isCatalogPackage(props.pkg));
const isSpecial = computed(() => Boolean(props.pkg.is_special));
const canEdit = computed(() => isCatalogPackage(props.pkg) && !props.pkg.deleted_at);

const durationLabel = computed(() => {
    if (isLegacy.value) {
        return "No fixed duration";
    }

    return packageDurationLabel(props.pkg.package_duration);
});

const featureSummary = computed(() => {
    const labels = enabledPowerFeatureLabels(props.pkg.features);

    return {
        labels,
        total: labels.length,
    };
});

const formatPrice = (value?: number | string | null) => {
    const amount = Number(value ?? 0);

    return `${amount.toLocaleString("en-BD", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })} TK`;
};
</script>
