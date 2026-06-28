<template>
    <MerchantPortalLayout title="Team">
        <div class="space-y-5">
            <PageHeader
                title="Team Members"
                description="People who can access this merchant account"
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

            <PageCard no-padding>
                <DataTable
                    :value="employees"
                    paginator
                    :rows="10"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column field="name" header="Name" />
                    <Column field="email" header="Email" />
                    <Column header="Role">
                        <template #body="{ data }">
                            {{ data.role?.name || "—" }}
                        </template>
                    </Column>
                    <Column header="Website">
                        <template #body="{ data }">
                            {{ data.website?.domain || "All websites" }}
                        </template>
                    </Column>
                    <Column header="Portal">
                        <template #body="{ data }">
                            <StatusBadge
                                :label="data.has_portal_access ? 'enabled' : 'none'"
                                :variant="data.has_portal_access ? 'success' : 'neutral'"
                            />
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
                :style="{ width: '36rem' }"
            >
                <form class="space-y-4" @submit.prevent="submitForm">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Full name</label>
                        <InputText
                            v-model="employeeForm.name"
                            class="w-full"
                            placeholder="Employee full name"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Email</label>
                            <InputText
                                v-model="employeeForm.email"
                                class="w-full"
                                placeholder="employee@example.com"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Phone</label>
                            <InputText
                                v-model="employeeForm.phone"
                                class="w-full"
                                placeholder="01XXXXXXXXX"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Role</label>
                        <Select
                            v-model="employeeForm.role_id"
                            :options="roles"
                            option-label="name"
                            option-value="id"
                            placeholder="Select role"
                            class="w-full"
                        />
                        <p
                            v-if="selectedRole?.description"
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ selectedRole.description }}
                        </p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Website scope</label>
                        <Select
                            v-model="employeeForm.website_id"
                            :options="websiteOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="All websites (leave empty for full access)"
                            show-clear
                            class="w-full"
                        />
                    </div>
                    <div class="flex items-center gap-3">
                        <ToggleSwitch v-model="employeeForm.grant_portal_access" />
                        <span class="text-sm">Grant portal login</span>
                    </div>
                    <div v-if="employeeForm.grant_portal_access">
                        <label class="mb-1 block text-sm font-medium">Portal password</label>
                        <InputText
                            v-model="employeeForm.portal_password"
                            type="password"
                            class="w-full"
                            placeholder="Minimum 8 characters"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button label="Cancel" severity="secondary" outlined type="button" @click="showForm = false" />
                        <Button :label="employeeForm.id ? 'Update' : 'Create'" type="submit" :loading="employeeForm.processing" />
                    </div>
                </form>
            </Dialog>

            <ConfirmDialog id="confirm" />
        </div>
    </MerchantPortalLayout>
</template>

<script setup lang="ts">
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";
import { usePermissions } from "@/composables/usePermissions";
import { router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useConfirm } from "primevue";

const props = defineProps<{
    employees: any[];
    roles: any[];
    websites: any[];
}>();

const { can } = usePermissions();
const confirm = useConfirm();
const showForm = ref(false);

const websiteOptions = computed(() =>
    props.websites.map((website) => ({
        label: website.domain,
        value: website.id,
    })),
);

const selectedRole = computed(() =>
    props.roles.find((role) => role.id === employeeForm.role_id),
);

const employeeForm = useForm({
    id: null as number | null,
    name: "",
    email: "",
    phone: "",
    role_id: null as number | null,
    website_id: null as number | null,
    status: true,
    notes: "",
    grant_portal_access: false,
    portal_password: "",
});

const openCreate = () => {
    employeeForm.reset();
    employeeForm.status = true;
    employeeForm.role_id = props.roles[0]?.id ?? null;
    showForm.value = true;
};

const openEdit = (employee: any) => {
    employeeForm.id = employee.id;
    employeeForm.name = employee.name ?? "";
    employeeForm.email = employee.email ?? "";
    employeeForm.phone = employee.phone ?? "";
    employeeForm.role_id = employee.role_id ?? employee.role?.id ?? null;
    employeeForm.website_id = employee.website_id ?? employee.website?.id ?? null;
    employeeForm.status = Boolean(employee.status);
    employeeForm.grant_portal_access = Boolean(employee.has_portal_access);
    employeeForm.portal_password = "";
    showForm.value = true;
};

const submitForm = () => {
    if (employeeForm.id) {
        employeeForm.put(route("portal.employees.update", employeeForm.id), {
            onSuccess: () => {
                showForm.value = false;
            },
        });
        return;
    }

    employeeForm.post(route("portal.employees.store"), {
        onSuccess: () => {
            showForm.value = false;
        },
    });
};

const handleDelete = (employee: { id: number; name: string }) => {
    confirm.require({
        header: "Remove employee?",
        message: `Remove ${employee.name} from your team?`,
        accept: () => router.delete(route("portal.employees.destroy", employee.id)),
    });
};
</script>
