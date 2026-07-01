<template>
    <MerchantPortalLayout title="Team">
        <div class="space-y-5">
            <PageHeader
                title="Team Members"
                description="People who help run your ecommerce stores"
                icon="PhUsersThree"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            >
                <template #actions>
                    <Button
                        v-if="can('employees.manage')"
                        label="Add Employee"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                    />
                </template>
            </PageHeader>

            <EmployeeStoreSyncNotice />
            <EmployeeStoreSyncRecentIssues class="mt-4" />

            <PageCard no-padding>
                <DataTable
                    :value="employees"
                    paginator
                    :rows="10"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column header="" style="width: 3rem">
                        <template #body="{ data }">
                            <div
                                class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-gray-50 text-xs font-semibold text-gray-500 dark:border-gray-700 dark:bg-gray-800"
                            >
                                <img
                                    v-if="data.photo_url"
                                    :src="data.photo_url"
                                    :alt="data.name"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initials(data.name) }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column field="name" header="Name" />
                    <Column field="phone" header="Phone" />
                    <Column header="Role">
                        <template #body="{ data }">
                            {{ roleLabel(data.role) }}
                        </template>
                    </Column>
                    <Column header="Websites">
                        <template #body="{ data }">
                            {{ formatWebsiteScope(data) }}
                        </template>
                    </Column>
                    <Column header="Actions" header-class="text-right">
                        <template #body="{ data }">
                            <TableActions v-if="can('employees.manage')">
                                <TableActionButton
                                    action="edit"
                                    tooltip="Edit employee"
                                    @click="openEdit(data)"
                                />
                                <TableActionButton
                                    action="delete"
                                    tooltip="Remove employee"
                                    @click="handleDelete(data)"
                                />
                            </TableActions>
                        </template>
                    </Column>
                </DataTable>
            </PageCard>

            <Dialog
                v-model:visible="showForm"
                :header="employeeForm.id ? 'Edit Employee' : 'Add Employee'"
                modal
                :style="{ width: '42rem' }"
            >
                <EmployeeForm
                    :form="employeeForm"
                    :roles="roles"
                    :websites="websites"
                    :existing-photo-url="editingPhotoUrl"
                    @submit="submitForm"
                    @cancel="closeForm"
                    @photo-selected="handlePhotoSelected"
                    @photo-removed="handlePhotoRemoved"
                />
            </Dialog>

            <ConfirmDialog id="confirm" />
        </div>
    </MerchantPortalLayout>
</template>

<script setup lang="ts">
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";
import EmployeeForm from "@/Pages/Users/fragments/EmployeeForm.vue";
import EmployeeStoreSyncNotice from "@/components/EmployeeStoreSyncNotice.vue";
import EmployeeStoreSyncRecentIssues from "@/components/EmployeeStoreSyncRecentIssues.vue";
import { roleLabel } from "@/data/merchantEmployeeRoles";
import { usePermissions } from "@/composables/usePermissions";
import { router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { useConfirm } from "primevue";

const props = defineProps<{
    employees: any[];
    roles: any[];
    websites: any[];
}>();

const { can } = usePermissions();
const confirm = useConfirm();
const showForm = ref(false);
const editingPhotoUrl = ref<string | null>(null);

const employeeForm = useForm({
    id: null as number | null,
    name: "",
    email: "",
    phone: "",
    address: "",
    role_id: null as number | null,
    website_ids: [] as number[],
    status: true,
    notes: "",
    photo: null as File | null,
    remove_photo: false,
});

const initials = (name?: string | null) => {
    const parts = (name ?? "").trim().split(/\s+/).filter(Boolean);

    if (!parts.length) {
        return "?";
    }

    return parts
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? "")
        .join("");
};

const formatWebsiteScope = (employee: any) => {
    const websites = employee.websites?.length
        ? employee.websites
        : employee.website
          ? [employee.website]
          : [];

    if (!websites.length) {
        return "All websites";
    }

    return websites.map((website: { domain: string }) => website.domain).join(", ");
};

const resetForm = () => {
    employeeForm.reset();
    employeeForm.status = true;
    employeeForm.id = null;
    employeeForm.website_ids = [];
    employeeForm.photo = null;
    employeeForm.remove_photo = false;
    editingPhotoUrl.value = null;
};

const openCreate = () => {
    resetForm();
    employeeForm.role_id = props.roles[0]?.id ?? null;
    showForm.value = true;
};

const openEdit = (employee: any) => {
    employeeForm.id = employee.id;
    employeeForm.name = employee.name ?? "";
    employeeForm.email = employee.email ?? "";
    employeeForm.phone = employee.phone ?? "";
    employeeForm.address = employee.address ?? "";
    employeeForm.role_id = employee.role_id ?? employee.role?.id ?? null;
    employeeForm.website_ids = employee.website_ids?.length
        ? [...employee.website_ids]
        : employee.website_id
          ? [employee.website_id]
          : employee.website?.id
            ? [employee.website.id]
            : [];
    employeeForm.status = Boolean(employee.status);
    employeeForm.notes = employee.notes ?? "";
    employeeForm.photo = null;
    employeeForm.remove_photo = false;
    editingPhotoUrl.value = employee.photo_url ?? null;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    resetForm();
};

const handlePhotoSelected = (file: File | null) => {
    employeeForm.photo = file;
    employeeForm.remove_photo = false;
    employeeForm.clearErrors("photo");
};

const handlePhotoRemoved = () => {
    employeeForm.photo = null;
    employeeForm.remove_photo = true;
};

const submitForm = () => {
    const options = {
        onSuccess: () => closeForm(),
        preserveScroll: true,
    };

    const payloadTransform = (data: Record<string, unknown>) => {
        const { id, ...payload } = data;

        if (employeeForm.id) {
            return {
                ...payload,
                _method: "put",
            };
        }

        return payload;
    };

    if (employeeForm.id) {
        employeeForm.transform(payloadTransform).post(route("portal.employees.update", employeeForm.id), options);
        return;
    }

    employeeForm.transform(payloadTransform).post(route("portal.employees.store"), options);
};

const handleDelete = (employee: { id: number; name: string }) => {
    confirm.require({
        header: "Remove employee?",
        message: `Remove ${employee.name} from your team?`,
        accept: () => router.delete(route("portal.employees.destroy", employee.id)),
    });
};
</script>
