<template>
    <AuthenticatedLayout title="Intelligence Customers">
        <div class="space-y-5">
            <PageHeader
                title="Customer Intelligence"
                description="Search platform customers by phone, view risk profiles, and courier fraud notes"
                icon="PhUsers"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
            />

            <IntelSubNav />

            <PageCard title="Phone Lookup" description="Full profile for a single customer phone number">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label class="mb-1 block text-xs text-gray-500">Phone number</label>
                        <InputText
                            v-model="lookupPhone"
                            class="w-full"
                            placeholder="01712345678"
                            @keyup.enter="runLookup"
                        />
                    </div>
                    <div class="min-w-[220px]">
                        <label class="mb-1 block text-xs text-gray-500">Merchant scope (optional)</label>
                        <Dropdown
                            v-model="lookupMerchantId"
                            :options="merchants"
                            option-label="label"
                            option-value="id"
                            placeholder="Platform-wide"
                            class="w-full"
                            show-clear
                            filter
                        />
                    </div>
                    <Button
                        label="Lookup"
                        icon="pi pi-search"
                        :loading="lookupLoading"
                        @click="runLookup"
                    />
                </div>

                <div v-if="lookupResult" class="mt-6 space-y-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <StatCard
                            title="Risk Tier"
                            :value="lookupResult.platform?.platform_intelligence?.risk_tier || '—'"
                            icon="PhShieldWarning"
                        />
                        <StatCard
                            title="Total Orders"
                            :value="lookupResult.platform?.platform_intelligence?.total_orders ?? 0"
                            icon="PhPackage"
                        />
                        <StatCard
                            title="Merchants"
                            :value="lookupResult.platform?.platform_intelligence?.total_merchants ?? 0"
                            icon="PhStorefront"
                        />
                    </div>

                    <div v-if="lookupResult.customer" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <h3 class="mb-2 font-semibold">Customer Record</h3>
                        <dl class="grid gap-2 text-sm sm:grid-cols-2">
                            <div><dt class="text-gray-500">Name</dt><dd>{{ lookupResult.customer.latest_name || "—" }}</dd></div>
                            <div><dt class="text-gray-500">Address</dt><dd>{{ lookupResult.customer.latest_address || "—" }}</dd></div>
                            <div><dt class="text-gray-500">First seen</dt><dd>{{ lookupResult.customer.first_seen_at || "—" }}</dd></div>
                            <div><dt class="text-gray-500">Last order</dt><dd>{{ lookupResult.customer.last_order_at || "—" }}</dd></div>
                        </dl>
                    </div>

                    <div v-if="lookupResult.platform?.courier_fraud_notes?.length">
                        <h3 class="mb-2 font-semibold text-rose-600 dark:text-rose-400">Courier Fraud Notes</h3>
                        <div class="space-y-2">
                            <div
                                v-for="(note, i) in lookupResult.platform.courier_fraud_notes"
                                :key="i"
                                class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm dark:border-rose-500/30 dark:bg-rose-500/10"
                            >
                                <div class="font-medium">{{ note.courier }} — {{ note.name }}</div>
                                <p class="mt-1 text-gray-700 dark:text-gray-300">{{ note.details }}</p>
                            </div>
                        </div>
                    </div>

                    <DataTable
                        v-if="lookupResult.recent_orders?.length"
                        :value="lookupResult.recent_orders"
                        size="small"
                        striped-rows
                        class="professional-table"
                    >
                        <Column field="wc_order_id" header="WC Order" />
                        <Column field="product_title" header="Product" />
                        <Column field="current_status" header="Status">
                            <template #body="{ data }">
                                <StatusBadge :label="data.current_status" variant="neutral" />
                            </template>
                        </Column>
                        <Column field="order_amount" header="Amount" />
                        <Column field="created_at" header="Created" />
                    </DataTable>
                </div>

                <EmptyState
                    v-else-if="lookupSearched && !lookupLoading"
                    icon="PhMagnifyingGlass"
                    title="No customer found"
                    description="This phone has no platform intelligence yet. Run a fraud check with order context to ingest."
                />
            </PageCard>

            <PageCard
                title="All Customers"
                :description="`${customersMeta.total} customers in platform stats`"
            >
                <div class="mb-4 flex flex-wrap items-end gap-3">
                    <IconField class="w-full sm:w-64">
                        <InputIcon class="pi pi-search" />
                        <InputText
                            v-model="filters.q"
                            placeholder="Search phone..."
                            class="w-full"
                            @keyup.enter="loadCustomers(1)"
                        />
                    </IconField>
                    <Dropdown
                        v-model="filters.risk_tier"
                        :options="riskTierOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="All risk tiers"
                        class="w-44"
                        show-clear
                    />
                    <Button label="Search" icon="pi pi-search" size="small" :loading="listLoading" @click="loadCustomers(1)" />
                </div>

                <DataTable
                    :value="customers"
                    size="small"
                    striped-rows
                    class="professional-table"
                    :loading="listLoading"
                >
                    <Column field="phone" header="Phone" />
                    <Column field="total_orders" header="Orders" />
                    <Column field="total_merchants" header="Merchants" />
                    <Column field="risk_tier" header="Risk">
                        <template #body="{ data }">
                            <StatusBadge :label="data.risk_tier" :variant="riskVariant(data.risk_tier)" />
                        </template>
                    </Column>
                    <Column field="risk_score" header="Score" />
                    <Column field="stats_computed_at" header="Updated" />
                    <Column header="">
                        <template #body="{ data }">
                            <Button
                                label="View"
                                size="small"
                                text
                                @click="viewCustomer(data.phone)"
                            />
                        </template>
                    </Column>
                </DataTable>

                <Paginator
                    v-if="customersMeta.last_page > 1"
                    class="mt-4"
                    :rows="customersMeta.per_page"
                    :total-records="customersMeta.total"
                    :first="(customersMeta.current_page - 1) * customersMeta.per_page"
                    @page="onPage"
                />
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import IntelSubNav from "./fragments/IntelSubNav.vue";

const props = defineProps<{
    merchants: Array<{ id: number; label: string }>;
    riskTiers: string[];
}>();

const lookupPhone = ref("");
const lookupMerchantId = ref<number | null>(null);
const lookupLoading = ref(false);
const lookupSearched = ref(false);
const lookupResult = ref<Record<string, any> | null>(null);

const listLoading = ref(false);
const customers = ref<Array<Record<string, unknown>>>([]);
const customersMeta = ref({ current_page: 1, per_page: 25, total: 0, last_page: 1 });
const filters = reactive({ q: "", risk_tier: null as string | null });

const riskTierOptions = [{ label: "All", value: null }, ...props.riskTiers.map((t) => ({ label: t, value: t }))];

const riskVariant = (tier: string) =>
    ({ safe: "success", caution: "warning", risky: "danger" }[tier] || "neutral") as "success" | "warning" | "danger";

const runLookup = async () => {
    if (!lookupPhone.value.trim()) return;
    lookupLoading.value = true;
    lookupSearched.value = true;
    try {
        const { data } = await axios.get(route("orderIntelligence.customerLookup"), {
            params: {
                phone: lookupPhone.value.trim(),
                access_token_id: lookupMerchantId.value || undefined,
            },
        });
        lookupResult.value = data;
    } catch {
        lookupResult.value = null;
    } finally {
        lookupLoading.value = false;
    }
};

const viewCustomer = (phone: string) => {
    lookupPhone.value = phone;
    lookupMerchantId.value = null;
    runLookup();
    window.scrollTo({ top: 0, behavior: "smooth" });
};

const loadCustomers = async (page = 1) => {
    listLoading.value = true;
    try {
        const { data } = await axios.get(route("orderIntelligence.customersList"), {
            params: { ...filters, page, per_page: 25 },
        });
        customers.value = data.data;
        customersMeta.value = data.meta;
    } finally {
        listLoading.value = false;
    }
};

const onPage = (event: { page: number }) => {
    loadCustomers(event.page + 1);
};

onMounted(() => loadCustomers());
</script>
