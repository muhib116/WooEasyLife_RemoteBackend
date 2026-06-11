<template>
    <UserLayout
        title="SMS Recharge"
        section="SMS Recharge"
        subtitle="Review and approve SMS balance recharges"
        :user="user"
    >
        <template #actions>
            <Button
                label="Add Recharge"
                icon="pi pi-plus"
                size="small"
                @click="showRechargeForm = true"
            />
        </template>

        <PageCard
            title="Recharge Requests"
            :description="`${recharge.length} recharge record${recharge.length === 1 ? '' : 's'}`"
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
                        <div
                            v-if="data?.status == 'pending'"
                            class="flex justify-end gap-2"
                        >
                            <Button
                                label="Reject"
                                severity="danger"
                                size="small"
                                outlined
                                @click="handleReject(data)"
                            />
                            <Button
                                label="Approve"
                                severity="success"
                                size="small"
                                @click="handleApprove(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </PageCard>

        <Dialog
            v-model:visible="showRechargeForm"
            header="Add SMS Recharge"
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
        <ConfirmDialog id="confirm" />
    </UserLayout>
</template>

<script setup lang="ts">
import UserLayout from "./UserLayout.vue";
import PageCard from "./fragments/PageCard.vue";
import StatusBadge from "./fragments/StatusBadge.vue";
import RechargeForm from "./fragments/RechargeForm.vue";
import { ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue";

defineOptions({
    name: "SmsRecharge",
});

const props = defineProps<{
    user: any;
    recharge: any[];
    user_packages: any[];
}>();

const toast = useToast();
const confirm = useConfirm();
const showRechargeForm = ref(false);

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
    rechargeForm.post(
        route("users.smsAdminRecharge", {
            user_id: props.user.id,
        }),
        {
            onSuccess: () => {
                rechargeForm.reset();
                showRechargeForm.value = false;
            },
            onError: () => {
                toast.add({
                    severity: "error",
                    summary: "Error",
                    detail: "Recharge failed",
                    life: 3000,
                });
            },
        },
    );
};

const handleApprove = (item: any) => {
    confirm.require({
        header: "Approve recharge?",
        message: "This will credit SMS balance to the user account.",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
        },
        acceptProps: {
            label: "Approve",
        },
        accept: () => {
            router.post(
                route("users.approveSmsRecharge", {
                    sms_id: item.id,
                }),
            );
        },
    });
};

const handleReject = (item: any) => {
    confirm.require({
        header: "Reject recharge?",
        message: "This recharge request will be marked as cancelled.",
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
            router.post(
                route("users.rejectSmsRecharge", {
                    sms_id: item.id,
                }),
            );
        },
    });
};
</script>
