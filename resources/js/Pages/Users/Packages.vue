<template>
    <UserLayout
        title="Packages"
        section="Packages"
        subtitle="Activate and manage order packages for this merchant"
        :user="user"
    >
        <template #actions>
            <Button
                label="Activate Package"
                icon="pi pi-plus"
                size="small"
                @click="openCreateForm"
            />
        </template>

        <PageCard
            title="User Packages"
            :description="`${user_packages.length} package${user_packages.length === 1 ? '' : 's'} assigned`"
            no-padding
        >
            <DataTable
                :value="user_packages"
                paginator
                :rows="10"
                responsive-layout="scroll"
                class="professional-table text-sm"
            >
                <Column field="title" header="Title" />
                <Column field="domain" header="Domain">
                    <template #body="{ data }">
                        <span
                            class="inline-block max-w-[220px] break-all rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-gray-700 dark:bg-slate-800 dark:text-gray-300"
                        >
                            {{ data.domain || "—" }}
                        </span>
                    </template>
                </Column>
                <Column field="total_order_can_handle" header="Quota" />
                <Column field="total_order_handled" header="Used" />
                <Column field="remaining_order" header="Remaining" />
                <Column field="is_active" header="Status">
                    <template #body="{ data }">
                        <StatusBadge
                            :label="data?.is_active ? 'Active' : 'Disabled'"
                            :variant="data?.is_active ? 'success' : 'neutral'"
                        />
                    </template>
                </Column>
                <Column field="total_cost" header="Cost">
                    <template #body="{ data }">
                        {{ data.total_cost }} TK
                    </template>
                </Column>
                <Column header="Actions" header-class="text-right">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <Button
                                severity="secondary"
                                size="small"
                                icon="pi pi-pencil"
                                outlined
                                label="Edit"
                                @click="handleEdit(data)"
                            />
                            <Button
                                severity="secondary"
                                size="small"
                                label="Details"
                                outlined
                                @click="showUseDetails = data.id"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </PageCard>

        <Dialog
            v-model:visible="showForm"
            header="Activate Package"
            modal
            :style="{ width: '35rem' }"
            draggable
            dismissable-mask
            @hide="form.reset()"
        >
            <PackageForm
                :form="form"
                :packages="packages"
                @on-close="onClose"
                @handle-save="handleSave"
            />
        </Dialog>
        <Dialog
            v-model:visible="showEditDialog"
            header="Edit Package"
            modal
            :style="{ width: '35rem' }"
            draggable
            dismissable-mask
            @hide="onCloseEdit"
        >
            <PackageForm
                :form="form"
                :packages="packages"
                @on-close="onCloseEdit"
                @handle-save="handleSave"
            />
        </Dialog>
        <Dialog
            v-model:visible="showUseDetails"
            header="Package Use Details"
            modal
            maximizable
            :style="{ width: '90%' }"
            draggable
            dismissable-mask
        >
            <UseDetails
                v-if="showUseDetails"
                :user="user"
                :id="showUseDetails"
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
import PackageForm from "./fragments/PackageForm.vue";
import UseDetails from "./fragments/UseDetails.vue";
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

const showUseDetails = ref<number | false>(false);
const showForm = ref(false);
const showEditDialog = ref(false);

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

const openCreateForm = () => {
    form.reset();
    showForm.value = true;
};

const handleEdit = (item: any) => {
    form.id = item.id;
    form.package_id = item.package_hub_id ?? item.package_id;
    form.transaction_number = item.transaction_number;
    form.transaction_id = item.transaction_id;
    form.transaction_method = item.transaction_method;
    form.transaction_charge = item.transaction_charge;
    form.domain = item.domain;
    form.note = item.note;
    form.limit = item.total_order_can_handle ?? item.limit;
    showEditDialog.value = true;
};

const onClose = () => {
    form.reset();
    showForm.value = false;
};

const onCloseEdit = () => {
    form.reset();
    showEditDialog.value = false;
};

const handleSave = () => {
    if (form.id) {
        form.post(route("users.updatePurchasePackage", props.user.id), {
            onFinish() {
                if (!form.hasErrors) {
                    onCloseEdit();
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
