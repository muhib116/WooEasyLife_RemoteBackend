<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <Header />
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <UserNav :user="user">
                        <button
                            @click="showRechargeForm = true"
                            class="py-1 px-4 bg-indigo-500 text-white flex items-center gap-2"
                        >
                            <span class="pi pi-plus"></span>
                            Add Recharge
                        </button>
                    </UserNav>

                    <div class="pt-4">
                        <DataTable
                            :value="recharge"
                            tableStyle="min-width: 50rem"
                            showGridlines
                        >
                            <Column
                                field="domain"
                                header="Domain"
                            />
                            <Column
                                field="account_number"
                                header="Account Number"
                            />
                            <Column
                                field="transaction_id"
                                header="Transaction Id"
                            />
                            <Column
                                field="transaction_method"
                                header="Method"
                            />
                            <Column
                                field="total_amount"
                                header="Total Amount"
                            />
                            <Column
                                field="transaction_charge"
                                header="Transaction Charge"
                            />
                            <Column field="status" header="Status" />
                            <Column
                                header="Action"
                                headerClass="text-right w-[12rem]"
                            >
                                <template #body="{ data }">
                                    <div
                                        v-if="data?.status == 'pending'"
                                        class="relative flex items-center justify-end gap-2"
                                    >
                                        <Button
                                            @click="handleReject(data)"
                                            severity="danger"
                                            size="small"
                                            icon="pi pi-ban"
                                        />
                                        <Button
                                            @click="handleApprove(data)"
                                            severity="info"
                                            size="small"
                                            icon="pi pi-check"
                                        />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </template>
        </Card>

        <Dialog
            v-model:visible="showRechargeForm"
            header="Add SMS Recharge"
            modal
            maximizable
            :style="{ width: '50%' }"
            draggable
            dismissableMask
            @hide="() => {}"
        >
            <RechargeForm
                v-if="showRechargeForm"
                :rechargeForm="rechargeForm"
                :user_packages="user_packages"
                @onClose="showRechargeForm = false"
                @onSubmit="handleRecharge"
            />
        </Dialog>

        <Toast />
        <ConfirmDialog id="confirm" class="min-w-[20rem]" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import UserNav from "./UserNav.vue";
import Header from "./Header.vue";
import PackageForm from "./fragments/PackageForm.vue";
import RechargeForm from "./fragments/RechargeForm.vue";
import { ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue";

defineOptions({
    name: "Packages",
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
    transaction_method: 'Cash',
    transaction_id: null,
    account_number: null,
    domain: null,
    status: null,
});

const showForm = ref(false);
const activeData = ref();

const handleRecharge = () => {
    rechargeForm.post(route("users.smsAdminRecharge", {
        user_id: props.user.id,
    }), {
        onSuccess: (response) => {
            rechargeForm.reset();
            showRechargeForm.value = false;
        },
        onError: (error) => {
            toast.add({
                severity: "error",
                summary: "Error",
                detail: 'Recharge failed',
                life: 3000,
            });
        },
    });
}

const handleApprove = (item) => {
    confirm.require({
        header: "Approve this?",
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
const handleReject = (item) => {
    confirm.require({
        header: "Reject this?",
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
