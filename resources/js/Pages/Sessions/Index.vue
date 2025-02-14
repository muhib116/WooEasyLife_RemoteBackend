<template>
    <AuthenticatedLayout title="Log Viewer">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between gap-5">
                    Sessions

                    <div class="flex items-center gap-5">
                        <Button
                            label="Delete All Session"
                            severity="danger"
                            size="small"
                            icon="pi pi-times-circle"
                            @click="deleteAllSession"
                            :loading="clearing"
                        />
                        <Button
                            label="Delete expired session"
                            severity="warn"
                            size="small"
                            icon="pi pi-times-circle"
                            @click="deleteSession"
                            :loading="clearing"
                        />
                        <Button
                            label="Reload"
                            size="small"
                            icon="pi pi-refresh"
                            @click="getSessions"
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
                        v-else-if="sessions.length"
                        :value="sessions"
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
                        <Column field="id" header="Id" />
                        <Column field="user_id" header="User Id" />
                        <Column field="ip_address" header="Ip Address" />
                        <Column field="user_agent" header="User Agent" />
                        <!-- <Column field="payload" header="Payload" /> -->
                        <Column field="last_activity" header="Last Activity">
                            <template #body="{ data }">
                                {{ getTime(data.last_activity) }}
                            </template>
                        </Column>
                        <!-- id
                        user_id
                        ip_address
                        user_agent
                        payload
                        last_activity -->
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
import { ref, onMounted, nextTick } from "vue";
import axios from "axios";
import { format, parse } from "date-fns";
import { router } from "@inertiajs/core";
import { useConfirm } from "primevue";

defineOptions({
    name: "Sessions",
});

const props = defineProps({
    im_super: Boolean,
});

const confirm = useConfirm();

const selectedItem = ref();

const op = ref();

const isLoading = ref(false);
const logFiles = ref<{ name: string; path: string }[]>([]);
const selectedLogFile = ref<string | null>(null);
const sessions = ref<any[]>([]);
const clearing = ref(false);

const getTime = (dateString) => {
    // Parse the date string for 1739376048 record
    let op = "";
    try {
        // Format the date in a more readable format
        op = format(new Date(dateString * 1000), "dd MMM yyyy, hh:mm a");
    } catch (error) {}
    return op;
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
            getSessions();
        },
    });
};
const deleteAllSession = async () => {
    confirm.require({
        header: "Are you sure to delete all?",
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
            await axios.post(route("sessions.clearAllSession"));
            getSessions();
        },
    });
};

onMounted(async () => {
    await getSessions();
});
</script>
