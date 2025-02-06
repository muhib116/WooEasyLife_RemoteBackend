<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <Header />
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <UserNav :user="user">
                        <!-- <button
                            @click="showForm = true"
                            class="py-1 px-4 bg-indigo-500 text-white flex items-center gap-2"
                        >
                            <span class="pi pi-plus"></span>
                            Activate Package
                        </button> -->
                    </UserNav>

                    <div class="pt-4">
                        <DataTable
                            :value="sms_history"
                            tableStyle="min-width: 50rem"
                            showGridlines
                        >
                            <Column
                                field="domain"
                                header="Domain"
                            />
                            <Column
                                field="amount"
                                header="Amount Cost"
                            />
                            <Column
                                field="sms_rate"
                                header="Sms Rate"
                            />
                            <Column
                                field="sms_text"
                                header="SMS Text"
                            />
                            <Column
                                field="sms_count"
                                header="SMS Count"
                            />
                            <Column
                                field="message_id"
                                header="SMS id"
                            />
                            <Column field="note" header="Note" />
                        </DataTable>
                    </div>
                </div>
            </template>
        </Card>

        <Toast />
        <ConfirmDialog id="confirm" class="min-w-[20rem]" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import UserNav from "./UserNav.vue";
import Header from "./Header.vue";
import PackageForm from "./fragments/PackageForm.vue";
import { ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue";

defineOptions({
    name: "Packages",
});

const props = defineProps<{
    user: any;
    sms_history: any[];
}>();

const toast = useToast();

const confirm = useConfirm();

const showForm = ref(false);
const activeData = ref();

const getItems = (data) => {
    const items = [
        {
            label: "Approve",
            command: async () => {},
        },
        {
            label: "Reject",
            command: () => {},
        },
    ];

    return items;
};

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
