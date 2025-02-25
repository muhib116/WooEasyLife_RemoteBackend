<template>
    <AuthenticatedLayout title="Log Viewer">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between gap-5">
                    Database Backups

                    <div class="flex items-center gap-5">
                        <Button
                            label="Dump the Database"
                            severity="success"
                            icon="pi pi-save"
                            @click="createBackup"
                            :loading="dumping"
                        />
                        <Button
                            label="Reload"
                            icon="pi pi-refresh"
                            @click="getBackups"
                            :loading="isLoading"
                        />
                    </div>
                </div>
            </template>
            <template #content>
                <!-- <div></div> -->
                <div class="min-h-[400px]">
                    <DataTable
                        v-if="isLoading"
                        :value="new Array(4)"
                        tableStyle="min-width: 50rem;"
                    >
                        <Column header="Timestamp">
                            <template #body>
                                <Skeleton></Skeleton>
                            </template>
                        </Column>
                        <Column header="Title">
                            <template #body>
                                <Skeleton></Skeleton>
                            </template>
                        </Column>
                        <Column header="Message">
                            <template #body>
                                <Skeleton></Skeleton>
                            </template>
                        </Column>
                    </DataTable>
                    <DataTable
                        v-else-if="backups.length"
                        :value="backups"
                        :rows="10"
                        paginator
                        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                        :rowsPerPageOptions="[10, 25, 50, 100, 200]"
                        currentPageReportTemplate="{first} to {last} of {totalRecords} Sessions &nbsp;"
                        tableStyle="min-width: 50rem;"
                    >
                        <Column header="SL" headerStyle="width:3rem">
                            <template #body="slotProps">
                                {{ slotProps.index + 1 }}
                            </template>
                        </Column>
                        <Column field="name" header="File Name" />
                        <Column field="size" header="File Size" />
                        <Column field="time" header="Created At">
                            <template #body="{ data }">
                                {{ getTime(data.time) }}
                            </template>
                        </Column>
                        <Column field="time" header="Created At">
                            <template #body="{ data }">
                                <Button 
                                    as="a"
                                    size="small"
                                    rounded
                                    icon="pi pi-download"
                                    :href="data.path"
                                    download
                                />
                                <Button 
                                    size="small"
                                    rounded
                                    @click="() => deleteFile(data.name)"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    class="ml-4"
                                />
                            </template>
                        </Column>
                    </DataTable>
                    <p v-else class="text-gray-400">No backups available.</p>
                </div>
            </template>
        </Card>
        <ConfirmDialog />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { ref, onMounted, nextTick } from "vue";
import axios from "axios";
import { format, parse } from "date-fns";
import { router } from "@inertiajs/core";
import { useConfirm } from "primevue";

defineOptions({
    name: "Backups",
});

const confirm = useConfirm();

const selectedItem = ref();

const op = ref();

const isLoading = ref(false);
const logFiles = ref<{ name: string; path: string }[]>([]);
const selectedLogFile = ref<string | null>(null);
const backups = ref<any[]>([]);
const dumping = ref(false);

const getTime = (dateString) => {
    // Parse the date string for 1739376048 record
    let op = "";
    try {
        // Format the date in a more readable format
        op = format(new Date(dateString * 1000), "dd MMM yyyy, hh:mm a");
    } catch (error) {}
    return op;
};

const getBackups = async () => {
    isLoading.value = true;
    try {
        const { data } = await axios.get(route("backups.getBackups"));
        backups.value = data || [];
    } catch (error) {
        console.error(error);
    } finally {
        isLoading.value = false;
    }
};

const deleteFile = async (fileName) => {
    confirm.require({
        header: "Are you sure to delete this backup file?",
        message: "This action cannot be undone.",
        rejectProps: {
            label: "Cancel",
            icon: "pi pi-times",
            severity: "primary",
            size: "small",
        },
        acceptProps: {
            label: "Delete",
            icon: "pi pi-check",
            severity: "danger",
            size: "small",
        },
        accept: async () => {
            await axios.post(route("backups.deleteFile", fileName));
            getBackups();
        },
    });
};

const createBackup = async () => {
    dumping.value = true;
    await axios.post(route("backups.dumpDatabase"));
    getBackups();
    dumping.value = false;
};

const deleteSession = async () => {
    confirm.require({
        header: "Are you sure to delete all expire logs?",
        message: "This action cannot be undone.",
        rejectProps: {
            label: "Cancel",
            icon: "pi pi-times",
            // outlined: true,
            severity: "primary",
            size: "small",
        },
        acceptProps: {
            label: "Clear Logs",
            icon: "pi pi-check",
            severity: "danger",
            size: "small",
        },
        accept: async () => {
            await axios.post(route("sessions.clearSession"));
            getBackups();
        },
    });
};

onMounted(async () => {
    await getBackups();
});
</script>
