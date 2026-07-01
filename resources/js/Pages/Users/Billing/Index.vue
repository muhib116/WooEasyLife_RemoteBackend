<template>
    <UserLayout
        title="Billing"
        section="Billing"
        subtitle="Payment requests and active subscriptions"
        :user="user"
    >
        <template #actions>
            <Button
                v-if="activeTab === 'payments'"
                label="Add Payment Request"
                icon="pi pi-plus"
                size="small"
                :disabled="!domains.length"
                @click="openForm"
            />
        </template>

        <BillingAlertsPanel v-if="alerts.length" :alerts="alerts" />

        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <SelectButton
                v-model="activeTab"
                :options="tabOptions"
                option-label="label"
                option-value="value"
                @change="onTabChange"
            />
        </div>

        <div v-if="activeTab === 'payments'" class="space-y-4">
            <PageCard
                title="Payment Requests"
                :description="`${payments.length} record${payments.length === 1 ? '' : 's'}`"
                no-padding
            >
                <DataTable
                    :value="payments"
                    paginator
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column header="Submitted">
                        <template #body="{ data }">
                            {{ dateFormat(data?.created_at) }}
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
                    <Column field="account_number" header="Account" />
                    <Column field="transaction_id" header="Transaction ID" />
                    <Column field="transaction_method" header="Method" />
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
                                    action="reject"
                                    tooltip="Reject payment"
                                    @click="handleReject(data)"
                                />
                                <TableActionButton
                                    action="approve"
                                    tooltip="Approve payment"
                                    @click="handleApprove(data)"
                                />
                            </TableActions>
                        </template>
                    </Column>
                </DataTable>
            </PageCard>
        </div>

        <div v-else class="space-y-4">
            <PageCard
                title="Active Subscriptions"
                :description="`${packages.length} active plan${packages.length === 1 ? '' : 's'}`"
                no-padding
            >
                <DataTable
                    :value="packages"
                    paginator
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column field="domain" header="Domain" />
                    <Column field="title" header="Plan" />
                    <Column field="remaining_order" header="Remaining" />
                    <Column field="total_order_can_handle" header="Quota" />
                    <Column field="expires_at" header="Expires">
                        <template #body="{ data }">
                            {{ data.expires_at || "—" }}
                        </template>
                    </Column>
                </DataTable>
            </PageCard>
        </div>

        <Dialog
            v-model:visible="showForm"
            header="Add Payment Request"
            modal
            :style="{ width: '42rem' }"
            draggable
            dismissable-mask
        >
            <PaymentRequestFormFields
                v-if="showForm"
                :form="paymentForm"
                :plans="plans"
                :domains="domains"
                :show-payment-guide="false"
                include-cash-method
                empty-domains-message="No websites found. Assign a plan on the Websites tab first."
                submit-label="Create"
                @submit="submitForm"
                @cancel="showForm = false"
            />
        </Dialog>

        <Toast />
        <ConfirmDialog id="confirm" />
    </UserLayout>
</template>

<script setup lang="ts">
import UserLayout from "../UserLayout.vue";
import PageCard from "../fragments/PageCard.vue";
import StatusBadge from "../fragments/StatusBadge.vue";
import TableActions from "../fragments/TableActions.vue";
import TableActionButton from "../fragments/TableActionButton.vue";
import BillingAlertsPanel from "@/Pages/Portal/fragments/BillingAlertsPanel.vue";
import PaymentRequestFormFields from "@/components/PaymentRequestFormFields.vue";
import { dateFormat } from "@/Helper";
import { syncPaymentFormTotals } from "@/utils/paymentFormTotals";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { useConfirm } from "primevue";
import { useToast } from "primevue/usetoast";
import { formatPaymentIntentLabel } from "@/utils/formatLabels";

defineOptions({
    name: "UserBillingIndex",
});

const props = withDefaults(
    defineProps<{
        user: { id: number };
        payments: any[];
        packages?: any[];
        plans: any[];
        domains: string[];
        alerts?: Array<{
            type: string;
            severity: string;
            message: string;
            domain?: string;
        }>;
        tab?: string;
    }>(),
    {
        packages: () => [],
        alerts: () => [],
        tab: "payments",
    },
);

const confirm = useConfirm();
const toast = useToast();
const page = usePage();
const showForm = ref(false);

const tabOptions = [
    { label: "Payment Requests", value: "payments" },
    { label: "Active Subscriptions", value: "subscriptions" },
];

const tabFromQuery = computed(() => {
    const url = new URL(page.url, window.location.origin);
    return url.searchParams.get("tab") || props.tab || "payments";
});

const activeTab = ref(
    tabFromQuery.value === "subscriptions" ? "subscriptions" : "payments",
);

watch(tabFromQuery, (value) => {
    activeTab.value = value === "subscriptions" ? "subscriptions" : "payments";
});

const onTabChange = () => {
    router.get(
        route("users.billing", props.user.id),
        { tab: activeTab.value },
        { preserveState: true, replace: true },
    );
};

const paymentForm = useForm({
    domain: "",
    package_hub_id: null as number | null,
    order_limit: 100,
    total_amount: null as number | null,
    transaction_method: "Bkash",
    transaction_id: "",
    account_number: "",
    transaction_charge: 0,
    note: "",
});

const syncSuggestedTotal = () => {
    syncPaymentFormTotals(paymentForm, props.plans);
};

const openForm = () => {
    paymentForm.domain = props.domains[0] ?? "";
    paymentForm.package_hub_id = props.plans[0]?.id ?? null;
    paymentForm.order_limit = 100;
    syncSuggestedTotal();
    showForm.value = true;
};

const statusVariant = (status: string) => {
    if (status === "approved") return "success";
    if (status === "pending") return "warning";
    if (status === "cancelled") return "danger";
    return "neutral";
};

const submitForm = () => {
    paymentForm.post(route("users.billing.create", props.user.id), {
        onSuccess: () => {
            paymentForm.reset();
            showForm.value = false;
        },
        onError: () => {
            toast.add({
                severity: "error",
                summary: "Error",
                detail: "Payment request failed",
                life: 3000,
            });
        },
    });
};

const handleApprove = (item: { id: number }) => {
    confirm.require({
        header: "Approve payment?",
        message: "This will activate or top up the merchant subscription.",
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
