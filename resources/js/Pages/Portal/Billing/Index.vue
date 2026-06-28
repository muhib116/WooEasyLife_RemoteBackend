<template>
    <MerchantPortalLayout title="Billing">
        <div class="space-y-5">
            <PageHeader
                title="Billing"
                description="Subscription alerts, active plans, and payment requests"
                icon="PhCreditCard"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            >
                <template #actions>
                    <Button
                        v-if="canManageBilling"
                        label="Submit Payment"
                        icon="pi pi-plus"
                        size="small"
                        :disabled="!domains.length || !plans.length"
                        @click="openForm"
                    />
                </template>
            </PageHeader>

            <BillingAlertsPanel :alerts="alerts" />

            <PageCard title="Active Plans" no-padding>
                <DataTable
                    :value="packages"
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

            <PageCard title="Payment Requests" no-padding>
                <DataTable
                    :value="payments"
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
                </DataTable>
            </PageCard>
        </div>

        <Dialog
            v-model:visible="showForm"
            header="Submit Payment Request"
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
                            No websites found yet. Contact support if you need a plan assigned.
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
                            @update:model-value="syncTotalAmount"
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
    </MerchantPortalLayout>
</template>

<script setup lang="ts">
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import BillingAlertsPanel from "@/Pages/Portal/fragments/BillingAlertsPanel.vue";
import { usePermissions } from "@/composables/usePermissions";
import { useForm } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

const props = defineProps<{
    payments: any[];
    packages: any[];
    plans: Array<{ id: number; title: string; per_order_rate: number }>;
    domains: string[];
    alerts: Array<{
        type: string;
        severity: string;
        message: string;
        domain?: string;
    }>;
}>();

const { can } = usePermissions();
const canManageBilling = computed(() => can("billing.manage"));

const showForm = ref(false);

const domainOptions = computed(() =>
    props.domains.map((domain) => ({
        label: domain,
        value: domain,
    })),
);

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

const selectedPlan = computed(() =>
    props.plans.find((plan) => plan.id === paymentForm.package_hub_id),
);

const syncTotalAmount = () => {
    if (!selectedPlan.value || !paymentForm.order_limit) {
        return;
    }

    paymentForm.total_amount = Number(
        (selectedPlan.value.per_order_rate * paymentForm.order_limit).toFixed(2),
    );
};

watch(
    () => paymentForm.package_hub_id,
    () => syncTotalAmount(),
);

const openForm = () => {
    paymentForm.domain = props.domains[0] ?? "";
    paymentForm.package_hub_id = props.plans[0]?.id ?? null;
    syncTotalAmount();
    showForm.value = true;
};

const submitForm = () => {
    paymentForm.post(route("portal.billing.payment-request"), {
        onSuccess: () => {
            paymentForm.reset();
            showForm.value = false;
        },
    });
};

const statusVariant = (status: string) => {
    if (status === "approved") return "success";
    if (status === "pending") return "warning";
    return "neutral";
};
</script>
