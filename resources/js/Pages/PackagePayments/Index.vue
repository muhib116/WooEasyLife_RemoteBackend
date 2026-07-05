<template>
    <AuthenticatedLayout title="Payment Requests">
        <div class="space-y-5">
            <PageHeader
                title="Payment Requests"
                description="Review manual subscription payments from merchants (Bkash, Rocket, etc.)"
                icon="PhCreditCard"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Pending"
                    :value="counts.pending"
                    icon="PhHourglass"
                    accent-class="bg-amber-500"
                />
                <StatCard
                    title="Approved"
                    :value="counts.approved"
                    icon="PhCheckCircle"
                    accent-class="bg-emerald-500"
                />
                <StatCard
                    title="Cancelled"
                    :value="counts.cancelled"
                    icon="PhXCircle"
                    accent-class="bg-red-500"
                />
            </div>

            <SelectButton
                v-model="activeStatus"
                :options="statusOptions"
                option-label="label"
                option-value="value"
                @change="onStatusChange"
            />

            <PageCard
                title="Queue"
                :description="`${payments.length} shown`"
                no-padding
            >
                <DataTable
                    :value="payments"
                    paginator
                    :rows="15"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column header="Merchant">
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium">{{ data.user?.name }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ data.user?.email }}
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column field="domain" header="Domain" />
                    <Column header="Plan">
                        <template #body="{ data }">
                            {{ data.package_hub?.title || "—" }}
                        </template>
                    </Column>
                    <Column header="Intent">
                        <template #body="{ data }">
                            {{ formatPaymentIntentLabel(data.payment_intent) }}
                        </template>
                    </Column>
                    <Column field="order_limit" header="Orders" />
                    <Column field="transaction_id" header="Txn ID" />
                    <Column field="total_amount" header="Amount">
                        <template #body="{ data }">
                            {{ data.total_amount }} TK
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
                            <TableActions v-if="data?.status === 'pending'">
                                <TableActionButton
                                    action="approve"
                                    tooltip="Approve"
                                    @click="handleApprove(data)"
                                />
                                <TableActionButton
                                    action="reject"
                                    tooltip="Reject"
                                    @click="handleReject(data)"
                                />
                            </TableActions>
                        </template>
                    </Column>
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
import { router } from "@inertiajs/vue3";
import { ref } from "vue";
import { useConfirm } from "primevue";
import { formatPaymentIntentLabel } from "@/utils/formatLabels";

defineOptions({
    name: "PackagePaymentsIndex",
});

const props = defineProps<{
    payments: any[];
    counts: { pending: number; approved: number; cancelled: number };
    status: string;
}>();

const confirm = useConfirm();
const activeStatus = ref(props.status || "pending");

const statusOptions = [
    { label: "Pending", value: "pending" },
    { label: "Approved", value: "approved" },
    { label: "Cancelled", value: "cancelled" },
    { label: "All", value: "all" },
];

const statusVariant = (status: string) => {
    if (status === "approved") return "success";
    if (status === "pending") return "warning";
    if (status === "cancelled") return "danger";
    return "neutral";
};

const onStatusChange = () => {
    router.get(
        route("packagePayments.index"),
        { status: activeStatus.value },
        { preserveState: true, replace: true },
    );
};

const handleApprove = (item: { id: number }) => {
    confirm.require({
        header: "Approve payment?",
        message: "Subscription will be assigned or topped up for this merchant.",
        rejectProps: { label: "Cancel", severity: "secondary", outlined: true },
        acceptProps: { label: "Approve" },
        accept: () => {
            router.post(route("users.approvePackagePayment", { payment_id: item.id }));
        },
    });
};

const handleReject = (item: { id: number }) => {
    confirm.require({
        header: "Reject payment?",
        message: "This request will be marked as cancelled.",
        rejectProps: { label: "Cancel", severity: "secondary", outlined: true },
        acceptProps: { label: "Reject", severity: "danger" },
        accept: () => {
            router.post(route("users.rejectPackagePayment", { payment_id: item.id }));
        },
    });
};
</script>
