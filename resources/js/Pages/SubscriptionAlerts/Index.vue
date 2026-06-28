<template>
    <AuthenticatedLayout title="Subscription Alerts">
        <div class="space-y-5">
            <PageHeader
                title="Subscription Alerts"
                description="Merchants with low quota, expiring licenses, or pending renewals"
                icon="PhBellRinging"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <div
                v-if="notifications_enabled"
                class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100"
            >
                Daily merchant notifications run at 08:05 via:
                <span v-if="notification_channels.email"> email</span>
                <span v-if="notification_channels.sms">, SMS</span>
                <span v-if="notification_channels.whatsapp">, WhatsApp</span>
                <span v-if="!notification_channels.email && !notification_channels.sms && !notification_channels.whatsapp">
                    (no outbound channels enabled)
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <StatCard
                    title="Total"
                    :value="summary.total"
                    icon="PhBell"
                    accent-class="bg-primary-500"
                />
                <StatCard
                    title="Critical"
                    :value="summary.danger"
                    icon="PhWarningCircle"
                    accent-class="bg-red-500"
                />
                <StatCard
                    title="Warnings"
                    :value="summary.warning"
                    icon="PhWarning"
                    accent-class="bg-amber-500"
                />
                <StatCard
                    title="Info"
                    :value="summary.info"
                    icon="PhInfo"
                    accent-class="bg-sky-500"
                />
            </div>

            <SelectButton
                v-model="activeSeverity"
                :options="severityOptions"
                option-label="label"
                option-value="value"
                @change="onSeverityChange"
            />

            <PageCard
                title="Active Alerts"
                :description="`${alerts.length} shown`"
                no-padding
            >
                <DataTable
                    :value="alerts"
                    paginator
                    :rows="15"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column header="Severity">
                        <template #body="{ data }">
                            <StatusBadge
                                :label="data.severity"
                                :variant="severityVariant(data.severity)"
                                format="severity"
                            />
                        </template>
                    </Column>
                    <Column field="type" header="Type" />
                    <Column header="Merchant">
                        <template #body="{ data }">
                            <Link
                                :href="route('users.view', data.user_id)"
                                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ data.user_name }}
                            </Link>
                        </template>
                    </Column>
                    <Column field="domain" header="Domain" />
                    <Column field="message" header="Message" />
                    <Column header="Notified">
                        <template #body="{ data }">
                            <span
                                v-if="data.notification_channels?.length"
                                class="text-xs text-gray-600 dark:text-gray-300"
                            >
                                {{ data.notification_channels.join(", ") }}
                            </span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </Column>
                    <Column header="Actions">
                        <template #body="{ data }">
                            <Link
                                :href="route('users.billing', data.user_id)"
                                class="text-theme-xs inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-slate-800"
                            >
                                Billing
                                <Icon name="PhArrowRight" class="text-sm" />
                            </Link>
                        </template>
                    </Column>
                </DataTable>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/components/PageCard.vue";
import StatCard from "@/components/StatCard.vue";
import StatusBadge from "@/components/StatusBadge.vue";
import Icon from "@/components/Icon.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref } from "vue";

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
}>();

const activeSeverity = ref(props.severity);

const severityOptions = [
    { label: "All", value: "all" },
    { label: "Critical", value: "danger" },
    { label: "Warning", value: "warning" },
    { label: "Info", value: "info" },
];

const onSeverityChange = () => {
    router.get(
        route("subscriptionAlerts.index"),
        { severity: activeSeverity.value },
        { preserveState: true, replace: true },
    );
};

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
