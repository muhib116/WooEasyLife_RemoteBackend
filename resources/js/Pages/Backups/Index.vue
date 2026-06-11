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
            </div>

            <PageCard
                title="Backup Files"
                :description="`${backups.length} file${backups.length === 1 ? '' : 's'} available`"
                no-padding
            >
                <DataTable
                    v-if="isLoading"
                    :value="new Array(4)"
                    class="professional-table text-sm"
                >
                    <Column header="SL"><template #body><Skeleton /></template></Column>
                    <Column header="File"><template #body><Skeleton /></template></Column>
                    <Column header="Size"><template #body><Skeleton /></template></Column>
                    <Column header="Actions"><template #body><Skeleton /></template></Column>
                </DataTable>

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
                    <Column header="Actions" headerStyle="width:8rem">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button
                                    as="a"
                                    size="small"
                                    rounded
                                    icon="pi pi-download"
                                    severity="secondary"
                                    :href="data.path"
                                    download
                                />
                                <Button
                                    size="small"
                                    rounded
                                    icon="pi pi-trash"
                                    severity="danger"
                                    @click="() => deleteFile(data.name)"
                                />
                            </div>
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
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";

defineOptions({
    name: "Backups",
});

const confirm = useConfirm();

const isLoading = ref(false);
const backups = ref<any[]>([]);
const dumping = ref(false);

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
