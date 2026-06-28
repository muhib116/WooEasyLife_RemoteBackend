<template>
    <AuthenticatedLayout title="Database Backups">
        <div class="space-y-5">
            <PageHeader
                title="Database Backups"
                description="Create, download, import, and manage database dump files"
                icon="PhDatabase"
                icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                icon-class="text-emerald-600 dark:text-emerald-400"
            >
                <template #actions>
                    <Button
                        label="Import Database"
                        icon="pi pi-upload"
                        severity="secondary"
                        outlined
                        size="small"
                        :disabled="importState.active"
                        @click="openImportPicker"
                    />
                    <Button
                        label="New Backup"
                        icon="pi pi-save"
                        size="small"
                        :loading="dumping"
                        :disabled="importState.active"
                        @click="createBackup"
                    />
                    <Button
                        label="Reload"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        size="small"
                        :loading="isLoading"
                        @click="getBackups"
                    />
                </template>
            </PageHeader>

            <input
                ref="fileInputRef"
                type="file"
                class="hidden"
                accept=".sql"
                @change="handleFileSelected"
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <template v-if="isLoading && !backups.length">
                    <StatCardSkeleton v-for="index in 2" :key="`backup-stat-${index}`" :delay="index * 80" />
                </template>
                <template v-else>
                    <StatCard
                        title="Backup Files"
                        :value="backups.length"
                        icon="PhHardDrives"
                        subtitle="Stored on server"
                    />
                    <StatCard
                        title="Latest Backup"
                        :value="latestBackupLabel"
                        icon="PhClock"
                        subtitle="Most recent file"
                        accent-class="bg-sky-500"
                        icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                        icon-class="text-sky-600 dark:text-sky-400"
                    />
                </template>
            </div>

            <PageCard
                title="Server Requirements"
                :description="serverRequirementsReady ? 'All required checks passed' : 'Some server settings need attention before backup/import will work reliably'"
            >
                <div class="space-y-4">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3"
                        :class="serverRequirementsReady
                            ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                            : 'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10'"
                    >
                        <div class="flex items-center gap-3">
                            <i
                                class="pi text-lg"
                                :class="serverRequirementsReady ? 'pi-check-circle text-emerald-600' : 'pi-exclamation-triangle text-amber-600'"
                            />
                            <div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ serverRequirementsReady ? "Server is ready for backup and import" : "Action required on the server" }}
                                </p>
                                <p class="text-xs text-slate-600 dark:text-slate-300">
                                    Large SQL files need MySQL CLI tools, PHP upload limits, and writable storage.
                                </p>
                            </div>
                        </div>
                        <Button
                            label="Re-check"
                            icon="pi pi-refresh"
                            severity="secondary"
                            outlined
                            size="small"
                            :loading="requirementsLoading"
                            @click="loadServerRequirements"
                        />
                    </div>

                    <div v-if="requirementsLoading" class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="index in 6"
                            :key="`req-skeleton-${index}`"
                            class="h-20 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800"
                        />
                    </div>

                    <div v-else class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="check in serverChecks"
                            :key="check.key"
                            class="min-w-0 rounded-lg border px-4 py-3 dark:border-slate-700"
                            :class="check.passed
                                ? 'border-slate-200'
                                : check.severity === 'warning'
                                    ? 'border-amber-200 bg-amber-50/60 dark:border-amber-500/30 dark:bg-amber-500/5'
                                    : 'border-rose-200 bg-rose-50/60 dark:border-rose-500/30 dark:bg-rose-500/5'"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                                        {{ check.label }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ check.detail }}
                                    </p>
                                </div>
                                <i
                                    class="pi shrink-0 text-sm"
                                    :class="check.passed
                                        ? 'pi-check-circle text-emerald-500'
                                        : check.severity === 'warning'
                                            ? 'pi-exclamation-circle text-amber-500'
                                            : 'pi-times-circle text-rose-500'"
                                />
                            </div>
                            <p class="mt-2 break-all font-mono text-xs text-slate-600 dark:text-slate-300">
                                {{ check.value }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between text-left text-sm font-semibold text-slate-800 dark:text-slate-100"
                            @click="showInstructions = !showInstructions"
                        >
                            <span>Setup instructions</span>
                            <i class="pi" :class="showInstructions ? 'pi-chevron-up' : 'pi-chevron-down'" />
                        </button>

                        <div v-if="showInstructions" class="space-y-4">
                            <div
                                v-for="section in serverInstructions"
                                :key="section.title"
                                class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700"
                            >
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ section.title }}
                                </p>
                                <ul class="mt-2 space-y-1.5 text-sm text-slate-600 dark:text-slate-300">
                                    <li
                                        v-for="(item, index) in section.items"
                                        :key="`${section.title}-${index}`"
                                        class="flex gap-2"
                                    >
                                        <span class="text-slate-400">•</span>
                                        <span>{{ item }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </PageCard>

            <PageCard
                title="Backup Files"
                :description="`${backups.length} file${backups.length === 1 ? '' : 's'} available`"
                no-padding
            >
                <div
                    v-if="isLoading"
                    class="border-t border-slate-100 dark:border-slate-800"
                >
                    <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <i class="pi pi-spin pi-spinner" />
                        Loading backup files...
                    </div>
                    <TableSkeletonLoader :columns="backupSkeletonColumns" :rows="4" />
                </div>

                <DataTable
                    v-else-if="backups.length"
                    :value="backups"
                    :rows="10"
                    paginator
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    :rowsPerPageOptions="[10, 25, 50]"
                    currentPageReportTemplate="{first} to {last} of {totalRecords} backups"
                    class="professional-table text-sm"
                >
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column field="name" header="File Name" />
                    <Column field="size" header="File Size" />
                    <Column header="Created At">
                        <template #body="{ data }">
                            {{ getTime(data.time) }}
                        </template>
                    </Column>
                    <Column header="Actions" header-class="text-right" headerStyle="width:10rem">
                        <template #body="{ data }">
                            <TableActions>
                                <TableActionButton
                                    action="download"
                                    :href="data.path"
                                    download
                                    tooltip="Download backup"
                                />
                                <TableActionButton
                                    icon="pi pi-database"
                                    severity="warn"
                                    tooltip="Import this backup"
                                    :disabled="importState.active"
                                    @click="() => importExistingBackup(data.name)"
                                />
                                <TableActionButton
                                    action="delete"
                                    tooltip="Delete backup"
                                    :disabled="importState.active"
                                    @click="() => deleteFile(data.name)"
                                />
                            </TableActions>
                        </template>
                    </Column>
                </DataTable>

                <EmptyState
                    v-else
                    icon="PhDatabase"
                    title="No backups yet"
                    description="Create a new backup to get started"
                />
            </PageCard>
        </div>

        <Dialog
            v-model:visible="importState.dialogVisible"
            modal
            :closable="!importState.active"
            :draggable="false"
            header="Import Database"
            class="w-full max-w-xl"
        >
            <div class="space-y-4">
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                    Importing will overwrite the current database. Create a backup first if you need to keep the existing data.
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">
                            {{ importState.fileName || "Preparing import..." }}
                        </span>
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ importState.overallProgress }}%
                        </span>
                    </div>
                    <ProgressBar :value="importState.overallProgress" :show-value="false" />
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700">
                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Upload
                        </p>
                        <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">
                            {{ uploadPhaseLabel }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ importState.uploadProgress }}%
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700">
                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Import
                        </p>
                        <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">
                            {{ importPhaseLabel }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ importState.importProgress }}%
                        </p>
                    </div>
                </div>

                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{ importState.message }}
                </p>

                <p
                    v-if="importState.error"
                    class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200"
                >
                    {{ importState.error }}
                </p>
            </div>

            <template #footer>
                <Button
                    v-if="!importState.active"
                    label="Close"
                    severity="secondary"
                    outlined
                    @click="closeImportDialog"
                />
            </template>
        </Dialog>

        <ConfirmDialog />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { ref, onMounted, computed, onBeforeUnmount } from "vue";
import axios from "axios";
import { format } from "date-fns";
import { useConfirm } from "primevue";
import { useToast } from "primevue/usetoast";
import Dialog from "primevue/dialog";
import ProgressBar from "primevue/progressbar";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatCardSkeleton from "@/Pages/Users/fragments/StatCardSkeleton.vue";
import TableSkeletonLoader from "@/Pages/Users/fragments/TableSkeletonLoader.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";

defineOptions({
    name: "Backups",
});

type ImportPhase = "idle" | "uploading" | "uploaded" | "queued" | "importing" | "completed" | "failed";

const confirm = useConfirm();
const toast = useToast();

const isLoading = ref(false);
const backups = ref<any[]>([]);
const dumping = ref(false);
const requirementsLoading = ref(false);
const showInstructions = ref(false);
const serverRequirementsReady = ref(false);
const serverChecks = ref<any[]>([]);
const serverInstructions = ref<any[]>([]);
const fileInputRef = ref<HTMLInputElement | null>(null);
const pollTimer = ref<number | null>(null);

const importState = ref({
    active: false,
    dialogVisible: false,
    importId: "",
    fileName: "",
    phase: "idle" as ImportPhase,
    uploadProgress: 0,
    importProgress: 0,
    overallProgress: 0,
    message: "",
    error: "",
});

const backupSkeletonColumns = [
    { width: "2rem", variant: "bar" },
    { width: "14rem", variant: "bar" },
    { width: "6rem", variant: "bar" },
    { width: "8rem", variant: "bar" },
    { width: "7rem", variant: "actions" },
];

const latestBackupLabel = computed(() => {
    if (!backups.value.length) {
        return "—";
    }

    const latest = [...backups.value].sort((a, b) => b.time - a.time)[0];
    return getTime(latest.time) || "—";
});

const uploadPhaseLabel = computed(() => {
    switch (importState.value.phase) {
        case "uploading":
            return "Uploading file...";
        case "idle":
            return "Waiting";
        default:
            return "Completed";
    }
});

const importPhaseLabel = computed(() => {
    switch (importState.value.phase) {
        case "queued":
            return "Queued";
        case "importing":
            return "Importing SQL...";
        case "completed":
            return "Completed";
        case "failed":
            return "Failed";
        case "uploading":
            return "Waiting";
        default:
            return "Waiting";
    }
});

const getTime = (dateString) => {
    try {
        return format(new Date(dateString * 1000), "dd MMM yyyy, hh:mm a");
    } catch {
        return "";
    }
};

const formatBytes = (bytes: number) => {
    if (!bytes) {
        return "0 B";
    }

    const units = ["B", "KB", "MB", "GB", "TB"];
    const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / 1024 ** exponent;

    return `${value.toFixed(exponent === 0 ? 0 : 2)} ${units[exponent]}`;
};

const updateOverallProgress = () => {
    const uploadWeight = 35;
    const importWeight = 65;
    const uploadDone = importState.value.uploadProgress >= 100 ? uploadWeight : (importState.value.uploadProgress / 100) * uploadWeight;
    const importDone = (importState.value.importProgress / 100) * importWeight;

    importState.value.overallProgress = Math.min(100, Math.round(uploadDone + importDone));
};

const resetImportState = () => {
    importState.value = {
        active: false,
        dialogVisible: false,
        importId: "",
        fileName: "",
        phase: "idle",
        uploadProgress: 0,
        importProgress: 0,
        overallProgress: 0,
        message: "",
        error: "",
    };
};

const closeImportDialog = () => {
    stopPolling();
    resetImportState();
};

const stopPolling = () => {
    if (pollTimer.value !== null) {
        window.clearInterval(pollTimer.value);
        pollTimer.value = null;
    }
};

const startPolling = (importId: string) => {
    stopPolling();

    pollTimer.value = window.setInterval(async () => {
        try {
            const { data } = await axios.get(route("backups.importStatus", importId));
            const status = data?.status;

            if (!status) {
                return;
            }

            importState.value.importProgress = status.progress ?? importState.value.importProgress;
            importState.value.message = status.message ?? importState.value.message;
            importState.value.error = status.error ?? "";
            importState.value.phase = status.status ?? importState.value.phase;
            updateOverallProgress();

            if (status.status === "completed") {
                importState.value.active = false;
                importState.value.importProgress = 100;
                importState.value.overallProgress = 100;
                importState.value.message = status.message || "Database imported successfully.";
                stopPolling();
                toast.add({
                    severity: "success",
                    summary: "Import complete",
                    detail: "The database was imported successfully.",
                    life: 5000,
                });
                await getBackups();
            }

            if (status.status === "failed") {
                importState.value.active = false;
                importState.value.error = status.error || "Database import failed.";
                importState.value.message = status.message || "Import failed.";
                stopPolling();
                toast.add({
                    severity: "error",
                    summary: "Import failed",
                    detail: importState.value.error,
                    life: 7000,
                });
            }
        } catch (error) {
            console.error(error);
        }
    }, 800);
};

const beginImportSession = async (importId: string, fileName: string, skipUpload = false) => {
    importState.value = {
        active: true,
        dialogVisible: true,
        importId,
        fileName,
        phase: skipUpload ? "queued" : "uploaded",
        uploadProgress: skipUpload ? 100 : importState.value.uploadProgress,
        importProgress: 0,
        overallProgress: skipUpload ? 35 : importState.value.overallProgress,
        message: skipUpload ? "Starting import..." : "Upload complete. Starting import...",
        error: "",
    };

    updateOverallProgress();

    await axios.post(route("backups.startImport"), {
        import_id: importId,
    });

    importState.value.phase = "queued";
    importState.value.message = "Import queued. Processing in background...";
    startPolling(importId);
};

const confirmImport = (message: string) =>
    new Promise<boolean>((resolve) => {
        confirm.require({
            header: "Import database?",
            message,
            rejectProps: {
                label: "Cancel",
                icon: "pi pi-times",
                severity: "secondary",
                size: "small",
            },
            acceptProps: {
                label: "Import",
                icon: "pi pi-database",
                severity: "danger",
                size: "small",
            },
            accept: () => resolve(true),
            reject: () => resolve(false),
        });
    });

const openImportPicker = () => {
    if (!serverRequirementsReady.value) {
        toast.add({
            severity: "warn",
            summary: "Server not ready",
            detail: "Fix the failed server requirements below before importing.",
            life: 5000,
        });
        showInstructions.value = true;
        return;
    }

    fileInputRef.value?.click();
};

const loadServerRequirements = async () => {
    requirementsLoading.value = true;

    try {
        const { data } = await axios.get(route("backups.serverRequirements"));
        serverRequirementsReady.value = Boolean(data?.ready);
        serverChecks.value = data?.checks || [];
        serverInstructions.value = data?.instructions || [];
    } catch (error) {
        console.error(error);
        toast.add({
            severity: "error",
            summary: "Could not load requirements",
            detail: "Unable to check server requirements.",
            life: 5000,
        });
    } finally {
        requirementsLoading.value = false;
    }
};

const handleFileSelected = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    input.value = "";

    if (!file) {
        return;
    }

    if (!file.name.toLowerCase().endsWith(".sql")) {
        toast.add({
            severity: "warn",
            summary: "Invalid file",
            detail: "Please choose a .sql database dump file.",
            life: 4000,
        });
        return;
    }

    const confirmed = await confirmImport(
        `This will replace the current database with "${file.name}" (${formatBytes(file.size)}). Continue?`,
    );

    if (!confirmed) {
        return;
    }

    importState.value = {
        active: true,
        dialogVisible: true,
        importId: "",
        fileName: file.name,
        phase: "uploading",
        uploadProgress: 0,
        importProgress: 0,
        overallProgress: 0,
        message: "Uploading SQL file...",
        error: "",
    };

    try {
        const formData = new FormData();
        formData.append("file", file);

        const { data } = await axios.post(route("backups.uploadImport"), formData, {
            headers: {
                "Content-Type": "multipart/form-data",
            },
            onUploadProgress: (progressEvent) => {
                const total = progressEvent.total || file.size || 1;
                importState.value.uploadProgress = Math.min(100, Math.round((progressEvent.loaded / total) * 100));
                importState.value.message = `Uploading SQL file... ${formatBytes(progressEvent.loaded)} of ${formatBytes(total)}`;
                updateOverallProgress();
            },
        });

        importState.value.uploadProgress = 100;
        updateOverallProgress();

        await beginImportSession(data.import_id, data.file_name || file.name);
    } catch (error: any) {
        importState.value.active = false;
        importState.value.phase = "failed";
        importState.value.error =
            error?.response?.data?.message ||
            error?.response?.data?.errors?.file?.[0] ||
            "Failed to upload the SQL file.";
        importState.value.message = "Upload failed.";

        toast.add({
            severity: "error",
            summary: "Upload failed",
            detail: importState.value.error,
            life: 7000,
        });
    }
};

const importExistingBackup = async (fileName: string) => {
    if (!serverRequirementsReady.value) {
        toast.add({
            severity: "warn",
            summary: "Server not ready",
            detail: "Fix the failed server requirements below before importing.",
            life: 5000,
        });
        showInstructions.value = true;
        return;
    }

    const confirmed = await confirmImport(
        `This will replace the current database with "${fileName}". Continue?`,
    );

    if (!confirmed) {
        return;
    }

    importState.value = {
        active: true,
        dialogVisible: true,
        importId: "",
        fileName,
        phase: "queued",
        uploadProgress: 100,
        importProgress: 0,
        overallProgress: 35,
        message: "Preparing import from server backup...",
        error: "",
    };

    try {
        const { data } = await axios.post(route("backups.importFromBackup", fileName));
        importState.value.importId = data.import_id;
        importState.value.message = "Import queued. Processing in background...";
        startPolling(data.import_id);
    } catch (error: any) {
        importState.value.active = false;
        importState.value.phase = "failed";
        importState.value.error =
            error?.response?.data?.message || "Failed to start the import.";
        importState.value.message = "Import failed to start.";

        toast.add({
            severity: "error",
            summary: "Import failed",
            detail: importState.value.error,
            life: 7000,
        });
    }
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
        header: "Delete this backup?",
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
    try {
        const { data } = await axios.post(route("backups.dumpDatabase"));

        if (!data?.success) {
            throw new Error(data?.message || "Backup failed");
        }

        await getBackups();
        toast.add({
            severity: "success",
            summary: "Backup created",
            detail: data.message || "A new database backup was created.",
            life: 4000,
        });
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Backup failed",
            detail: error?.response?.data?.message || error?.message || "Could not create a database backup.",
            life: 5000,
        });
    } finally {
        dumping.value = false;
    }
};

onMounted(async () => {
    await Promise.all([getBackups(), loadServerRequirements()]);
});

onBeforeUnmount(() => {
    stopPolling();
});
</script>
