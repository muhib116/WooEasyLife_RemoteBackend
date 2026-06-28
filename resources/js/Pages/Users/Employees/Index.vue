<template>
    <UserLayout
        title="Employees"
        section="Employees"
        subtitle="Team members who can help manage this merchant account"
        :user="user"
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

        <EmptyState
            v-if="!employees.length"
            title="No employees yet"
            description="Add team members and assign roles for this merchant."
            icon="PhUsersThree"
        >
            <Button
                v-if="can('employees.manage')"
                label="Add First Employee"
                icon="pi pi-plus"
                size="small"
                @click="openCreate"
            />
        </EmptyState>

        <PageCard
            v-else
            title="Team Members"
            :description="`${employees.length} employee${employees.length === 1 ? '' : 's'}`"
            no-padding
        >
            <DataTable
                :value="employees"
                paginator
                :rows="10"
                responsive-layout="scroll"
                class="professional-table text-sm"
            >
                <Column field="name" header="Name" />
                <Column field="email" header="Email" />
                <Column field="phone" header="Phone" />
                <Column header="Role">
                    <template #body="{ data }">
                        {{ data.role?.name || "—" }}
                    </template>
                </Column>
                <Column header="Website Scope">
                    <template #body="{ data }">
                        {{ data.website?.domain || "All websites" }}
                    </template>
                </Column>
                <Column header="Portal">
                    <template #body="{ data }">
                        <StatusBadge
                            :label="data.has_portal_access ? 'Enabled' : 'None'"
                            :variant="data.has_portal_access ? 'success' : 'neutral'"
                            format="none"
                        />
                    </template>
                </Column>
                <Column field="status" header="Status">
                    <template #body="{ data }">
                        <StatusBadge
                            :label="data.status ? 'Active' : 'Inactive'"
                            :variant="data.status ? 'success' : 'neutral'"
                            format="none"
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
            dismissable-mask
        >
            <form class="space-y-4" @submit.prevent="submitForm">
                <div>
                    <label class="mb-1 block text-sm font-medium">Full name</label>
                    <InputText
                        v-model="employeeForm.name"
                        class="w-full"
                        placeholder="Employee full name"
                    />
                    <small v-if="employeeForm.errors.name" class="text-red-500">
                        {{ employeeForm.errors.name }}
                    </small>
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
                    <ToggleSwitch v-model="employeeForm.status" />
                    <span class="text-sm">Active</span>
                </div>
                <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium">Portal login access</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Creates a sign-in account for this team member.
                            </p>
                        </div>
                        <ToggleSwitch v-model="employeeForm.grant_portal_access" />
                    </div>
                    <div v-if="employeeForm.grant_portal_access" class="mt-3">
                        <label class="mb-1 block text-sm font-medium">Portal password</label>
                        <InputText
                            v-model="employeeForm.portal_password"
                            type="password"
                            class="w-full"
                            placeholder="Minimum 8 characters"
                        />
                        <small
                            v-if="employeeForm.errors.portal_password"
                            class="text-red-500"
                        >
                            {{ employeeForm.errors.portal_password }}
                        </small>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Notes</label>
                    <Textarea
                        v-model="employeeForm.notes"
                        rows="3"
                        class="w-full"
                        placeholder="Optional internal notes"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Cancel"
                        severity="secondary"
                        outlined
                        type="button"
                        @click="showForm = false"
                    />
                    <Button
                        :label="employeeForm.id ? 'Update' : 'Create'"
                        type="submit"
                        :loading="employeeForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <ConfirmDialog id="confirm" />
    </UserLayout>
</template>

<script setup lang="ts">
import UserLayout from "../UserLayout.vue";
import PageCard from "../fragments/PageCard.vue";
import StatusBadge from "../fragments/StatusBadge.vue";
import TableActions from "../fragments/TableActions.vue";
import TableActionButton from "../fragments/TableActionButton.vue";
import EmptyState from "../fragments/EmptyState.vue";
import { usePermissions } from "@/composables/usePermissions";
import { router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useConfirm } from "primevue";

defineOptions({
    name: "UserEmployeesIndex",
});

const props = defineProps<{
    user: { id: number };
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

const resetForm = () => {
    employeeForm.reset();
    employeeForm.status = true;
    employeeForm.id = null;
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
    employeeForm.role_id = employee.role_id ?? employee.role?.id ?? null;
    employeeForm.website_id = employee.website_id ?? employee.website?.id ?? null;
    employeeForm.status = Boolean(employee.status);
    employeeForm.notes = employee.notes ?? "";
    employeeForm.grant_portal_access = Boolean(employee.has_portal_access);
    employeeForm.portal_password = "";
    showForm.value = true;
};

const submitForm = () => {
    if (employeeForm.id) {
        employeeForm.put(
            route("users.employees.update", {
                user_id: props.user.id,
                employee_id: employeeForm.id,
            }),
            {
                onSuccess: () => {
                    showForm.value = false;
                    resetForm();
                },
            },
        );
        return;
    }

    employeeForm.post(route("users.employees.store", props.user.id), {
        onSuccess: () => {
            showForm.value = false;
            resetForm();
        },
    });
};

const handleDelete = (employee: { id: number; name: string }) => {
    confirm.require({
        header: "Remove employee?",
        message: `Remove ${employee.name} from this merchant team?`,
        rejectProps: { label: "Cancel", severity: "secondary", outlined: true },
        acceptProps: { label: "Remove", severity: "danger" },
        accept: () => {
            router.delete(
                route("users.employees.destroy", {
                    user_id: props.user.id,
                    employee_id: employee.id,
                }),
            );
        },
    });
};
</script>
