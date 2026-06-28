<template>
    <UserLayout
        title="Billing"
        section="Billing"
        subtitle="Subscription payment requests and renewals"
        :user="user"
    >
        <template #actions>
            <Button
                label="New Payment Request"
                icon="pi pi-plus"
                size="small"
                :disabled="!domains.length"
                @click="openForm"
            />
        </template>

        <PageCard
            title="Subscription Payments"
            :description="`${payments.length} record${payments.length === 1 ? '' : 's'}`"
            no-padding
        >
            <DataTable
                :value="payments"
                paginator
                :rows="10"
                responsive-layout="scroll"
                class="professional-table text-sm"
            >
                <Column field="domain" header="Domain" />
                <Column header="Plan">
                    <template #body="{ data }">
                        {{ data.package_hub?.title || "—" }}
                    </template>
                </Column>
                <Column field="order_limit" header="Orders" />
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

        <Dialog
            v-model:visible="showForm"
            header="New Payment Request"
            modal
            :style="{ width: '40rem' }"
            dismissable-mask
        >
            <form class="space-y-4" @submit.prevent="submitForm">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Domain</label>
                        <Select
                            v-model="paymentForm.domain"
                            :options="domainOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Select website domain"
                            class="w-full"
                        />
                        <small
                            v-if="!domains.length"
                            class="mt-1 block text-amber-600"
                        >
                            No websites found. Assign a plan on the Websites tab first.
                        </small>
                        <small v-if="paymentForm.errors.domain" class="text-red-500">
                            {{ paymentForm.errors.domain }}
                        </small>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Plan</label>
                        <Select
                            v-model="paymentForm.package_hub_id"
                            :options="plans"
                            option-label="title"
                            option-value="id"
                            placeholder="Select plan"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Order limit</label>
                        <InputNumber
                            v-model="paymentForm.order_limit"
                            class="w-full"
                            :min="1"
                            placeholder="e.g. 100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Total amount (TK)</label>
                        <InputNumber
                            v-model="paymentForm.total_amount"
                            class="w-full"
                            :min="0.01"
                            :min-fraction-digits="2"
                            placeholder="Enter total paid amount"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Payment method</label>
                        <InputText
                            v-model="paymentForm.transaction_method"
                            class="w-full"
                            placeholder="e.g. Bkash, Nagad, Bank"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Transaction ID</label>
                        <InputText
                            v-model="paymentForm.transaction_id"
                            class="w-full"
                            placeholder="Enter payment transaction ID"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Account number</label>
                        <InputText
                            v-model="paymentForm.account_number"
                            class="w-full"
                            placeholder="Sender mobile or account number"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Gateway charge (TK)</label>
                        <InputNumber
                            v-model="paymentForm.transaction_charge"
                            class="w-full"
                            :min="0"
                            :min-fraction-digits="2"
                            placeholder="Enter gateway fee"
                        />
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        type="button"
                        @click="showForm = false"
                    />
                    <Button label="Submit" type="submit" :loading="paymentForm.processing" />
                </div>
            </form>
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
import { router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useConfirm } from "primevue";

defineOptions({
    name: "UserBillingIndex",
});

const props = defineProps<{
    user: { id: number };
    payments: any[];
    plans: any[];
    domains: string[];
}>();

const confirm = useConfirm();
const showForm = ref(false);

const domainOptions = computed(() =>
    props.domains.map((domain) => ({
        label: domain,
        value: domain,
    })),
);

const openForm = () => {
    paymentForm.domain = props.domains[0] ?? "";
    paymentForm.package_hub_id = props.plans[0]?.id ?? null;
    showForm.value = true;
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
