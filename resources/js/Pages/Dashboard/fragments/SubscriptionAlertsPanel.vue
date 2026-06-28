<template>
    <PageCard no-padding>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-500/15"
                >
                    <Icon
                        name="PhBellRinging"
                        class="text-lg text-amber-600 dark:text-amber-400"
                    />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">
                        {{ data?.title || "Subscription Alerts" }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Quota, expiry, and payment lifecycle issues
                    </p>
                </div>
            </div>
        </template>

        <template #actions>
            <Link
                v-if="data?.link"
                :href="data.link"
                class="text-theme-sm inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3.5 py-2 font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-slate-800 dark:text-gray-200 dark:hover:bg-slate-700"
            >
                {{ data?.link_text || "View All Alerts" }}
                <Icon name="PhArrowRight" />
            </Link>
        </template>

        <div class="space-y-5 p-5 md:p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Widget
                    title="Critical"
                    :value="data?.summary?.danger ?? 0"
                    icon="PhWarningCircle"
                    right-text="Needs attention"
                />
                <Widget
                    title="Warnings"
                    :value="data?.summary?.warning ?? 0"
                    icon="PhWarning"
                    right-text="Expiring or low quota"
                />
                <Widget
                    title="Info"
                    :value="data?.summary?.info ?? 0"
                    icon="PhInfo"
                    right-text="Pending or informational"
                />
                <Widget
                    title="Total Alerts"
                    :value="data?.summary?.total ?? 0"
                    icon="PhBell"
                    right-text="Across all merchants"
                />
            </div>

            <div v-if="recent.length" class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Recent alerts
                </p>
                <div class="divide-y divide-gray-100 rounded-xl border border-gray-100 dark:divide-gray-800 dark:border-gray-800">
                    <div
                        v-for="(alert, index) in recent"
                        :key="`${alert.user_id}-${alert.type}-${index}`"
                        class="flex items-start gap-3 px-4 py-3"
                    >
                        <StatusBadge
                            :label="alert.severity"
                            :variant="severityVariant(alert.severity)"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ alert.user_name }}
                                <span class="font-normal text-gray-500 dark:text-gray-400">
                                    · {{ alert.domain }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ alert.message }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                No subscription alerts right now.
            </p>
        </div>
    </PageCard>
</template>

<script setup lang="ts">
import PageCard from "@/components/PageCard.vue";
import Widget from "@/components/Widget.vue";
import StatusBadge from "@/components/StatusBadge.vue";
import Icon from "@/components/Icon.vue";
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";

type AlertSummary = {
    total: number;
    danger: number;
    warning: number;
    info: number;
};

type AlertRow = {
    type: string;
    severity: string;
    message: string;
    user_id: number;
    user_name: string;
    domain: string;
};

type PanelData = {
    title?: string;
    link?: string;
    link_text?: string;
    summary?: AlertSummary;
    recent?: AlertRow[];
};

const props = defineProps<{
    data?: PanelData;
}>();

const recent = computed(() => props.data?.recent ?? []);

const severityVariant = (severity: string) => {
    if (severity === "danger") {
        return "danger";
    }

    if (severity === "warning") {
        return "warning";
    }

    return "info";
};
</script>
