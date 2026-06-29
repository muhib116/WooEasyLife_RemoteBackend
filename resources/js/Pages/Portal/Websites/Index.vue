<template>
    <MerchantPortalLayout title="Websites">
        <div class="space-y-5">
            <PageHeader
                title="Websites"
                description="Your connected stores, plans, and license health"
                icon="PhGlobe"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <WebsiteHealthLegend />

            <EmptyState
                v-if="!websites.length"
                title="No websites available"
                description="Your account does not have any websites in scope."
                icon="PhGlobe"
            />

            <div v-else class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <PageCard
                    v-for="website in websites"
                    :key="website.domain"
                    no-padding
                >
                    <div class="border-b border-gray-100 p-5 dark:border-gray-700/80">
                        <div class="flex flex-wrap items-center gap-2">
                            <Icon name="PhGlobe" class="text-primary-500" />
                            <h3 class="font-semibold text-gray-900 dark:text-white">
                                {{ website.domain }}
                            </h3>
                            <StatusBadge
                                :label="healthLabel(website.health?.status)"
                                :variant="healthVariant(website.health?.status)"
                                format="none"
                            />
                            <WebsiteEmployeeBadge :employees="website.employees ?? []" />
                        </div>
                        <ul
                            v-if="website.health?.issues?.length"
                            class="mt-3 space-y-1 text-xs text-amber-700 dark:text-amber-300"
                        >
                            <li v-for="issue in website.health.issues" :key="issue">
                                {{ issue }}
                            </li>
                        </ul>
                    </div>
                    <div class="space-y-3 p-5 text-sm text-gray-600 dark:text-gray-300">
                        <template v-if="website.subscription">
                            <p>
                                Plan:
                                <strong>{{ website.subscription.title }}</strong>
                            </p>
                            <p>
                                Remaining orders:
                                <strong>{{ website.subscription.remaining_order }}</strong>
                            </p>
                            <p v-if="website.subscription.expires_at">
                                Expires:
                                <strong>{{ website.subscription.expires_at }}</strong>
                            </p>
                        </template>
                        <p v-else>No active plan for this domain.</p>
                        <p>
                            License keys:
                            <strong>{{ website.licenses?.length || 0 }}</strong>
                        </p>
                        <div v-if="canViewBilling" class="pt-2">
                            <Link :href="route('portal.billing')">
                                <Button
                                    label="Renew in Billing"
                                    icon="pi pi-credit-card"
                                    size="small"
                                    severity="warning"
                                    outlined
                                    as="span"
                                />
                            </Link>
                        </div>
                    </div>
                </PageCard>
            </div>
        </div>
    </MerchantPortalLayout>
</template>

<script setup lang="ts">
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import WebsiteHealthLegend from "@/components/WebsiteHealthLegend.vue";
import WebsiteEmployeeBadge from "@/Pages/Users/fragments/WebsiteEmployeeBadge.vue";
import { usePermissions } from "@/composables/usePermissions";
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";

defineProps<{
    websites: any[];
}>();

const { can } = usePermissions();
const canViewBilling = computed(() => can("billing.view"));

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
