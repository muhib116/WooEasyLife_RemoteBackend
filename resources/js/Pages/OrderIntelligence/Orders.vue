<template>
    <AuthenticatedLayout title="Intelligence Orders">
        <div class="space-y-5">
            <PageHeader
                title="Platform Orders"
                description="Browse and filter orders tracked across all merchants"
                icon="PhPackage"
                icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                icon-class="text-emerald-600 dark:text-emerald-400"
            />

            <IntelSubNav />

            <PageCard :description="`${meta.total} orders found`">
                <div class="mb-4 flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px]">
                        <label class="mb-1 block text-xs text-gray-500">Merchant</label>
                        <Dropdown
                            v-model="filters.access_token_id"
                            :options="merchants"
                            option-label="label"
                            option-value="id"
                            placeholder="All merchants"
                            class="w-full"
                            show-clear
                            filter
                        />
                    </div>
                    <div class="min-w-[180px]">
                        <label class="mb-1 block text-xs text-gray-500">Status</label>
                        <Dropdown
                            v-model="filters.status"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="All statuses"
                            class="w-full"
                            show-clear
                        />
                    </div>
                    <div class="min-w-[200px] flex-1">
                        <label class="mb-1 block text-xs text-gray-500">Search</label>
                        <InputText
                            v-model="filters.q"
                            class="w-full"
                            placeholder="Phone, WC order ID, consignment..."
                            @keyup.enter="loadOrders(1)"
                        />
                    </div>
                    <Button label="Filter" icon="pi pi-filter" :loading="loading" @click="loadOrders(1)" />
                </div>

                <DataTable :value="orders" size="small" striped-rows class="professional-table" :loading="loading">
                    <Column field="id" header="ID" />
                    <Column field="phone" header="Phone" />
                    <Column field="customer_name" header="Customer" />
                    <Column field="wc_order_id" header="WC Order" />
                    <Column field="product_title" header="Product" />
                    <Column field="current_status" header="Status">
                        <template #body="{ data }">
                            <StatusBadge :label="data.current_status" variant="neutral" />
                        </template>
                    </Column>
                    <Column field="courier_partner" header="Courier" />
                    <Column field="consignment_id" header="Consignment" />
                    <Column field="order_amount" header="Amount" />
                    <Column field="created_at" header="Created" />
                </DataTable>

                <Paginator
                    v-if="meta.last_page > 1"
                    class="mt-4"
                    :rows="meta.per_page"
                    :total-records="meta.total"
                    :first="(meta.current_page - 1) * meta.per_page"
                    @page="onPage"
                />

                <EmptyState
                    v-if="!loading && !orders.length"
                    icon="PhPackage"
                    title="No orders found"
                    description="Adjust filters or ingest orders via fraud-check with wc_order_id"
                />
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import IntelSubNav from "./fragments/IntelSubNav.vue";

const props = defineProps<{
    merchants: Array<{ id: number; label: string }>;
    statuses: string[];
}>();

const loading = ref(false);
const orders = ref<Array<Record<string, unknown>>>([]);
const meta = ref({ current_page: 1, per_page: 25, total: 0, last_page: 1 });
const filters = reactive({
    access_token_id: null as number | null,
    status: null as string | null,
    q: "",
});

const statusOptions = computed(() => [
    { label: "All", value: null },
    ...props.statuses.map((s) => ({ label: s.replace(/_/g, " "), value: s })),
]);

const loadOrders = async (page = 1) => {
    loading.value = true;
    try {
        const { data } = await axios.get(route("orderIntelligence.ordersList"), {
            params: { ...filters, page, per_page: 25 },
        });
        orders.value = data.data;
        meta.value = data.meta;
    } finally {
        loading.value = false;
    }
};

const onPage = (event: { page: number }) => loadOrders(event.page + 1);

onMounted(() => loadOrders());
</script>
