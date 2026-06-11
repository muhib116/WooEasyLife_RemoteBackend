<template>
    <AuthenticatedLayout title="Error Logs">
        <div class="space-y-5">
            <PageHeader
                title="Error Logs"
                description="Browse application error logs and scheduled task status"
                icon="PhWarningCircle"
                icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                icon-class="text-rose-600 dark:text-rose-400"
            >
                <template #actions>
                    <Dropdown
                        v-model="selectedLogFile"
                        :options="logFiles"
                        optionLabel="name"
                        optionValue="path"
                        placeholder="Select log file"
                        class="w-[220px]"
                        @change="fetchLogContent"
                    />
                    <Button
                        label="Schedule"
                        icon="pi pi-calendar"
                        severity="secondary"
                        outlined
                        size="small"
                        :loading="isLoadingSchedule"
                        @click="fetchSchedule"
                    />
                    <Button
                        label="Reload"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        size="small"
                        :loading="isLoading"
                        @click="fetchLogContent"
                    />
                    <Button
                        v-if="im_super"
                        label="Clear All"
                        icon="pi pi-trash"
                        severity="danger"
                        size="small"
                        :loading="clearing"
                        @click="handleClearAllLog"
                    />
                </template>
            </PageHeader>

            <p
                v-if="scheduleText"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                {{ scheduleText }}
            </p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard
                    title="Log Entries"
                    :value="logs.length"
                    icon="PhListBullets"
                    :subtitle="selectedFileName || 'No file selected'"
                />
                <StatCard
                    title="Log Files"
                    :value="logFiles.length"
                    icon="PhFileText"
                    subtitle="Available on server"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
            </div>

            <PageCard
                title="Log Entries"
                :description="selectedFileName ? `Viewing ${selectedFileName}` : 'Select a log file to view entries'"
                no-padding
            >
                <DataTable
                    v-if="isLoading"
                    :value="new Array(6)"
                    class="professional-table text-sm"
                >
                    <Column header="SL"><template #body><Skeleton /></template></Column>
                    <Column header="Timestamp"><template #body><Skeleton /></template></Column>
                    <Column header="Title"><template #body><Skeleton /></template></Column>
                    <Column header="Message"><template #body><Skeleton /></template></Column>
                </DataTable>

                <DataTable
                    v-else-if="logs.length"
                    :value="logs"
                    :rows="10"
                    paginator
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    :rowsPerPageOptions="[10, 25, 50, 100, 200]"
                    currentPageReportTemplate="{first} to {last} of {totalRecords} entries"
                    class="professional-table text-sm"
                >
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column header="Timestamp" style="min-width: 10rem">
                        <template #body="{ data }">
                            {{ getTime(data?.timestamp) }}
                        </template>
                    </Column>
                    <Column field="title" header="Title" style="min-width: 8rem" />
                    <Column field="message" header="Message" style="min-width: 20rem">
                        <template #body="{ data }">
                            <div class="space-y-1">
                                <Button
                                    icon="pi pi-info-circle"
                                    severity="help"
                                    size="small"
                                    text
                                    rounded
                                    @click="displayProduct($event, data)"
                                />
                                <div
                                    v-if="data?.token"
                                    class="text-xs text-emerald-600 dark:text-emerald-400"
                                >
                                    Token: {{ data.token }}
                                </div>
                                <div class="text-xs text-violet-600 dark:text-violet-400">
                                    Endpoint: {{ data?.endpoint }}
                                </div>
                                <div class="text-xs text-teal-600 dark:text-teal-400">
                                    Origin: {{ data?.frontendDomain }}
                                </div>
                                <div class="text-sm text-rose-600 dark:text-rose-400">
                                    {{ data.message }}
                                </div>
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <EmptyState
                    v-else
                    icon="PhFileText"
                    title="No logs available"
                    description="Select a log file or reload to fetch entries"
                />
            </PageCard>
        </div>

        <ConfirmDialog />
        <Popover ref="op" @hide="hidePopover">
            <div class="min-w-[16rem] space-y-1 p-1 text-sm">
                <div>Line: {{ selectedItem?.lineInfo?.line }}</div>
                <div>Class: {{ selectedItem?.lineInfo?.class }}</div>
            </div>
        </Popover>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { format } from "date-fns";
import { router } from "@inertiajs/core";
import { useConfirm } from "primevue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";

defineOptions({
    name: "LogViewer",
});

const props = defineProps({
    im_super: Boolean,
});

const confirm = useConfirm();

const selectedItem = ref();
const op = ref();
const isLoading = ref(false);
const scheduleText = ref("");
const isLoadingSchedule = ref(false);
const logFiles = ref<{ name: string; path: string }[]>([]);
const selectedLogFile = ref<string | null>(null);
const logs = ref<any[]>([]);
const clearing = ref(false);

const selectedFileName = computed(() => {
    const file = logFiles.value.find((f) => f.path === selectedLogFile.value);
    return file?.name || "";
});

const displayProduct = (event, item) => {
    if (selectedItem.value) {
        selectedItem.value = null;
        op.value.hide();
    } else {
        selectedItem.value = item;
        op.value.show(event);
    }
};

const hidePopover = () => {
    selectedItem.value = null;
    op.value.hide();
};

const getTime = (dateString) => {
    try {
        return format(new Date(dateString), "dd MMM yyyy, hh:mm a");
    } catch {
        return "";
    }
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

const fetchSchedule = async () => {
    try {
        isLoadingSchedule.value = true;
        const { data } = await axios.get(route("logs.schedule"));
        scheduleText.value = data;
    } catch (error) {
        console.error("Error fetching schedule:", error);
    } finally {
        isLoadingSchedule.value = false;
    }
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
        header: "Clear all logs?",
        message: "This action cannot be undone.",
        rejectProps: {
            label: "Cancel",
            icon: "pi pi-times",
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
