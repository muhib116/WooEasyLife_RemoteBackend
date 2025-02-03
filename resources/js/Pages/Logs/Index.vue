<template>
    <AuthenticatedLayout title="Log Viewer">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between gap-5">
                    Log Viewer

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
                                    <div class="text-yellow-600">
                                        {{ data?.endpoint }}
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
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { ref, onMounted } from "vue";
import axios from "axios";
import { format, parse } from "date-fns";

defineOptions({
    name: "LogViewer",
});

const isLoading = ref(false);
const logFiles = ref<{ name: string; path: string }[]>([]);
const selectedLogFile = ref<string | null>(null);
const logs = ref<any[]>([]);

const getTime = (dateString) => {
    let op = "";
    try {
        const parsedDate = parse(dateString, "yyyy-MM-dd HH:mm:ss", new Date());

        // Format the date in a more readable format
        op = format(parsedDate, "dd MMM yyyy, hh:mm a");
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

onMounted(async () => {
    await fetchLogFiles();
    await fetchLogContent();
});
</script>
