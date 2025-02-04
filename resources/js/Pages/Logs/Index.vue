<template>
    <AuthenticatedLayout title="Log Viewer">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between gap-5">
                    Log Viewer

                    <div class="flex items-center gap-5">
                        <Button
                            v-if="im_super"
                            label="Clear"
                            severity="danger"
                            icon="pi pi-times-circle"
                            @click="handleClearAllLog"
                            :loading="clearing"
                        />
                        <Button
                            label="Reload"
                            icon="pi pi-refresh"
                            @click="fetchLogContent"
                            :loading="isLoading"
                        />
                        <Dropdown
                            v-model="selectedLogFile"
                            :options="logFiles"
                            optionLabel="name"
                            optionValue="path"
                            placeholder="Select Log File"
                            class="w-[250px]"
                            @change="fetchLogContent"
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
                        v-else-if="logs.length"
                        :value="logs"
                        :rows="10"
                        paginator
                        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                        :rowsPerPageOptions="[10, 25, 50, 100, 200]"
                        currentPageReportTemplate="{first} to {last} of {totalRecords} Error &nbsp;"
                        tableStyle="min-width: 50rem;"
                    >
                        <Column header="SL" headerStyle="width:3rem">
                            <template #body="slotProps">
                                {{ slotProps.index + 1 }}
                            </template>
                        </Column>
                        <Column
                            :field="(data) => getTime(data?.timestamp)"
                            header="Timestamp"
                        />
                        <Column field="title" header="Title" />
                        <Column
                            class="max-w-[30rem]"
                            field="message"
                            header="Message"
                        >
                            <template #body="{ data }">
                                <div>
                                    <div
                                        v-if="data?.token"
                                        class="border-b border-green-400/50 text-green-500 dark:text-green-300"
                                    >
                                        <span>Token: </span>
                                        {{ data?.token }}
                                    </div>
                                    <div
                                        class="border-b border-violet-400/50 text-violet-500 dark:text-violet-300"
                                    >
                                        <span>Endpoint: </span>
                                        {{ data?.endpoint }}
                                    </div>
                                    <div
                                        class="border-b border-teal-400/50 text-teal-500 dark:text-teal-300"
                                    >
                                        <span>Origin Url: </span>
                                        {{ data?.frontendDomain }}
                                    </div>
                                    <div class="text-red-600 dark:text-red-400">
                                        {{ data.message }}
                                    </div>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                    <p v-else class="text-gray-400">No logs available.</p>
                </div>
            </template>
        </Card>
        <ConfirmDialog />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { ref, onMounted } from "vue";
import axios from "axios";
import { format, parse } from "date-fns";
import { router } from "@inertiajs/core";
import { useConfirm } from "primevue";

defineOptions({
    name: "LogViewer",
});

const props = defineProps({
    im_super: Boolean,
});

const confirm = useConfirm();

const isLoading = ref(false);
const logFiles = ref<{ name: string; path: string }[]>([]);
const selectedLogFile = ref<string | null>(null);
const logs = ref<any[]>([]);
const clearing = ref(false);

const getTime = (dateString) => {
    let op = "";
    try {
        // Format the date in a more readable format
        op = format(new Date(dateString), "dd MMM yyyy, hh:mm a");
    } catch (error) {}
    return op;
};

const fetchLogFiles = async () => {
    isLoading.value = true;
    try {
        const { data } = await axios.get(route("logs.list"));
        logFiles.value = data.files;

        if (logFiles.value.length > 0) {
            selectedLogFile.value = logFiles.value[0].path;
        }
    } catch (error) {
        console.error("Error fetching log files:", error);
    }
    isLoading.value = false;
};

const fetchLogContent = async () => {
    if (!selectedLogFile.value) return;
    isLoading.value = true;
    try {
        const { data } = await axios.post(route("logs.view"), {
            file: selectedLogFile.value,
        });
        logs.value = data.logs;
    } catch (error) {
        console.error("Error fetching log content:", error);
        logs.value = [];
    }
    isLoading.value = false;
};

const handleClearAllLog = () => {
    confirm.require({
        header: "Are you sure to delete all logs?",
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
        accept: () => {
            clearing.value = true;
            router.post(
                route("logs.clearAllLog"),
                {},
                {
                    async onFinish() {
                        clearing.value = false;
                        await fetchLogFiles();
                        await fetchLogContent();
                    },
                },
            );
        },
    });
};

onMounted(async () => {
    await fetchLogFiles();
    await fetchLogContent();
});
</script>
