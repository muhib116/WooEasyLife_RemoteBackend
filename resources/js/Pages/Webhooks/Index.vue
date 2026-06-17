<template>
    <AuthenticatedLayout title="Webhook Activities">
        <div class="space-y-5">
            <PageHeader
                title="Webhook Activities"
                description="Monitor courier webhook events, forward status, and retry queue"
                icon="PhArrowClockwise"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <Button
                        label="Process Due Retries"
                        icon="pi pi-play"
                        severity="help"
                        size="small"
                        :loading="processingRetries"
                        @click="handleProcessRetries"
                    />
                    <Button
                        label="Reload"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        size="small"
                        :loading="loading"
                        @click="reloadAll"
                    />
                </template>
            </PageHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Total Events"
                    :value="summary.total_events"
                    icon="PhListBullets"
                    :subtitle="summary.last_event_at ? `Last: ${formatDate(summary.last_event_at)}` : 'No events yet'"
                />
                <StatCard
                    title="Forwarded"
                    :value="summary.success_count"
                    icon="PhCheckCircle"
                    subtitle="Successful forwards"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <StatCard
                    title="Pending Retries"
                    :value="summary.pending_retries"
                    icon="PhClock"
                    :subtitle="`${summary.retry_queued_count} queued events`"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
                <StatCard
                    title="Failed / Orphan"
                    :value="summary.failed_count + summary.orphan_count"
                    icon="PhWarningCircle"
                    :subtitle="`${summary.failed_retries} failed retries`"
                    accent-class="bg-rose-500"
                    icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                    icon-class="text-rose-600 dark:text-rose-400"
                />
            </div>

            <PageCard
                title="Recent Webhook Events"
                description="Inbound courier webhooks and WordPress forward results"
                no-padding
            >
                <div class="flex flex-wrap gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <Dropdown
                        v-model="eventFilters.partner"
                        :options="partnerOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="All partners"
                        class="w-[160px]"
                        showClear
                        @change="fetchEvents(1)"
                    />
                    <Dropdown
                        v-model="eventFilters.environment"
                        :options="environmentOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="All environments"
                        class="w-[160px]"
                        showClear
                        @change="fetchEvents(1)"
                    />
                    <Dropdown
                        v-model="eventFilters.forward_status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="All statuses"
                        class="w-[180px]"
                        showClear
                        @change="fetchEvents(1)"
                    />
                    <InputText
                        v-model="eventFilters.search"
                        placeholder="Search consignment, site, order..."
                        class="w-[240px]"
                        @keyup.enter="fetchEvents(1)"
                    />
                </div>

                <DataTable
                    v-if="loadingEvents"
                    :value="new Array(6)"
                    class="professional-table text-sm"
                >
                    <Column header="SL"><template #body><Skeleton /></template></Column>
                    <Column header="Partner"><template #body><Skeleton /></template></Column>
                    <Column header="Status"><template #body><Skeleton /></template></Column>
                    <Column header="Details"><template #body><Skeleton /></template></Column>
                </DataTable>

                <DataTable
                    v-else-if="events.data.length"
                    :value="events.data"
                    :rows="events.per_page"
                    :totalRecords="events.total"
                    :first="(events.current_page - 1) * events.per_page"
                    lazy
                    paginator
                    class="professional-table text-sm"
                    @page="onEventPage"
                >
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ (events.current_page - 1) * events.per_page + slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column header="Received" style="min-width: 9rem">
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>
                    <Column header="Partner" style="min-width: 7rem">
                        <template #body="{ data }">
                            <div class="font-medium capitalize">{{ data.partner }}</div>
                            <div class="text-xs text-gray-500">{{ data.environment }}</div>
                        </template>
                    </Column>
                    <Column header="Shipment" style="min-width: 10rem">
                        <template #body="{ data }">
                            <div>{{ data.consignment_id || "—" }}</div>
                            <div class="text-xs text-gray-500">
                                Order #{{ data.wc_order_id || "—" }}
                            </div>
                        </template>
                    </Column>
                    <Column header="Site" style="min-width: 12rem">
                        <template #body="{ data }">
                            <span class="break-all text-xs">{{ data.site_url || "—" }}</span>
                        </template>
                    </Column>
                    <Column header="Event" field="event_type" style="min-width: 8rem" />
                    <Column header="Forward Status" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Tag
                                :value="data.forward_status"
                                :severity="statusSeverity(data.forward_status)"
                            />
                        </template>
                    </Column>
                    <Column header="Message" style="min-width: 10rem">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600 dark:text-gray-300">
                                {{ data.forward_message || "—" }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Action" headerStyle="width:11rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Button
                                    v-if="canTestPlugin(data)"
                                    label="Test"
                                    icon="pi pi-link"
                                    size="small"
                                    text
                                    :loading="testingEventId === data.id"
                                    @click="handleTestPlugin(data.id)"
                                />
                                <Button
                                    v-if="canRetryEvent(data)"
                                    label="Retry"
                                    icon="pi pi-replay"
                                    size="small"
                                    text
                                    :loading="retryingEventId === data.id"
                                    @click="handleRetryEvent(data.id)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <EmptyState
                    v-else
                    icon="PhArrowClockwise"
                    title="No webhook events"
                    description="Events will appear here when couriers send status updates"
                />
            </PageCard>

            <PageCard
                title="Forward Retry Queue"
                description="Pending and failed WordPress forward attempts"
                no-padding
            >
                <DataTable
                    v-if="loadingRetries"
                    :value="new Array(4)"
                    class="professional-table text-sm"
                >
                    <Column header="SL"><template #body><Skeleton /></template></Column>
                    <Column header="Partner"><template #body><Skeleton /></template></Column>
                    <Column header="Status"><template #body><Skeleton /></template></Column>
                </DataTable>

                <DataTable
                    v-else-if="retries.data.length"
                    :value="retries.data"
                    :rows="retries.per_page"
                    :totalRecords="retries.total"
                    :first="(retries.current_page - 1) * retries.per_page"
                    lazy
                    paginator
                    class="professional-table text-sm"
                    @page="onRetryPage"
                >
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ (retries.current_page - 1) * retries.per_page + slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column header="Partner" style="min-width: 7rem">
                        <template #body="{ data }">
                            <span class="capitalize">{{ data.partner || "—" }}</span>
                        </template>
                    </Column>
                    <Column header="Consignment" field="consignment_id" style="min-width: 9rem" />
                    <Column header="Site" style="min-width: 12rem">
                        <template #body="{ data }">
                            <span class="break-all text-xs">{{ data.site_url || "—" }}</span>
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 7rem">
                        <template #body="{ data }">
                            <Tag :value="data.status" :severity="retrySeverity(data.status)" />
                        </template>
                    </Column>
                    <Column header="Attempts" style="min-width: 6rem">
                        <template #body="{ data }">
                            {{ data.attempts }} / {{ data.max_attempts }}
                        </template>
                    </Column>
                    <Column header="Next Retry" style="min-width: 9rem">
                        <template #body="{ data }">
                            {{ data.next_retry_at ? formatDate(data.next_retry_at) : "—" }}
                        </template>
                    </Column>
                    <Column header="Last Error" style="min-width: 10rem">
                        <template #body="{ data }">
                            <span class="text-xs text-rose-600 dark:text-rose-400">
                                {{ data.last_error || "—" }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Action" headerStyle="width:11rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Button
                                    label="Test"
                                    icon="pi pi-link"
                                    size="small"
                                    text
                                    :loading="testingRetryId === data.id"
                                    @click="handleTestRetryPlugin(data.id, data.event_id)"
                                />
                                <Button
                                    v-if="data.status !== 'completed'"
                                    label="Retry Now"
                                    icon="pi pi-replay"
                                    size="small"
                                    text
                                    :loading="retryingRetryId === data.id"
                                    @click="handleRetryForward(data.id)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <EmptyState
                    v-else
                    icon="PhCheckCircle"
                    title="No pending retries"
                    description="All webhook forwards are up to date"
                />
            </PageCard>
        </div>

        <Dialog
            v-model:visible="testDialogVisible"
            modal
            header="Plugin Reachability Test"
            :style="{ width: '34rem' }"
        >
            <div v-if="testResult" class="space-y-4 text-sm">
                <div
                    class="rounded-xl border px-4 py-3"
                    :class="
                        testResult.success
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200'
                            : 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200'
                    "
                >
                    <p class="font-semibold">
                        {{ testResult.success ? "Plugin reachable" : "Plugin not reachable" }}
                    </p>
                    <p class="mt-1">{{ testResult.message }}</p>
                    <p v-if="testResult.result?.site_url" class="mt-2 break-all text-xs opacity-80">
                        Site: {{ testResult.result.site_url }}
                    </p>
                </div>

                <div class="space-y-3">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="font-medium text-gray-800 dark:text-gray-100">
                                License Endpoint
                            </p>
                            <Tag
                                :value="testResult.result?.license_probe?.success ? 'OK' : 'Failed'"
                                :severity="testResult.result?.license_probe?.success ? 'success' : 'danger'"
                            />
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ testResult.result?.license_probe?.detail || "—" }}
                        </p>
                        <p class="mt-2 break-all text-xs text-gray-500 dark:text-gray-400">
                            {{ testResult.result?.license_probe?.url || "—" }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="font-medium text-gray-800 dark:text-gray-100">
                                Courier Status Hook
                            </p>
                            <Tag
                                :value="testResult.result?.forward_probe?.success ? 'OK' : 'Failed'"
                                :severity="testResult.result?.forward_probe?.success ? 'success' : 'danger'"
                            />
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ testResult.result?.forward_probe?.detail || "—" }}
                        </p>
                        <p class="mt-2 break-all text-xs text-gray-500 dark:text-gray-400">
                            {{ testResult.result?.forward_probe?.url || "—" }}
                        </p>
                    </div>
                </div>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { onMounted, reactive, ref } from "vue";
import axios from "axios";
import { format } from "date-fns";
import { useToast } from "primevue/usetoast";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";

defineOptions({
    name: "WebhookActivities",
});

type Paginated<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
};

const toast = useToast();

const loading = ref(false);
const loadingEvents = ref(false);
const loadingRetries = ref(false);
const processingRetries = ref(false);
const retryingEventId = ref<number | null>(null);
const retryingRetryId = ref<number | null>(null);
const testingEventId = ref<number | null>(null);
const testingRetryId = ref<number | null>(null);
const testDialogVisible = ref(false);
const testResult = ref<{
    success: boolean;
    message: string;
    result?: {
        site_url?: string | null;
        license_probe?: {
            success?: boolean;
            detail?: string;
            url?: string | null;
        };
        forward_probe?: {
            success?: boolean;
            detail?: string;
            url?: string | null;
        };
    };
} | null>(null);

const summary = reactive({
    total_events: 0,
    success_count: 0,
    failed_count: 0,
    retry_queued_count: 0,
    orphan_count: 0,
    pending_retries: 0,
    failed_retries: 0,
    last_event_at: null as string | null,
});

const events = ref<Paginated<any>>({
    data: [],
    current_page: 1,
    per_page: 20,
    total: 0,
});

const retries = ref<Paginated<any>>({
    data: [],
    current_page: 1,
    per_page: 20,
    total: 0,
});

const eventFilters = reactive({
    partner: null as string | null,
    environment: null as string | null,
    forward_status: null as string | null,
    search: "",
});

const partnerOptions = [
    { label: "Steadfast", value: "steadfast" },
    { label: "Pathao", value: "pathao" },
    { label: "RedX", value: "redx" },
];

const environmentOptions = [
    { label: "Live", value: "live" },
    { label: "Sandbox", value: "sandbox" },
];

const statusOptions = [
    { label: "Received", value: "received" },
    { label: "Success", value: "success" },
    { label: "Retry Queued", value: "retry_queued" },
    { label: "Failed", value: "failed" },
    { label: "Orphan", value: "orphan" },
];

const formatDate = (value?: string | null) => {
    if (!value) return "—";

    try {
        return format(new Date(value), "dd MMM yyyy, hh:mm a");
    } catch {
        return value;
    }
};

const statusSeverity = (status: string) => {
    switch (status) {
        case "success":
            return "success";
        case "retry_queued":
            return "warn";
        case "failed":
        case "orphan":
            return "danger";
        default:
            return "secondary";
    }
};

const retrySeverity = (status: string) => {
    switch (status) {
        case "completed":
            return "success";
        case "pending":
            return "warn";
        case "failed":
            return "danger";
        default:
            return "secondary";
    }
};

const canRetryEvent = (event: { forward_status: string; wc_order_id?: number | null }) => {
    return ["retry_queued", "failed"].includes(event.forward_status) && Boolean(event.wc_order_id);
};

const canTestPlugin = (event: { site_url?: string | null; wc_order_id?: number | null }) => {
    return Boolean(event.site_url || event.wc_order_id);
};

const fetchSummary = async () => {
    const { data } = await axios.get(route("webhooks.summary"));
    Object.assign(summary, data);
};

const fetchEvents = async (page = events.value.current_page) => {
    loadingEvents.value = true;

    try {
        const { data } = await axios.get(route("webhooks.events"), {
            params: {
                page,
                partner: eventFilters.partner,
                environment: eventFilters.environment,
                forward_status: eventFilters.forward_status,
                search: eventFilters.search || undefined,
            },
        });
        events.value = data;
    } catch (error) {
        console.error("Error fetching webhook events:", error);
        events.value = { data: [], current_page: 1, per_page: 20, total: 0 };
    } finally {
        loadingEvents.value = false;
    }
};

const fetchRetries = async (page = retries.value.current_page) => {
    loadingRetries.value = true;

    try {
        const { data } = await axios.get(route("webhooks.retries"), { params: { page } });
        retries.value = data;
    } catch (error) {
        console.error("Error fetching webhook retries:", error);
        retries.value = { data: [], current_page: 1, per_page: 20, total: 0 };
    } finally {
        loadingRetries.value = false;
    }
};

const reloadAll = async () => {
    loading.value = true;

    try {
        await Promise.all([fetchSummary(), fetchEvents(), fetchRetries()]);
    } finally {
        loading.value = false;
    }
};

const onEventPage = (event: { page: number }) => {
    fetchEvents(event.page + 1);
};

const onRetryPage = (event: { page: number }) => {
    fetchRetries(event.page + 1);
};

const handleProcessRetries = async () => {
    processingRetries.value = true;

    try {
        const { data } = await axios.post(route("webhooks.processRetries"));
        toast.add({
            severity: "success",
            summary: "Retries processed",
            detail: `Processed ${data.result.processed}, succeeded ${data.result.succeeded}, failed ${data.result.failed}`,
            life: 4000,
        });
        await reloadAll();
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Retry processing failed",
            detail: error?.response?.data?.message || "Unable to process retries",
            life: 4000,
        });
    } finally {
        processingRetries.value = false;
    }
};

const handleRetryEvent = async (eventId: number) => {
    retryingEventId.value = eventId;

    try {
        const { data } = await axios.post(route("webhooks.retryEvent", eventId));
        toast.add({
            severity: data.success ? "success" : "warn",
            summary: data.success ? "Forward succeeded" : "Forward failed",
            detail: data.message,
            life: 4000,
        });
        await reloadAll();
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Retry failed",
            detail: error?.response?.data?.message || "Unable to retry event",
            life: 4000,
        });
    } finally {
        retryingEventId.value = null;
    }
};

const handleRetryForward = async (retryId: number) => {
    retryingRetryId.value = retryId;

    try {
        const { data } = await axios.post(route("webhooks.retryForward", retryId));
        toast.add({
            severity: data.success ? "success" : "warn",
            summary: data.success ? "Forward succeeded" : "Forward failed",
            detail: data.message,
            life: 4000,
        });
        await reloadAll();
    } catch (error: any) {
        toast.add({
            severity: "error",
            summary: "Retry failed",
            detail: error?.response?.data?.message || "Unable to retry forward",
            life: 4000,
        });
    } finally {
        retryingRetryId.value = null;
    }
};

const runPluginTest = async (eventId: number) => {
    const { data } = await axios.post(route("webhooks.testPlugin", eventId));
    testResult.value = data;
    testDialogVisible.value = true;

    toast.add({
        severity: data.success ? "success" : "warn",
        summary: data.success ? "Plugin reachable" : "Plugin unreachable",
        detail: data.message,
        life: 4000,
    });
};

const handleTestPlugin = async (eventId: number) => {
    testingEventId.value = eventId;

    try {
        await runPluginTest(eventId);
    } catch (error: any) {
        const responseData = error?.response?.data;
        if (responseData?.result) {
            testResult.value = responseData;
            testDialogVisible.value = true;
        }

        toast.add({
            severity: "error",
            summary: "Plugin test failed",
            detail: responseData?.message || "Unable to test plugin reachability",
            life: 4000,
        });
    } finally {
        testingEventId.value = null;
    }
};

const handleTestRetryPlugin = async (retryId: number, eventId?: number | null) => {
    if (!eventId) {
        toast.add({
            severity: "warn",
            summary: "No linked event",
            detail: "This retry row has no webhook event to test against.",
            life: 4000,
        });
        return;
    }

    testingRetryId.value = retryId;

    try {
        await runPluginTest(eventId);
    } catch (error: any) {
        const responseData = error?.response?.data;
        if (responseData?.result) {
            testResult.value = responseData;
            testDialogVisible.value = true;
        }

        toast.add({
            severity: "error",
            summary: "Plugin test failed",
            detail: responseData?.message || "Unable to test plugin reachability",
            life: 4000,
        });
    } finally {
        testingRetryId.value = null;
    }
};

onMounted(() => {
    reloadAll();
});
</script>
