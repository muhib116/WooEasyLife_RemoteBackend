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
                            class="flex items-center gap-2 bg-primary-500 px-4 py-1 text-white"
                        >
                            <span class="pi pi-plus"></span>
                            Activate Package
                        </button>
                    </UserNav>

                    <div class="pt-4">
                        <DataTable
                            :value="user_packages"
                            tableStyle="min-width: 50rem"
                            showGridlines
                        >
                            <Column field="title" header="Title" />
                            <Column field="domain" header="Accessed Domain" />
                            <Column
                                field="total_order_can_handle"
                                header="Can Handle"
                            />
                            <Column
                                field="total_order_handled"
                                header="Handled"
                            />
                            <Column field="is_active" header="Active">
                                <template #body="{ data }">
                                    <Badge
                                        severity="success"
                                        v-if="data?.is_active"
                                        >Active</Badge
                                    >
                                    <Badge v-else>Disabled</Badge>
                                </template>
                            </Column>
                            <Column
                                field="per_order_rate"
                                header="Order Rate"
                            />
                            <Column field="total_cost" header="Total Cost" />
                            <Column
                                field="transaction_charge"
                                header="Transaction Charge"
                            />
                            <Column
                                header="Action"
                                headerClass="text-right w-[12rem]"
                            >
                                <template #body="{ data }">
                                    <div class="flex gap-2">
                                        <Button
                                            @click="handleEdit(data)"
                                            severity="help"
                                            size="small"
                                            icon="pi pi-eye"
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
            v-model:visible="showForm"
            header="Package"
            modal
            maximizable
            :style="{ width: '35rem' }"
            draggable
            dismissableMask
            @hide="form.reset()"
        >
            <PackageForm
                :form="form"
                :packages="packages"
                @onClose="onClose"
                @handleSave="handleSave"
            />
        </Dialog>
        <Dialog
            v-model:visible="selectedItem"
            header="Details"
            modal
            maximizable
            :style="{ width: '35rem' }"
            draggable
            dismissableMask
            @hide="form.reset()"
        >
            <!-- {{ selectedItem }} -->
            <PackageForm
                :form="form"
                :packages="packages"
                @onClose="onClose"
                @handleSave="handleSave"
            />
        </Dialog>

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
import { useForm } from "@inertiajs/vue3";

defineOptions({
    name: "Packages",
});

const props = defineProps<{
    user: any;
    packages: any[];
    user_packages: any[];
}>();

const selectedItem = ref(false);

// title
// description
// domain
// user_id
// package_hub_id
// total_order_can_handle
// total_order_handled
// per_order_rate
// is_active
// note
// created_by
// updated_by

const showForm = ref(false);

const form = useForm({
    id: null,
    package_id: null,
    transaction_number: null,
    transaction_id: null,
    transaction_method: "Cash",
    transaction_charge: 0,
    domain: null,
    note: null,
    limit: 300,
});

const handleEdit = (item) => {
    form.id = item.id;
    form.package_id = item.package_id;
    form.transaction_number = item.transaction_number;
    form.transaction_id = item.transaction_id;
    form.transaction_method = item.transaction_method;
    form.transaction_charge = item.transaction_charge;
    form.domain = item.domain;
    form.note = item.note;
    form.limit = item.limit;
    selectedItem.value = item;
};

const onClose = () => {
    form.reset();
    showForm.value = false;
};

const handleSave = () => {
    if (form.id) {
        form.post(route("users.updatePurchasePackage", props.user.id), {
            onFinish() {
                if (!form.hasErrors) {
                    onClose();
                }
            },
        });
    } else {
        form.post(route("users.purchasePackage", props.user.id), {
            onFinish() {
                if (!form.hasErrors) {
                    onClose();
                }
            },
        });
    }
};
</script>
