<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between gap-5">
                    User list

                    <div class="flex flex-wrap gap-4">
                        <div class="flex items-center gap-2">
                            <RadioButton
                                v-model="mode"
                                inputId="all"
                                name="all"
                                value=""
                            />
                            <label for="all">All</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <RadioButton
                                v-model="mode"
                                inputId="admin"
                                name="Admin"
                                value="admin"
                            />
                            <label for="admin">Admin</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <RadioButton
                                v-model="mode"
                                inputId="user"
                                name="user"
                                value="user"
                            />
                            <label for="user">User</label>
                        </div>
                    </div>
                    <Button
                        label="Create User"
                        icon="pi pi-plus"
                        size="small"
                        @click="showForm = true"
                    />
                </div>
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <!-- v-model:filters="filters"
                    filterDisplay="row"
                    :globalFilterFields="['name', 'email', 'phone']"
                    :rows="10"
                    paginator
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    :rowsPerPageOptions="[10, 25, 50, 100, 200]"
                    currentPageReportTemplate="{first} to {last} of {totalRecords} Users &nbsp;" -->
                    <DataTable
                        :value="getFilterData(mode, users)"
                        tableStyle="min-width: 50rem;background:black;"
                    >
                        <template #header>
                            <div class="flex justify-end">
                                <IconField>
                                    <InputIcon>
                                        <i class="pi pi-search" />
                                    </InputIcon>
                                    <InputText
                                        v-model="filters['global'].value"
                                        placeholder="Keyword Search"
                                    />
                                </IconField>
                            </div>
                        </template>
                        <Column field="name" header="Name">
                            <template #body="{data}">
                                <Badge
                                    size="small"
                                    :severity="
                                        data?.role == 'admin'
                                            ? 'success'
                                            : 'secondary'
                                    "
                                    :value="data?.role"
                                />
                                {{ data.name }}
                            </template>
                        </Column>
                        <Column field="status" header="Status">
                            <template #body="{ data }">
                                <Badge
                                    :severity="
                                        data?.status
                                            ? 'success'
                                            : 'danger'
                                    "
                                    :value="data?.status
                                            ? 'Active'
                                            : 'Disabled'"
                                />
                            </template>
                        </Column>
                        <Column field="is_test" header="Test User?">
                            <template #body="{ data }">
                                <Badge
                                    v-if="data?.role != 'admin'"
                                    :severity="
                                        data?.is_test
                                            ? 'info'
                                            : 'contrast'
                                    "
                                    :value="data?.is_test ? 'Yes' : 'No'"
                                />
                            </template>
                        </Column>
                        <Column field="email" header="Email" />
                        <Column field="phone" header="Phone" />
                        <Column
                            field="remaining_order"
                            header="Remaining Orders"
                        />
                        <Column
                            header="Action"
                            headerClass="text-right w-[12rem]"
                        >
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Button
                                        v-if="data?.role == 'user'"
                                        @click="handleEdit(data)"
                                        severity="primary"
                                        size="small"
                                        label="Edit"
                                        iconPos="right"
                                    />
                                    <Link :href="route('users.view', data.id)">
                                        <Button
                                            severity="help"
                                            size="small"
                                            label="Details"
                                            icon="pi pi-angle-right"
                                            iconPos="right"
                                            as="span"
                                        />
                                    </Link>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </Card>
        <UserForm
            v-if="showForm"
            v-model="showForm"
            @update:model-value="!showForm && (selectedUser = null)"
            :selectedUser="selectedUser"
        />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { Link } from "@inertiajs/vue3";
import { ref } from "vue";
import UserForm from "./fragments/UserForm.vue";
import { FilterMatchMode } from "@primevue/core/api";

defineOptions({
    name: "Users",
});

const props = defineProps<{
    users: any[];
}>();

const mode = ref("");

const getFilterData = (_mode, _users) => {
    if (_mode == "admin") {
        return (_users || []).filter((item) => item?.role == "admin");
    }
    if (_mode == "user") {
        return (_users || []).filter((item) => item?.role == "user");
    }
    return _users || [];
};

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    // name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
    // email: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
    // phone: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
});

const showForm = ref(false);
const selectedUser = ref();

const handleEdit = (user: any) => {
    selectedUser.value = user;
    showForm.value = true;
};
</script>
