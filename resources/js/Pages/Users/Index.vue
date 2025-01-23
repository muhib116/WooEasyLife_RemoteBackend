<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex justify-between items-center gap-5">
                    User list
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
                            header="Action"
                            headerClass="text-right w-[12rem]"
                        >
                            <template #body="{ data }">
                                <div class="flex gap-2">
                                    <Link :href="route('users.view', data.id)">
                                        <Button
                                            severity="help"
                                            size="small"
                                            icon="pi pi-eye"
                                            class="pointer-events-none"
                                        />
                                    </Link>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { Link } from "@inertiajs/vue3";

defineOptions({
    name: "Users",
});

const props = defineProps<{
    users: any[];
}>();
</script>
