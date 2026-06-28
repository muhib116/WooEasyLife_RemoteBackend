<template>
    <PageCard
        v-if="alerts.length"
        title="Billing Alerts"
        :description="`${alerts.length} active alert${alerts.length === 1 ? '' : 's'}`"
    >
        <div class="space-y-3">
            <div
                v-for="(alert, index) in alerts"
                :key="`${alert.domain || 'all'}-${alert.type}-${index}`"
                class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
                :class="alertClass(alert.severity)"
            >
                <Icon :name="alertIcon(alert.severity)" class="mt-0.5 shrink-0 text-lg" />
                <div class="min-w-0 flex-1">
                    <p v-if="alert.domain" class="mb-1 text-xs font-semibold uppercase tracking-wide opacity-80">
                        {{ alert.domain }}
                    </p>
                    <p>{{ alert.message }}</p>
                </div>
            </div>
        </div>
    </PageCard>
</template>

<script setup lang="ts">
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import { Icon } from "@/plugins";
import type { IconName } from "@/types";

defineProps<{
    alerts: Array<{
        type: string;
        severity: string;
        message: string;
        domain?: string;
    }>;
}>();

const alertClass = (severity: string) => {
    if (severity === "danger") {
        return "border-red-200 bg-red-50 text-red-900 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-100";
    }

    if (severity === "warning") {
        return "border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100";
    }

    return "border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100";
};

const alertIcon = (severity: string): IconName => {
    if (severity === "danger") return "PhWarningCircle";
    if (severity === "warning") return "PhWarning";
    return "PhInfo";
};
</script>
