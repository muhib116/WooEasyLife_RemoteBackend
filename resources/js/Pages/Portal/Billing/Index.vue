<template>
    <MerchantPortalLayout title="Billing">
        <div class="space-y-5">
            <PageHeader
                title="Billing"
                description="Payment requests and active subscriptions"
                icon="PhCreditCard"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            >
                <template #actions>
                    <Button
                        v-if="canManageBilling && activeTab === 'payments'"
                        label="Submit Payment"
                        icon="pi pi-plus"
                        size="small"
                        :disabled="!domains.length || !plans.length"
                        @click="openForm"
                    />
                </template>
            </PageHeader>

            <BillingAlertsPanel :alerts="alerts" />

            <SelectButton
                v-model="activeTab"
                :options="tabOptions"
                option-label="label"
                option-value="value"
                @change="onTabChange"
            />

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
                        <Column field="order_limit" header="Orders" />
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
        </div>

        <Dialog
            v-model:visible="showForm"
            header="Submit Payment Request"
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
                empty-domains-message="No websites found yet. Contact support if you need a plan assigned."
                submit-label="Submit"
                @submit="submitForm"
                @cancel="showForm = false"
            />
        </Dialog>

        <Toast />
    </MerchantPortalLayout>
</template>

<script setup lang="ts">
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import BillingAlertsPanel from "@/Pages/Portal/fragments/BillingAlertsPanel.vue";
import PaymentRequestFormFields from "@/components/PaymentRequestFormFields.vue";
import { dateFormat } from "@/Helper";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { syncPaymentFormTotals } from "@/utils/paymentFormTotals";
import { computed, ref, watch } from "vue";
import { usePermissions } from "@/composables/usePermissions";
import { useToast } from "primevue/usetoast";

const props = withDefaults(
    defineProps<{
        payments: any[];
        packages?: any[];
        plans: Array<{ id: number; title: string; per_order_rate: number }>;
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

const { can } = usePermissions();
const canManageBilling = computed(() => can("billing.manage"));
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
        route("portal.billing"),
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

const submitForm = () => {
    paymentForm.post(route("portal.billing.payment-request"), {
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

const statusVariant = (status: string) => {
    if (status === "approved") return "success";
    if (status === "pending") return "warning";
    if (status === "cancelled") return "danger";
    return "neutral";
};
</script>
