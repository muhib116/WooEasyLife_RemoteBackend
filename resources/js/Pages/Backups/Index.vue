<template>
    <AuthenticatedLayout title="Database Backups">
        <div class="space-y-5">
            <PageHeader
                title="Database Backups"
                description="Create, download, and manage database dump files"
                icon="PhDatabase"
                icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                icon-class="text-emerald-600 dark:text-emerald-400"
            >
                <template #actions>
                    <Button
                        label="New Backup"
                        icon="pi pi-save"
                        size="small"
                        :loading="dumping"
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
                    <Column header="Actions" header-class="text-right" headerStyle="width:8rem">
                        <template #body="{ data }">
                            <TableActions>
                                <TableActionButton
                                    action="download"
                                    :href="data.path"
                                    download
                                    tooltip="Download backup"
                                />
                                <TableActionButton
                                    action="delete"
                                    tooltip="Delete backup"
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

        <ConfirmDialog />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { format } from "date-fns";
import { useConfirm } from "primevue";
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

const confirm = useConfirm();

const isLoading = ref(false);
const backups = ref<any[]>([]);
const dumping = ref(false);

const backupSkeletonColumns = [
    { width: "2rem", variant: "bar" },
    { width: "14rem", variant: "bar" },
    { width: "6rem", variant: "bar" },
    { width: "8rem", variant: "bar" },
    { width: "5rem", variant: "actions" },
];

const latestBackupLabel = computed(() => {
    if (!backups.value.length) {
        return "—";
    }

    const latest = [...backups.value].sort((a, b) => b.time - a.time)[0];
    return getTime(latest.time) || "—";
});

const getTime = (dateString) => {
    try {
        return format(new Date(dateString * 1000), "dd MMM yyyy, hh:mm a");
    } catch {
        return "";
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
        await axios.post(route("backups.dumpDatabase"));
        await getBackups();
    } finally {
        dumping.value = false;
    }
};

onMounted(async () => {
    await getBackups();
});
</script>
