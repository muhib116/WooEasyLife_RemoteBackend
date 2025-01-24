<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between">
                    Packages
                    <Button
                        icon="pi pi-plus"
                        label="Create"
                        @click="showForm = true"
                    />
                </div>
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <div>
                        <DataTable scrollable :value="packages">
                            <Column
                                field="per_order_rate"
                                header="Per Order Rate"
                            ></Column>
                            <Column
                                field="description"
                                header="Description"
                            ></Column>
                            <Column field="created_at" header="Created At">
                                <template #body="{ data }">
                                    {{
                                        format(
                                            new Date(data?.created_at || null),
                                            "do MMMM yyyy, h:mm a"
                                        )
                                    }}
                                </template>
                            </Column>
                            <Column field="updated_at" header="Updated At">
                                <template #body="{ data }">
                                    {{
                                        format(
                                            new Date(data?.updated_at || null),
                                            "do MMMM yyyy, h:mm a"
                                        )
                                    }}
                                </template>
                            </Column>
                            <Column field="created_by" header="Created By">
                                <template #body="{ data }">
                                    <span
                                        class="py-1 px-4 bg-blue-100 text-blue-800 rounded-full"
                                    >
                                        {{ data.creator?.name }}
                                    </span>
                                </template>
                            </Column>
                            <Column field="is_active" header="Status">
                                <template #body="{ data }">
                                    <Badge
                                        :value="
                                            data?.is_active
                                                ? 'Active'
                                                : 'Disabled'
                                        "
                                        :severity="
                                            data?.is_active
                                                ? 'success'
                                                : 'danger'
                                        "
                                    ></Badge>
                                </template>
                            </Column>
                            <Column header="Actions">
                                <template #body="{ data }">
                                    <div class="flex gap-3"></div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </template>
        </Card>
        <Dialog
            v-model:visible="showForm"
            @hide="onClose"
            maximizable
            modal
            header="Create Package"
            :style="{ width: '50rem' }"
            :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
        >
            <CreateForm
                :form="form"
                @onClose="onClose"
                @handleSubmit="handleSubmit"
            />
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import { format } from "date-fns";
import CreateForm from "./fragments/CreateForm.vue";
import { ref } from "vue";

defineOptions({
    name: "Package",
});

const props = defineProps<{
    packages: any[];
}>();

const showForm = ref(false);

const form = useForm({
    title: "",
    description: "",
    per_order_rate: null,
    is_active: false,
});

const onClose = () => {
    showForm.value = false;
    form.reset();
    form.title = "";
    form.description = "";
    form.per_order_rate = null;
    form.is_active = false;
};

const handleSubmit = () => {
    form.post(route("packages.create"), {
        onFinish(e) {
            if (!form.hasErrors) {
                onClose();
            }
        },
    });
};
</script>
