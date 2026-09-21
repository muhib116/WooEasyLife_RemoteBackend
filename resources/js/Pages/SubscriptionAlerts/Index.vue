<template>
    <AuthenticatedLayout title="Subscription Alerts">
        <div class="space-y-4 sm:space-y-5">
            <PageHeader
                title="Subscription Alerts"
                description="Merchants with low quota, expiring licenses, or pending renewals"
                icon="PhBellRinging"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <div
                v-if="notifications_enabled"
                class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-relaxed text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100"
            >
                Daily merchant notifications run at 08:05
                <template v-if="enabledChannelLabels.length">
                    via {{ enabledChannelLabels.join(", ") }}.
                </template>
                <template v-else>
                    (no outbound channels enabled).
                </template>
                <span v-if="smsExpiryCopy">
                    {{ smsExpiryCopy }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <button
                    v-for="stat in summaryTiles"
                    :key="stat.value"
                    type="button"
                    class="box-bg box-color box-border rounded-2xl border px-4 py-3 text-left transition hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    :class="
                        activeSeverity === stat.value
                            ? stat.activeClass
                            : 'border-gray-200 dark:border-gray-800'
                    "
                    @click="setSeverity(stat.value)"
                >
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ stat.label }}
                    </p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                        {{ stat.count }}
                    </p>
                </button>
            </div>

            <div
                class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                role="tablist"
                aria-label="Filter alerts by severity"
            >
                <button
                    v-for="option in severityOptions"
                    :key="option.value"
                    type="button"
                    role="tab"
                    class="inline-flex shrink-0 items-center rounded-full border px-3.5 py-2 text-sm font-medium transition"
                    :class="
                        activeSeverity === option.value
                            ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-400 dark:bg-primary-500/15 dark:text-primary-200'
                            : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-300 dark:hover:bg-slate-800'
                    "
                    :aria-selected="activeSeverity === option.value"
                    @click="setSeverity(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <PageCard
                title="Active Alerts"
                :description="`${alerts.length} shown`"
                no-padding
            >
                <EmptyState
                    v-if="!alerts.length"
                    class="m-5 md:m-6"
                    title="No alerts in this filter"
                    description="Try another severity, or check back after the next daily scan."
                    icon="PhBellRinging"
                />

                <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li
                        v-for="(alert, index) in pagedAlerts"
                        :key="`${alert.user_id}-${alert.type}-${alert.domain}-${index}`"
                        class="px-4 py-4 sm:px-6"
                    >
                        <div
                            class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <StatusBadge
                                        :label="alert.severity"
                                        :variant="severityVariant(alert.severity)"
                                        format="severity"
                                    />
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        {{ typeLabel(alert.type) }}
                                    </span>
                                </div>

                                <div class="min-w-0">
                                    <Link
                                        :href="route('users.view', alert.user_id)"
                                        class="break-words font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ alert.user_name }}
                                    </Link>
                                    <p
                                        v-if="alert.domain"
                                        class="mt-0.5 break-all text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ alert.domain }}
                                    </p>
                                </div>

                                <p
                                    class="text-sm leading-relaxed text-gray-700 break-words dark:text-gray-300"
                                >
                                    {{ alert.message }}
                                </p>

                                <p
                                    v-if="alert.notification_channels?.length"
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Notified via
                                    {{ alert.notification_channels.join(", ") }}
                                </p>
                            </div>

                            <div
                                class="grid grid-cols-2 gap-2 lg:w-36 lg:shrink-0 lg:grid-cols-1"
                            >
                                <Link
                                    :href="route('users.websites', alert.user_id)"
                                    class="inline-flex items-center justify-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-slate-800"
                                >
                                    Websites
                                    <Icon name="PhArrowRight" class="text-sm" />
                                </Link>
                                <Link
                                    :href="route('users.billing', alert.user_id)"
                                    class="inline-flex items-center justify-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-slate-800"
                                >
                                    Billing
                                    <Icon name="PhArrowRight" class="text-sm" />
                                </Link>
                            </div>
                        </div>
                    </li>
                </ul>

                <div
                    v-if="alerts.length > pageSize"
                    class="flex items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-6"
                >
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ pageStart }}–{{ pageEnd }} of {{ alerts.length }}
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-40 dark:border-gray-700 dark:text-gray-200"
                            :disabled="page === 1"
                            @click="page -= 1"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 disabled:opacity-40 dark:border-gray-700 dark:text-gray-200"
                            :disabled="page >= pageCount"
                            @click="page += 1"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/components/PageCard.vue";
import StatusBadge from "@/components/StatusBadge.vue";
import Icon from "@/components/Icon.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import { Link, router } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

type AlertRow = {
    type: string;
    severity: string;
    message: string;
    user_id: number;
    user_name: string;
    domain: string;
    notification_channels?: string[];
};

type Summary = {
    total: number;
    danger: number;
    warning: number;
    info: number;
};

const props = defineProps<{
    alerts: AlertRow[];
    summary: Summary;
    severity: string;
    notifications_enabled?: boolean;
    notification_channels?: {
        email: boolean;
        sms: boolean;
        whatsapp: boolean;
    };
    sms_expiry_days?: number[];
}>();

const activeSeverity = ref(props.severity || "all");
const page = ref(1);
const pageSize = 15;

const severityOptions = [
    { label: "All", value: "all" },
    { label: "Critical", value: "danger" },
    { label: "Warning", value: "warning" },
    { label: "Info", value: "info" },
];

const summaryTiles = computed(() => [
    {
        label: "Total",
        value: "all",
        count: props.summary.total,
        activeClass:
            "border-primary-300 ring-1 ring-primary-200 dark:border-primary-500/50 dark:ring-primary-500/30",
    },
    {
        label: "Critical",
        value: "danger",
        count: props.summary.danger,
        activeClass:
            "border-rose-300 ring-1 ring-rose-200 dark:border-rose-500/50 dark:ring-rose-500/30",
    },
    {
        label: "Warnings",
        value: "warning",
        count: props.summary.warning,
        activeClass:
            "border-amber-300 ring-1 ring-amber-200 dark:border-amber-500/50 dark:ring-amber-500/30",
    },
    {
        label: "Info",
        value: "info",
        count: props.summary.info,
        activeClass:
            "border-sky-300 ring-1 ring-sky-200 dark:border-sky-500/50 dark:ring-sky-500/30",
    },
]);

const enabledChannelLabels = computed(() => {
    const channels = props.notification_channels ?? {
        email: false,
        sms: false,
        whatsapp: false,
    };
    const labels: string[] = [];

    if (channels.email) {
        labels.push("email");
    }
    if (channels.sms) {
        labels.push("SMS");
    }
    if (channels.whatsapp) {
        labels.push("WhatsApp");
    }

    return labels;
});

const smsExpiryCopy = computed(() => {
    if (!props.notification_channels?.sms) {
        return "";
    }

    const days = [...(props.sms_expiry_days ?? [7, 3, 1, 0])]
        .filter((day) => day > 0)
        .sort((a, b) => b - a);

    if (!days.length) {
        return "License and plan expiry SMS is sent on the expiry day.";
    }

    return `License and plan expiry SMS is sent ${days.join(", ")} days before, and on the expiry day.`;
});

const pageCount = computed(() =>
    Math.max(1, Math.ceil(props.alerts.length / pageSize)),
);

const pagedAlerts = computed(() => {
    const start = (page.value - 1) * pageSize;

    return props.alerts.slice(start, start + pageSize);
});

const pageStart = computed(() =>
    props.alerts.length ? (page.value - 1) * pageSize + 1 : 0,
);

const pageEnd = computed(() =>
    Math.min(page.value * pageSize, props.alerts.length),
);

watch(
    () => props.severity,
    (value) => {
        activeSeverity.value = value || "all";
        page.value = 1;
    },
);

watch(
    () => props.alerts.length,
    () => {
        if (page.value > pageCount.value) {
            page.value = pageCount.value;
        }
    },
);

const setSeverity = (value: string) => {
    if (activeSeverity.value === value) {
        return;
    }

    activeSeverity.value = value;
    page.value = 1;
    router.get(
        route("subscriptionAlerts.index"),
        { severity: value },
        { preserveState: true, replace: true },
    );
};

const typeLabels: Record<string, string> = {
    quota_exhausted: "Quota empty",
    subscription_expired: "Plan expired",
    subscription_expiring: "Plan expiring",
    license_expired: "License expired",
    license_expiring: "License expiring",
    payment_pending: "Payment pending",
    sms_low: "SMS low",
};

const typeLabel = (type: string) =>
    typeLabels[type] ?? type.replaceAll("_", " ");

const severityVariant = (severity: string) => {
    if (severity === "danger") {
        return "danger";
    }

    if (severity === "warning") {
        return "warning";
    }

    return "info";
};
</script>
