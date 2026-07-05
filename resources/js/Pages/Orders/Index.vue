<template>
    <AuthenticatedLayout title="Landing Orders">
        <div class="space-y-5">
            <PageHeader
                title="Landing Orders"
                description="Subscription requests from the public pricing page (guests and logged-in users). For merchant portal payments, use Payment Requests."
                icon="PhShoppingCart"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <button
                    v-for="card in statCards"
                    :key="card.value"
                    type="button"
                    class="text-left transition hover:scale-[1.01] focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded-2xl"
                    :class="activeStatus === card.value ? 'ring-2 ring-primary-500/60' : ''"
                    @click="filterByStatus(card.value)"
                >
                    <StatCard
                        :title="card.label"
                        :value="counts[card.countKey]"
                        :icon="card.icon"
                        :accent-class="card.accentClass"
                    />
                </button>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <SelectButton
                    v-model="activeStatus"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    @change="onStatusChange"
                />
                <IconField class="w-full sm:max-w-xs">
                    <InputIcon>
                        <i class="pi pi-search" />
                    </InputIcon>
                    <InputText
                        v-model="searchQuery"
                        placeholder="Search email, domain, txn..."
                        class="w-full"
                        @keyup.enter="applySearch"
                    />
                </IconField>
            </div>

            <PageCard
                title="Orders"
                :description="`${orders.length} shown`"
                no-padding
            >
                <EmptyState
                    v-if="!orders.length"
                    title="No landing orders"
                    :description="emptyDescription"
                    icon="PhShoppingCart"
                />

                <DataTable
                    v-else
                    :value="orders"
                    v-model:expandedRows="expandedRows"
                    data-key="id"
                    paginator
                    :rows="15"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column expander style="width: 3rem" />
                    <Column header="Submitted">
                        <template #body="{ data }">
                            <span class="whitespace-nowrap text-xs text-gray-500">
                                {{ dateFormat(data.created_at) }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Customer">
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium">
                                    {{ data.customer_name || data.user?.name || "Guest" }}
                                </div>
                                <div class="text-xs text-gray-500">{{ data.email }}</div>
                                <div class="text-xs text-gray-500">{{ data.contact_number }}</div>
                            </div>
                        </template>
                    </Column>
                    <Column header="WhatsApp">
                        <template #body="{ data }">
                            <a
                                :href="whatsappLink(data.whatsapp_number)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ data.whatsapp_number }}
                            </a>
                        </template>
                    </Column>
                    <Column field="domain" header="Website" />
                    <Column header="Plan">
                        <template #body="{ data }">
                            {{ data.package_hub?.title || "—" }}
                        </template>
                    </Column>
                    <Column header="Amount">
                        <template #body="{ data }">
                            {{ data.total_amount }} TK
                        </template>
                    </Column>
                    <Column header="Txn ID">
                        <template #body="{ data }">
                            {{ data.transaction_id || "—" }}
                        </template>
                    </Column>
                    <Column field="transaction_method" header="Method" />
                    <Column header="Sender">
                        <template #body="{ data }">
                            {{ data.account_number || "—" }}
                        </template>
                    </Column>
                    <Column field="status" header="Status">
                        <template #body="{ data }">
                            <StatusBadge
                                :label="data.status"
                                :variant="statusVariant(data.status)"
                            />
                        </template>
                    </Column>
                    <Column header="Actions" header-class="text-right">
                        <template #body="{ data }">
                            <TableActions>
                                <TableActionButton
                                    v-if="data.status === 'pending'"
                                    action="contact"
                                    tooltip="Mark contacted"
                                    @click="confirmStatusUpdate(data, 'contacted')"
                                />
                                <TableActionButton
                                    v-if="data.status !== 'converted'"
                                    action="approve"
                                    tooltip="Mark converted"
                                    @click="confirmStatusUpdate(data, 'converted')"
                                />
                                <TableActionButton
                                    v-if="data.status !== 'rejected'"
                                    action="reject"
                                    tooltip="Reject"
                                    @click="confirmStatusUpdate(data, 'rejected')"
                                />
                            </TableActions>
                        </template>
                    </Column>
                    <template #expansion="{ data }">
                        <div class="grid gap-4 bg-slate-50/80 px-4 py-4 dark:bg-slate-900/40 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Address
                                </p>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                    {{ data.address || "—" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Note
                                </p>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                    {{ data.note || "—" }}
                                </p>
                            </div>
                            <div v-if="data.package_payment_request">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Linked payment request
                                </p>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                    #{{ data.package_payment_request.id }}
                                    ({{ data.package_payment_request.status }})
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Source
                                </p>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                    {{ data.source || "landing_pricing" }}
                                </p>
                            </div>
                        </div>
                    </template>
                </DataTable>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import StatCard from "@/components/StatCard.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import { dateFormat } from "@/Helper";
import { router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useConfirm } from "primevue";
import InputText from "primevue/inputtext";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";

defineOptions({
    name: "OrdersIndex",
});

const props = defineProps<{
    orders: any[];
    counts: {
        pending: number;
        contacted: number;
        converted: number;
        rejected: number;
    };
    status: string;
    search?: string;
}>();

const confirm = useConfirm();
const activeStatus = ref(props.status || "pending");
const searchQuery = ref(props.search || "");
const expandedRows = ref({});

const statCards = [
    { label: "Pending", value: "pending", countKey: "pending", icon: "PhHourglass", accentClass: "bg-amber-500" },
    { label: "Contacted", value: "contacted", countKey: "contacted", icon: "PhChatCircle", accentClass: "bg-sky-500" },
    { label: "Converted", value: "converted", countKey: "converted", icon: "PhCheckCircle", accentClass: "bg-emerald-500" },
    { label: "Rejected", value: "rejected", countKey: "rejected", icon: "PhXCircle", accentClass: "bg-red-500" },
] as const;

const statusOptions = [
    { label: "Pending", value: "pending" },
    { label: "Contacted", value: "contacted" },
    { label: "Converted", value: "converted" },
    { label: "Rejected", value: "rejected" },
    { label: "All", value: "all" },
];

const emptyDescription = computed(() => {
    if (searchQuery.value) {
        return "No orders match your search. Try a different email, domain, or transaction ID.";
    }

    if (activeStatus.value === "all") {
        return "Landing subscription requests will appear here when customers submit the pricing wizard.";
    }

    return `No ${activeStatus.value} landing orders right now.`;
});

const statusVariant = (status: string) => {
    if (status === "converted") return "success";
    if (status === "pending") return "warning";
    if (status === "contacted") return "info";
    if (status === "rejected") return "danger";
    return "neutral";
};

const whatsappLink = (phone: string) => {
    const digits = String(phone || "").replace(/\D/g, "");
    const normalized = digits.startsWith("880") ? digits : `880${digits.replace(/^0/, "")}`;

    return `https://wa.me/${normalized}`;
};

const queryParams = () => ({
    status: activeStatus.value,
    ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
});

const onStatusChange = () => {
    router.get(route("orders.index"), queryParams(), { preserveState: true, replace: true });
};

const filterByStatus = (status: string) => {
    activeStatus.value = status;
    onStatusChange();
};

const applySearch = () => {
    router.get(route("orders.index"), queryParams(), { preserveState: true, replace: true });
};

const statusConfirmCopy: Record<string, { header: string; message: string; acceptLabel: string; severity?: "danger" }> = {
    contacted: {
        header: "Mark as contacted?",
        message: "Use this when you have reached the customer by phone or WhatsApp.",
        acceptLabel: "Mark contacted",
    },
    converted: {
        header: "Mark as converted?",
        message: "Use this when the subscription is active or payment is fully processed.",
        acceptLabel: "Mark converted",
    },
    rejected: {
        header: "Reject this order?",
        message: "The inquiry will be marked rejected. This does not notify the customer automatically.",
        acceptLabel: "Reject",
        severity: "danger",
    },
};

const confirmStatusUpdate = (item: { id: number }, status: string) => {
    const copy = statusConfirmCopy[status];

    confirm.require({
        header: copy.header,
        message: copy.message,
        rejectProps: { label: "Cancel", severity: "secondary", outlined: true },
        acceptProps: { label: copy.acceptLabel, severity: copy.severity ?? "success" },
        accept: () => {
            router.post(route("orders.updateStatus", { order: item.id }), { status });
        },
    });
};
</script>
