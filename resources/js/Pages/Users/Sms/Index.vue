<template>
    <UserLayout
        title="SMS"
        section="SMS"
        subtitle="Top-up requests and outbound message history"
        :user="user"
    >
        <template #actions>
            <Button
                v-if="activeTab === 'recharge'"
                label="Add Top-up"
                icon="pi pi-plus"
                size="small"
                @click="showRechargeForm = true"
            />
        </template>

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

        <div v-if="activeTab === 'recharge'" class="space-y-4">
            <PageCard
                title="SMS Top-up Requests"
                :description="`${recharge.length} record${recharge.length === 1 ? '' : 's'}`"
                no-padding
            >
                <DataTable
                    :value="recharge"
                    paginator
                    :rows="10"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column field="domain" header="Domain" />
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
                            <TableActions v-if="data?.status == 'pending'">
                                <TableActionButton
                                    action="reject"
                                    tooltip="Reject recharge"
                                    @click="handleReject(data)"
                                />
                                <TableActionButton
                                    action="approve"
                                    tooltip="Approve recharge"
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
                title="SMS Usage Log"
                :description="`${sms_history.length} sent message${sms_history.length === 1 ? '' : 's'}`"
                no-padding
            >
                <DataTable
                    :value="sms_history"
                    paginator
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column header="Created">
                        <template #body="{ data }">
                            {{ dateFormat(data?.created_at) }}
                        </template>
                    </Column>
                    <Column field="amount" header="Cost">
                        <template #body="{ data }">
                            {{ data.amount }} TK
                        </template>
                    </Column>
                    <Column field="sms_count" header="Count" />
                    <Column field="sms_rate" header="Rate" />
                    <Column field="sms_text" header="Message">
                        <template #body="{ data }">
                            <span
                                class="line-clamp-2 max-w-xs text-gray-700 dark:text-gray-300"
                                :title="data.sms_text"
                            >
                                {{ data.sms_text || "—" }}
                            </span>
                        </template>
                    </Column>
                    <Column field="message_id" header="Message ID" />
                    <Column field="note" header="Note" />
                </DataTable>
            </PageCard>
        </div>

        <Dialog
            v-model:visible="showRechargeForm"
            header="Add SMS Top-up"
            modal
            :style="{ width: '40rem' }"
            draggable
            dismissable-mask
        >
            <RechargeForm
                v-if="showRechargeForm"
                :recharge-form="rechargeForm"
                :user_packages="user_packages"
                @on-close="showRechargeForm = false"
                @on-submit="handleRecharge"
            />
        </Dialog>

        <Toast />
    </UserLayout>
</template>

<script setup lang="ts">
import UserLayout from "../UserLayout.vue";
import PageCard from "../fragments/PageCard.vue";
import StatusBadge from "../fragments/StatusBadge.vue";
import RechargeForm from "../fragments/RechargeForm.vue";
import TableActions from "../fragments/TableActions.vue";
import TableActionButton from "../fragments/TableActionButton.vue";
import { dateFormat } from "@/Helper";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue";

defineOptions({
    name: "SmsIndex",
});

const props = defineProps<{
    user: any;
    recharge: any[];
    sms_history: any[];
    user_packages: any[];
    tab?: string;
}>();

const toast = useToast();
const confirm = useConfirm();
const showRechargeForm = ref(false);
const page = usePage();

const tabOptions = [
    { label: "Top-up Requests", value: "recharge" },
    { label: "Usage History", value: "history" },
];

const tabFromQuery = computed(() => {
    const url = new URL(page.url, window.location.origin);
    return url.searchParams.get("tab") || props.tab || "recharge";
});

const activeTab = ref(tabFromQuery.value === "history" ? "history" : "recharge");

watch(tabFromQuery, (value) => {
    activeTab.value = value === "history" ? "history" : "recharge";
});

const onTabChange = () => {
    router.get(
        route("users.sms", props.user.id),
        { tab: activeTab.value },
        { preserveState: true, replace: true },
    );
};

const rechargeForm = useForm({
    id: null,
    user_id: null,
    total_amount: null,
    transaction_charge: null,
    transaction_method: "Cash",
    transaction_id: null,
    account_number: null,
    domain: null,
    status: null,
});

const statusVariant = (status: string) => {
    if (status === "approved") return "success";
    if (status === "pending") return "warning";
    if (status === "cancelled") return "danger";
    return "neutral";
};

const handleRecharge = () => {
    rechargeForm.post(route("users.smsAdminRecharge", props.user.id), {
        onSuccess: () => {
            rechargeForm.reset();
            showRechargeForm.value = false;
        },
        onError: () => {
            toast.add({
                severity: "error",
                summary: "Error",
                detail: "Top-up request failed",
                life: 3000,
            });
        },
    });
};

const handleApprove = (item: any) => {
    confirm.require({
        header: "Approve top-up?",
        message: "This will credit SMS balance to the merchant account.",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
        },
        acceptProps: {
            label: "Approve",
        },
        accept: () => {
            router.post(route("users.approveSmsRecharge", { sms_id: item.id }));
        },
    });
};

const handleReject = (item: any) => {
    confirm.require({
        header: "Reject top-up?",
        message: "This request will be marked as cancelled.",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
        },
        acceptProps: {
            label: "Reject",
            severity: "danger",
        },
        accept: () => {
            router.post(route("users.rejectSmsRecharge", { sms_id: item.id }));
        },
    });
};
</script>
