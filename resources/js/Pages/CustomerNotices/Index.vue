<template>
    <AuthenticatedLayout title="Customer Notices">
        <div class="space-y-5">
            <PageHeader
                title="Customer Notices"
                description="Publish offers, maintenance and feature notices to merchants, targeted by subscription status"
                icon="PhMegaphone"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <Button
                        label="Add Notice"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                    />
                </template>
            </PageHeader>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard title="Total" :value="notices.length" icon="PhMegaphone" />
                <StatCard
                    title="Live"
                    :value="liveCount"
                    icon="PhBroadcast"
                    subtitle="Currently visible to merchants"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <StatCard
                    title="Scheduled"
                    :value="scheduledCount"
                    icon="PhClock"
                    subtitle="Waiting to start"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
                <StatCard
                    title="Inactive"
                    :value="inactiveCount"
                    icon="PhProhibit"
                    subtitle="Disabled notices"
                    accent-class="bg-rose-500"
                    icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                    icon-class="text-rose-600 dark:text-rose-400"
                />
            </div>

            <PageCard
                title="Notice List"
                :description="`${notices.length} notice${notices.length === 1 ? '' : 's'} configured`"
                no-padding
            >
                <DataTable
                    v-if="notices.length"
                    :value="notices"
                    paginator
                    :rows="10"
                    :rowsPerPageOptions="[10, 25, 50]"
                    paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                    currentPageReportTemplate="{first}–{last} of {totalRecords}"
                    class="professional-table text-sm"
                >
                    <Column header="SL" headerStyle="width:3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column field="title" header="Notice" style="min-width: 16rem">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-1">
                                <span class="font-medium">{{ data.title }}</span>
                                <span class="line-clamp-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ data.body }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Type" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Tag :value="typeLabels[data.type] ?? data.type" severity="secondary" />
                        </template>
                    </Column>
                    <Column header="Audience" style="min-width: 10rem">
                        <template #body="{ data }">
                            <span class="text-sm text-gray-700 dark:text-gray-200">
                                {{ audienceLabels[data.audience] ?? data.audience }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Severity" style="min-width: 7rem">
                        <template #body="{ data }">
                            <Tag
                                :value="data.severity"
                                :severity="severityTag(data.severity)"
                            />
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 7rem">
                        <template #body="{ data }">
                            <Tag :value="statusOf(data)" :severity="statusTag(data)" />
                        </template>
                    </Column>
                    <Column header="Actions" header-class="text-right" headerStyle="width:9rem">
                        <template #body="{ data }">
                            <TableActions>
                                <TableActionButton
                                    action="edit"
                                    tooltip="Edit notice"
                                    @click="openEdit(data)"
                                />
                                <TableActionButton
                                    action="delete"
                                    tooltip="Delete notice"
                                    @click="confirmRemove(data)"
                                />
                            </TableActions>
                        </template>
                    </Column>
                </DataTable>

                <EmptyState
                    v-else
                    icon="PhMegaphone"
                    title="No notices yet"
                    description="Create a notice to broadcast offers, maintenance windows or feature releases to your merchants"
                >
                    <Button
                        label="Add Notice"
                        icon="pi pi-plus"
                        size="small"
                        class="mt-4"
                        @click="openCreate"
                    />
                </EmptyState>
            </PageCard>
        </div>

        <AdminDialog
            v-model:visible="showForm"
            :header="editing ? 'Edit Notice' : 'Add Notice'"
            :style="{ width: '40rem' }"
            @hide="resetForm"
        >
            <form class="space-y-5 p-1" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Title
                    </label>
                    <InputText v-model="form.title" class="w-full" placeholder="e.g. Eid renewal offer" />
                    <small v-if="form.errors.title" class="mt-1 block text-rose-500">
                        {{ form.errors.title }}
                    </small>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Message
                    </label>
                    <Textarea v-model="form.body" class="w-full" rows="4" placeholder="Notice details shown to merchants" />
                    <small v-if="form.errors.body" class="mt-1 block text-rose-500">
                        {{ form.errors.body }}
                    </small>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Type
                        </label>
                        <Select
                            v-model="form.type"
                            :options="typeOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Severity
                        </label>
                        <Select
                            v-model="form.severity"
                            :options="severityOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Priority
                        </label>
                        <InputNumber v-model="form.priority" :min="0" :max="1000" class="w-full" />
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Audience
                    </label>
                    <Select
                        v-model="form.audience"
                        :options="audienceOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                    <small class="mt-1 block text-gray-500 dark:text-gray-400">
                        {{ audienceHints[form.audience] }}
                    </small>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Button label (optional)
                        </label>
                        <InputText v-model="form.cta_label" class="w-full" placeholder="e.g. Renew now" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Button URL (optional)
                        </label>
                        <InputText v-model="form.cta_url" class="w-full" placeholder="https://..." />
                        <small v-if="form.errors.cta_url" class="mt-1 block text-rose-500">
                            {{ form.errors.cta_url }}
                        </small>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Start at (optional)
                        </label>
                        <DatePicker
                            v-model="form.starts_at"
                            show-time
                            hour-format="24"
                            date-format="yy-mm-dd"
                            placeholder="Immediately"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            End at (optional)
                        </label>
                        <DatePicker
                            v-model="form.ends_at"
                            show-time
                            hour-format="24"
                            date-format="yy-mm-dd"
                            placeholder="No end date"
                            class="w-full"
                        />
                        <small v-if="form.errors.ends_at" class="mt-1 block text-rose-500">
                            {{ form.errors.ends_at }}
                        </small>
                    </div>
                </div>

                <div class="flex flex-wrap gap-6 pt-1">
                    <div class="flex items-center gap-2">
                        <ToggleSwitch v-model="form.is_active" inputId="is_active" />
                        <label for="is_active" class="text-sm text-gray-700 dark:text-gray-200">Active</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <ToggleSwitch v-model="form.is_dismissible" inputId="is_dismissible" />
                        <label for="is_dismissible" class="text-sm text-gray-700 dark:text-gray-200">
                            Dismissible
                        </label>
                    </div>
                </div>
            </form>

            <template #footer>
                <div class="mt-3 flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="showForm = false" />
                    <Button
                        label="Save"
                        icon="pi pi-check"
                        severity="info"
                        :loading="form.processing"
                        @click="submit"
                    />
                </div>
            </template>
        </AdminDialog>

        <ConfirmDialog />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useConfirm } from "primevue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import AdminDialog from "@/Pages/Users/fragments/AdminDialog.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";

defineOptions({ name: "CustomerNoticesIndex" });

type Notice = {
    id: number;
    title: string;
    body: string;
    type: string;
    severity: string;
    audience: string;
    cta_label: string | null;
    cta_url: string | null;
    is_dismissible: boolean;
    is_active: boolean;
    priority: number;
    starts_at: string | null;
    ends_at: string | null;
};

const props = defineProps<{
    notices: Notice[];
    options: {
        types: string[];
        severities: string[];
        audiences: string[];
    };
}>();

const confirm = useConfirm();
const notices = computed(() => props.notices);

const typeLabels: Record<string, string> = {
    offer: "Offer",
    maintenance: "Maintenance",
    feature: "Feature",
    general: "General",
};

const audienceLabels: Record<string, string> = {
    all: "All merchants",
    active_subscribers: "Active subscribers",
    expiring_soon: "Expiring soon",
    recent_expired: "Recently expired",
    not_renewed: "Not renewed",
};

const audienceHints: Record<string, string> = {
    all: "Shown to every merchant.",
    active_subscribers: "Merchants with a valid, non-expired plan.",
    expiring_soon: "Merchants whose subscription expires within the reminder window.",
    recent_expired: "Merchants whose subscription expired recently.",
    not_renewed: "Expired merchants with no pending renewal payment.",
};

const typeOptions = computed(() =>
    props.options.types.map((value) => ({ value, label: typeLabels[value] ?? value })),
);
const severityOptions = computed(() =>
    props.options.severities.map((value) => ({ value, label: value })),
);
const audienceOptions = computed(() =>
    props.options.audiences.map((value) => ({ value, label: audienceLabels[value] ?? value })),
);

const severityTag = (severity: string) => {
    switch (severity) {
        case "danger":
            return "danger";
        case "warning":
            return "warn";
        case "success":
            return "success";
        default:
            return "info";
    }
};

const isLive = (n: Notice) => {
    if (!n.is_active) return false;
    const now = Date.now();
    if (n.starts_at && new Date(n.starts_at).getTime() > now) return false;
    if (n.ends_at && new Date(n.ends_at).getTime() < now) return false;
    return true;
};

const isScheduled = (n: Notice) =>
    n.is_active && !!n.starts_at && new Date(n.starts_at).getTime() > Date.now();

const statusOf = (n: Notice) => {
    if (!n.is_active) return "Inactive";
    if (isScheduled(n)) return "Scheduled";
    if (isLive(n)) return "Live";
    return "Expired";
};

const statusTag = (n: Notice) => {
    const status = statusOf(n);
    if (status === "Live") return "success";
    if (status === "Scheduled") return "warn";
    return "secondary";
};

const liveCount = computed(() => notices.value.filter(isLive).length);
const scheduledCount = computed(() => notices.value.filter(isScheduled).length);
const inactiveCount = computed(() => notices.value.filter((n) => !n.is_active).length);

const showForm = ref(false);
const editing = ref<{ id: number } | null>(null);

const form = useForm<{
    title: string;
    body: string;
    type: string;
    severity: string;
    audience: string;
    cta_label: string;
    cta_url: string;
    priority: number;
    starts_at: Date | null;
    ends_at: Date | null;
    is_active: boolean;
    is_dismissible: boolean;
}>({
    title: "",
    body: "",
    type: "general",
    severity: "info",
    audience: "all",
    cta_label: "",
    cta_url: "",
    priority: 0,
    starts_at: null,
    ends_at: null,
    is_active: true,
    is_dismissible: true,
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    editing.value = null;
};

const openCreate = () => {
    resetForm();
    showForm.value = true;
};

const openEdit = (item: Notice) => {
    editing.value = { id: item.id };
    form.title = item.title;
    form.body = item.body;
    form.type = item.type;
    form.severity = item.severity;
    form.audience = item.audience;
    form.cta_label = item.cta_label ?? "";
    form.cta_url = item.cta_url ?? "";
    form.priority = item.priority ?? 0;
    form.starts_at = item.starts_at ? new Date(item.starts_at) : null;
    form.ends_at = item.ends_at ? new Date(item.ends_at) : null;
    form.is_active = item.is_active;
    form.is_dismissible = item.is_dismissible;
    form.clearErrors();
    showForm.value = true;
};

const submit = () => {
    const onSuccess = () => {
        showForm.value = false;
        resetForm();
    };

    if (editing.value) {
        form.put(route("customerNotices.update", editing.value.id), { onSuccess });
        return;
    }

    form.post(route("customerNotices.store"), { onSuccess });
};

const confirmRemove = (item: Notice) => {
    confirm.require({
        header: "Delete notice?",
        message: `Delete "${item.title}"? Merchants will stop seeing it immediately.`,
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Cancel",
        acceptLabel: "Delete",
        acceptClass: "p-button-danger",
        accept: () => {
            useForm({}).delete(route("customerNotices.destroy", item.id));
        },
    });
};
</script>
