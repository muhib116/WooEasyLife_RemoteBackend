<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between gap-5">
                    User list
                    {{ showForm }}
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
                    <DataTable
                        :value="users"
                        tableStyle="min-width: 50rem;background:black;"
                    >
                        <Column field="name" header="Name" />
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

defineOptions({
    name: "Users",
});

const props = defineProps<{
    users: any[];
}>();

const showForm = ref(false);
const selectedUser = ref();

const handleEdit = (user: any) => {
    selectedUser.value = user;
    showForm.value = true;
};
</script>
