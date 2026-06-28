<template>
    <MerchantPortalLayout title="Dashboard">
        <div class="space-y-6">
            <PageHeader
                title="Dashboard"
                :description="welcomeText"
                icon="PhChartBar"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Websites"
                    :value="summary.websites"
                    icon="PhGlobe"
                />
                <StatCard
                    title="Remaining Orders"
                    :value="summary.remaining_orders"
                    icon="PhPackage"
                />
                <StatCard
                    title="Active Plans"
                    :value="summary.active_plans"
                    icon="PhCheckCircle"
                />
                <StatCard
                    title="Pending Payments"
                    :value="summary.pending_payments"
                    icon="PhHourglass"
                />
            </div>

            <BillingAlertsPanel :alerts="alerts" />

            <PageCard title="Websites" :description="`${websites.length} shown`">
                <div v-if="!websites.length" class="text-sm text-gray-500 dark:text-gray-400">
                    No websites are assigned to your account yet.
                </div>
                <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div
                        v-for="website in websites"
                        :key="website.domain"
                        class="rounded-xl border border-gray-100 p-4 dark:border-gray-800"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ website.domain }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ website.subscription?.remaining_order ?? 0 }} orders left
                                </p>
                            </div>
                            <StatusBadge
                                :label="healthLabel(website.health?.status)"
                                :variant="healthVariant(website.health?.status)"
                            />
                        </div>
                    </div>
                </div>
            </PageCard>
        </div>
    </MerchantPortalLayout>
</template>

<script setup lang="ts">
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/components/StatCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import BillingAlertsPanel from "@/Pages/Portal/fragments/BillingAlertsPanel.vue";
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps<{
    summary: {
        websites: number;
        remaining_orders: number;
        active_plans: number;
        pending_payments: number;
        billing_alerts?: number;
    };
    websites: any[];
    alerts: Array<{
        type: string;
        severity: string;
        message: string;
        domain?: string;
    }>;
}>();

const page = usePage();
const portal = computed(() => (page.props.auth as any)?.portal);

const welcomeText = computed(() => {
    if (portal.value?.is_staff) {
        return `Signed in as ${portal.value.employee?.role || "team member"}`;
    }

    return "Overview of your websites, plans, and billing";
});

const healthLabel = (status?: string) => {
    if (status === "connected") return "Connected";
    if (status === "configured") return "Configured";
    if (status === "disabled") return "Disabled";
    return "Incomplete";
};

const healthVariant = (status?: string) => {
    if (status === "connected") return "success";
    if (status === "configured") return "info";
    if (status === "disabled") return "danger";
    return "warning";
};
</script>
