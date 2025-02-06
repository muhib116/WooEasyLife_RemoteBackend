<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex justify-between items-center gap-5">
                    Business list

                    <Button
                        label="Create Business"
                        icon="pi pi-plus"
                        size="small"
                        @click="showForm = true"
                    />
                </div>
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <DataTable
                        :value="business"
                        tableStyle="min-width: 50rem;background:black;"
                    >
                        <Column field="title" header="Title" />
                        <Column field="description" header="Description" />
                        <Column field="domain" header="Domain" />
                        <Column field="ip" header="IP" />
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
        <BusinessForm
            v-if="showForm"
            v-model="showForm"
            :selectedUser="selectedBusiness"
        />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { Link } from "@inertiajs/vue3";
import { ref } from "vue";
import BusinessForm from "./fragments/BusinessForm.vue";

defineOptions({
    name: "Users",
});

const props = defineProps<{
    user: object;
    business: any[]
}>();

const showForm = ref(false);
const selectedBusiness = ref();

const handleEdit = (user: any) => {
    selectedBusiness.value = user;
    showForm.value = true;
};
</script>
