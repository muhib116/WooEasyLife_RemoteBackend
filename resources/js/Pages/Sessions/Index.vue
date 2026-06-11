<template>
    <AuthenticatedLayout title="Sessions">
        <div class="space-y-5">
            <PageHeader
                title="Active Sessions"
                description="Monitor and manage database-stored user sessions"
                icon="PhDevices"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <Button
                        label="Clear Expired"
                        icon="pi pi-clock"
                        severity="warn"
                        outlined
                        size="small"
                        :loading="clearing"
                        @click="deleteSession"
                    />
                    <Button
                        label="Clear All"
                        icon="pi pi-trash"
                        severity="danger"
                        size="small"
                        :loading="clearing"
                        @click="deleteAllSession"
                    />
                    <Button
                        label="Reload"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        size="small"
                        :loading="isLoading"
                        @click="getSessions"
                    />
                </template>
            </PageHeader>

            <StatCard
                title="Active Sessions"
                :value="sessions.length"
                icon="PhMonitor"
                subtitle="Currently stored in database"
            />

            <PageCard
                title="Session Records"
                :description="`${sessions.length} session${sessions.length === 1 ? '' : 's'}`"
                no-padding
            >
                <DataTable
                    v-if="isLoading"
                    :value="new Array(5)"
                    class="professional-table text-sm"
                >
                    <Column header="SL"><template #body><Skeleton /></template></Column>
                    <Column header="User"><template #body><Skeleton /></template></Column>
                    <Column header="IP"><template #body><Skeleton /></template></Column>
                </DataTable>

                <DataTable
                    v-else-if="sessions.length"
                    :value="sessions"
                    :rows="15"
                    paginator
                    class="professional-table text-sm"
                >
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column field="id" header="ID" />
                    <Column field="user_id" header="User" />
                    <Column field="ip_address" header="IP Address" />
                    <Column field="user_agent" header="User Agent" style="min-width: 12rem" />
                    <Column header="Last Activity">
                        <template #body="{ data }">
                            {{ getTime(data.last_activity) }}
                        </template>
                    </Column>
                    <Column header="Actions" headerStyle="width:5rem">
                        <template #body="{ data }">
                            <Button
                                v-tooltip.left="'View payload'"
                                icon="pi pi-eye"
                                size="small"
                                severity="secondary"
                                text
                                rounded
                                @click="selectedItem = data"
                            />
                        </template>
                    </Column>
                </DataTable>

                <EmptyState
                    v-else
                    icon="PhDevices"
                    title="No sessions found"
                    description="Reload to fetch current session records"
                />
            </PageCard>
        </div>

        <ConfirmDialog />
        <Dialog
            v-model:visible="selectedItem"
            header="Session Payload"
            modal
            maximizable
            :style="{ width: '40rem' }"
            dismissableMask
        >
            <pre class="max-h-96 overflow-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">{{ selectedItem?.decoded_payload }}</pre>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { ref, onMounted } from "vue";
import axios from "axios";
import { format } from "date-fns";
import { useConfirm } from "primevue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";

defineOptions({
    name: "Sessions",
});

const confirm = useConfirm();
const selectedItem = ref();
const isLoading = ref(false);
const sessions = ref<any[]>([]);
const clearing = ref(false);

const getTime = (dateString: number) => {
    try {
        return format(new Date(dateString * 1000), "dd MMM yyyy, hh:mm a");
    } catch {
        return "";
    }
};

const getSessions = async () => {
    isLoading.value = true;
    try {
        const { data } = await axios.get(route("sessions.getSessions"));
        sessions.value = data || [];
    } catch (error) {
        console.error(error);
    } finally {
        isLoading.value = false;
    }
};

const deleteSession = () => {
    confirm.require({
        header: "Clear expired sessions?",
        message: "This action cannot be undone.",
        rejectProps: { label: "Cancel", severity: "secondary", size: "small" },
        acceptProps: { label: "Clear", severity: "danger", size: "small" },
        accept: async () => {
            clearing.value = true;
            try {
                await axios.post(route("sessions.clearSession"));
                await getSessions();
            } finally {
                clearing.value = false;
            }
        },
    });
};

const deleteAllSession = () => {
    confirm.require({
        header: "Clear all sessions?",
        message: "This action cannot be undone.",
        rejectProps: { label: "Cancel", severity: "secondary", size: "small" },
        acceptProps: { label: "Clear All", severity: "danger", size: "small" },
        accept: async () => {
            clearing.value = true;
            try {
                await axios.post(route("sessions.clearAllSession"));
                await getSessions();
            } finally {
                clearing.value = false;
            }
        },
    });
};

onMounted(getSessions);
</script>
