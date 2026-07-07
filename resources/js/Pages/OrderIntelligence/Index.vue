<template>
    <AuthenticatedLayout title="Order Intelligence">
        <div class="space-y-5">
            <PageHeader
                title="Order Intelligence"
                description="Global customer profiles, order lifecycle tracking, and platform analytics"
                icon="PhBrain"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <Button
                        label="Rebuild Search Index"
                        icon="pi pi-refresh"
                        size="small"
                        severity="secondary"
                        outlined
                        :loading="reindexing"
                        @click="handleReindex"
                    />
                </template>
            </PageHeader>

            <IntelSubNav />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Platform Customers"
                    :value="summary.total_customers"
                    icon="PhUsers"
                    subtitle="Unique phones tracked"
                />
                <StatCard
                    title="Platform Orders"
                    :value="summary.total_orders"
                    icon="PhPackage"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                    subtitle="Across all merchants"
                />
                <StatCard
                    title="Total Revenue"
                    :value="formatMoney(summary.total_revenue)"
                    icon="PhCurrencyCircleDollar"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                    subtitle="Tracked order amounts"
                />
                <StatCard
                    title="Avg Orders / Customer"
                    :value="summary.avg_orders_per_customer"
                    icon="PhChartLineUp"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <PageCard
                    title="Risk Distribution"
                    description="Customer risk tiers across the platform"
                    class="xl:col-span-1"
                >
                    <div class="space-y-3">
                        <div
                            v-for="tier in riskTierRows"
                            :key="tier.key"
                            class="space-y-1"
                        >
                            <div class="flex items-center justify-between text-sm">
                                <StatusBadge :label="tier.key" :variant="tier.variant" />
                                <span class="font-medium text-gray-700 dark:text-gray-200">
                                    {{ tier.count }}
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-800">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="tier.barClass"
                                    :style="{ width: `${tier.percent}%` }"
                                />
                            </div>
                        </div>
                        <EmptyState
                            v-if="!riskTierRows.length"
                            icon="PhChartPie"
                            title="No risk data yet"
                            description="Risk tiers appear after fraud checks ingest customer stats"
                        />
                    </div>
                </PageCard>

                <PageCard
                    title="Order Status Distribution"
                    description="Current lifecycle state of all platform orders"
                    class="xl:col-span-1"
                >
                    <div class="space-y-3">
                        <div
                            v-for="status in statusRows"
                            :key="status.key"
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <StatusBadge :label="status.key" variant="neutral" />
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500">{{ status.percent }}%</span>
                                <span class="min-w-[2rem] text-right font-medium">
                                    {{ status.count }}
                                </span>
                            </div>
                        </div>
                        <EmptyState
                            v-if="!statusRows.length"
                            icon="PhFlowArrow"
                            title="No orders yet"
                            description="Orders appear when plugins send fraud-check with order context"
                        />
                    </div>
                </PageCard>

                <PageCard
                    title="System Configuration"
                    description="Current Order Intelligence settings (read-only)"
                    class="xl:col-span-1"
                >
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Status</dt>
                            <dd>
                                <StatusBadge
                                    :label="config.enabled ? 'Enabled' : 'Disabled'"
                                    :variant="config.enabled ? 'success' : 'danger'"
                                    format="none"
                                />
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Fraud check mode</dt>
                            <dd class="font-medium">{{ config.fraud_check_mode }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Search driver</dt>
                            <dd class="font-medium">{{ config.search_driver }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Suggest</dt>
                            <dd class="font-medium">{{ config.suggest_enabled ? "On" : "Off" }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Analytics</dt>
                            <dd class="font-medium">{{ config.analytics_enabled ? "On" : "Off" }}</dd>
                        </div>
                    </dl>
                </PageCard>
            </div>

            <PageCard
                title="Merchant Drill-Down"
                description="View intelligence scoped to a single merchant store"
            >
                <div class="mb-4 flex flex-wrap items-end gap-3">
                    <div class="min-w-[260px] flex-1">
                        <label class="mb-1 block text-xs text-gray-500">Merchant / Domain</label>
                        <Dropdown
                            v-model="selectedMerchantId"
                            :options="merchants"
                            option-label="label"
                            option-value="id"
                            placeholder="Select merchant"
                            class="w-full"
                            filter
                            show-clear
                            @change="loadMerchantDashboard"
                        />
                    </div>
                    <Button
                        label="Load"
                        icon="pi pi-search"
                        size="small"
                        :loading="merchantLoading"
                        :disabled="!selectedMerchantId"
                        @click="loadMerchantDashboard"
                    />
                </div>

                <div
                    v-if="merchantDashboard"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                >
                    <StatCard
                        title="Merchant Orders"
                        :value="merchantDashboard.summary.total_orders"
                        icon="PhShoppingCart"
                    />
                    <StatCard
                        title="Unique Customers"
                        :value="merchantDashboard.summary.total_customers"
                        icon="PhUsersThree"
                        accent-class="bg-violet-500"
                        icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                        icon-class="text-violet-600 dark:text-violet-400"
                    />
                    <StatCard
                        title="Revenue"
                        :value="formatMoney(merchantDashboard.summary.total_revenue)"
                        icon="PhCurrencyCircleDollar"
                        accent-class="bg-emerald-500"
                        icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                        icon-class="text-emerald-600 dark:text-emerald-400"
                    />
                </div>

                <EmptyState
                    v-else
                    icon="PhStorefront"
                    title="Select a merchant"
                    description="Choose a merchant to view store-specific order intelligence"
                />
            </PageCard>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                <PageCard title="Top Products (Global)" :description="`${topProducts.length} products`">
                    <DataTable
                        :value="topProducts"
                        size="small"
                        striped-rows
                        class="professional-table"
                    >
                        <Column field="product_title" header="Product" />
                        <Column field="order_count" header="Orders" />
                        <Column field="unique_customers" header="Customers" />
                        <Column field="merchant_count" header="Merchants" />
                    </DataTable>
                    <EmptyState
                        v-if="!topProducts.length"
                        icon="PhPackage"
                        title="No product data"
                        description="Products are tracked when orders include product titles"
                    />
                </PageCard>

                <PageCard title="Recent Platform Orders" description="Latest 10 orders across all merchants">
                    <DataTable
                        :value="recentOrders"
                        size="small"
                        striped-rows
                        class="professional-table"
                    >
                        <Column field="phone" header="Phone" />
                        <Column field="customer_name" header="Customer" />
                        <Column field="product_title" header="Product" />
                        <Column field="current_status" header="Status">
                            <template #body="{ data }">
                                <StatusBadge :label="data.current_status" variant="neutral" />
                            </template>
                        </Column>
                        <Column field="order_amount" header="Amount" />
                    </DataTable>
                </PageCard>
            </div>

            <PageCard title="Getting Started" description="How data flows into Order Intelligence">
                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-2 flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700 dark:bg-violet-500/20 dark:text-violet-300">1</span>
                            <h3 class="font-semibold">Fraud Check (Plugin)</h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Send phone + <code class="text-xs">wc_order_id</code>, name, address, product, and price on
                            <code class="text-xs">POST /api/fraud-check</code> to create platform orders.
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-2 flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-sky-100 text-sm font-bold text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">2</span>
                            <h3 class="font-semibold">Courier Entry</h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            When a shipment is created via courier API, the order moves to
                            <code class="text-xs">courier_entry</code>.
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-2 flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">3</span>
                            <h3 class="font-semibold">Webhooks</h3>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Pathao, Steadfast, and RedX webhooks update order status to delivered, returned, or canceled.
                        </p>
                    </div>
                </div>
                <div class="mt-4">
                    <Link
                        :href="route('orderIntelligence.apiDocs')"
                        class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                    >
                        View full API & integration guide
                        <Icon name="PhArrowRight" />
                    </Link>
                </div>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";
import { Icon } from "@/plugins";
import { useToast } from "primevue/usetoast";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import IntelSubNav from "./fragments/IntelSubNav.vue";

type Merchant = { id: number; label: string; domain?: string };
type DashboardPayload = {
    summary: {
        total_customers: number;
        total_orders: number;
        total_revenue: number;
        avg_orders_per_customer: number;
        risk_distribution: Record<string, number>;
        status_distribution: Record<string, number>;
    };
    top_products: Array<Record<string, unknown>>;
    recent_orders: Array<Record<string, unknown>>;
    config: Record<string, unknown>;
};

const props = defineProps<{
    merchants: Merchant[];
    dashboard: DashboardPayload;
}>();

const toast = useToast();
const selectedMerchantId = ref<number | null>(null);
const merchantLoading = ref(false);
const reindexing = ref(false);
const merchantDashboard = ref<{
    summary: { total_orders: number; total_customers: number; total_revenue: number };
} | null>(null);

const summary = computed(() => props.dashboard.summary);
const config = computed(() => props.dashboard.config as Record<string, unknown>);
const topProducts = computed(() => props.dashboard.top_products);
const recentOrders = computed(() => props.dashboard.recent_orders);

const totalRisk = computed(() =>
    Object.values(summary.value.risk_distribution || {}).reduce((a, b) => a + Number(b), 0),
);
const totalStatus = computed(() =>
    Object.values(summary.value.status_distribution || {}).reduce((a, b) => a + Number(b), 0),
);

const riskVariantMap: Record<string, "success" | "warning" | "danger"> = {
    safe: "success",
    caution: "warning",
    risky: "danger",
};
const riskBarMap: Record<string, string> = {
    safe: "bg-emerald-500",
    caution: "bg-amber-500",
    risky: "bg-rose-500",
};

const riskTierRows = computed(() =>
    Object.entries(summary.value.risk_distribution || {}).map(([key, count]) => ({
        key,
        count: Number(count),
        percent: totalRisk.value ? Math.round((Number(count) / totalRisk.value) * 100) : 0,
        variant: riskVariantMap[key] || "neutral",
        barClass: riskBarMap[key] || "bg-slate-400",
    })),
);

const statusRows = computed(() =>
    Object.entries(summary.value.status_distribution || {}).map(([key, count]) => ({
        key,
        count: Number(count),
        percent: totalStatus.value ? Math.round((Number(count) / totalStatus.value) * 100) : 0,
    })),
);

const formatMoney = (value: number | string) =>
    new Intl.NumberFormat("en-BD", { style: "currency", currency: "BDT", maximumFractionDigits: 0 }).format(
        Number(value) || 0,
    );

const loadMerchantDashboard = async () => {
    if (!selectedMerchantId.value) {
        merchantDashboard.value = null;
        return;
    }
    merchantLoading.value = true;
    try {
        const { data } = await axios.get(
            route("orderIntelligence.merchantDashboard", selectedMerchantId.value),
        );
        merchantDashboard.value = data;
    } finally {
        merchantLoading.value = false;
    }
};

const handleReindex = async () => {
    reindexing.value = true;
    try {
        const { data } = await axios.post(route("orderIntelligence.reindexSearch"));
        toast.add({ severity: "success", summary: "Search index", detail: data.message, life: 4000 });
    } catch {
        toast.add({ severity: "error", summary: "Reindex failed", life: 3000 });
    } finally {
        reindexing.value = false;
    }
};
</script>
