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
                            @click="showForm = true"
                            class="py-1 px-4 bg-indigo-500 text-white flex items-center gap-2"
                        >
                            <span class="pi pi-plus"></span>
                            Activate Package
                        </button>
                    </UserNav>

                    <div class="pt-4">
                        <DataTable
                            :value="recharge"
                            tableStyle="min-width: 50rem"
                            showGridlines
                        >
                            <Column field="account_number" header="Account Number" />
                            <Column field="transaction_id" header="Transaction Id" />
                            <Column field="transaction_method" header="Method" />
                            <Column field="total_amount" header="Total Amount" />
                            <Column
                                field="transaction_charge"
                                header="Transaction Charge"
                            />
                            <Column
                                field="status"
                                header="Status"
                            />
                            <Column
                                header="Action"
                                headerClass="text-right w-[12rem]"
                            >
                                <template #body="{ data }">
                                    <div class="flex gap-2">
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

        <Toast />
        <ConfirmDialog id="confirm" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import UserNav from "./UserNav.vue";
import Header from "./Header.vue";
import PackageForm from "./fragments/PackageForm.vue";
import { ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";

defineOptions({
    name: "Packages",
});

const props = defineProps<{
    user: any;
    recharge: any[];
}>();

const showForm = ref(false);

const handleApprove = (item) => {
    router.post(route("users.approveSmsRecharge", { 
        sms_id: item.id
    }))
};
</script>
