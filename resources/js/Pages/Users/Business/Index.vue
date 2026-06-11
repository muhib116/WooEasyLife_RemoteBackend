<template>
    <AuthenticatedLayout title="Business">
        <div class="space-y-5">
            <PageHeader
                title="Business Profiles"
                :description="`Manage business entries for ${user?.name || 'merchant'}`"
                icon="PhBuildings"
            >
                <template #actions>
                    <Button
                        label="Add Business"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                    />
                </template>
            </PageHeader>

            <PageCard
                title="Business List"
                :description="`${business?.length || 0} record${(business?.length || 0) === 1 ? '' : 's'}`"
                no-padding
            >
                <DataTable
                    :value="business"
                    class="professional-table text-sm"
                >
                    <Column field="title" header="Title" />
                    <Column field="description" header="Description" />
                    <Column field="domain" header="Domain" />
                    <Column field="ip" header="IP" />
                    <Column header="Actions" headerStyle="width:10rem">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button
                                    label="Edit"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    @click="handleEdit(data)"
                                />
                                <Link :href="route('users.view', user?.id)">
                                    <Button
                                        label="User"
                                        size="small"
                                        icon="pi pi-arrow-right"
                                        iconPos="right"
                                        as="span"
                                    />
                                </Link>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </PageCard>
        </div>

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
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineOptions({
    name: "UserBusiness",
});

const props = defineProps<{
    user: { id?: number; name?: string };
    business: any[];
}>();

const showForm = ref(false);
const selectedBusiness = ref();

const openCreate = () => {
    selectedBusiness.value = undefined;
    showForm.value = true;
};

const handleEdit = (item: any) => {
    selectedBusiness.value = item;
    showForm.value = true;
};
</script>
