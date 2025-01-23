<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between">
                    Packages
                    <Button icon="pi pi-plus" label="Create" />
                </div>
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <div>
                        <DataTable :value="packages" class="!bg-red-500">
                            <Column field="version" header="Version"></Column>
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
                            <!-- <Column field="created_by" header="Created By">
                                <template #body="{ data }">
                                    <span
                                        class="py-1 px-4 bg-blue-100 text-blue-800 rounded-full"
                                    >
                                        {{ data.creator?.name }}
                                    </span>
                                </template>
                            </Column> -->
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
        <!-- <ToggleSwitch v-model="checked" /> -->
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import { format } from "date-fns";

defineOptions({
    name: "Package",
});

const props = defineProps<{
    packages: any[];
}>();

const form = useForm({
    title: "",
    description: "",
    per_order_rate: null,
    is_active: false,
});
</script>
