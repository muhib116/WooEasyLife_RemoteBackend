<template>
    <AuthenticatedLayout title="Wise AI — Fleet">
        <div class="space-y-5">
            <PageHeader
                title="Founder Fleet"
                description="Advanced — multi-key health, queues, cost proxy, observe-only alerts"
                icon="PhBroadcast"
                icon-bg-class="bg-indigo-50 dark:bg-indigo-500/15"
                icon-class="text-indigo-600 dark:text-indigo-400"
            >
                <template #actions>
                    <StatusBadge label="Advanced" variant="neutral" format="none" />
                    <StatusBadge
                        :label="`alerts ${alerts_version}`"
                        variant="neutral"
                        format="none"
                    />
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <PageCard title="Filters" description="Sandbox keys hidden by default (Playground / eval isolation).">
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
                    <label class="text-xs text-gray-500">
                        Days
                        <select v-model="form.days" class="mt-1 block rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-slate-900">
                            <option :value="7">7</option>
                            <option :value="14">14</option>
                            <option :value="30">30</option>
                            <option :value="90">90</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                        <input v-model="form.include_sandbox" type="checkbox" class="rounded border-gray-300" />
                        Include sandbox keys
                    </label>
                    <Button type="submit" label="Apply" size="small" />
                    <Link
                        :href="route('wiseAi.intelligence')"
                        class="text-sm font-medium text-fuchsia-600 hover:underline dark:text-fuchsia-400"
                    >
                        Merchant Intelligence →
                    </Link>
                </form>
                <p class="mt-3 text-[11px] text-gray-400">
                    Since {{ report.window.since }} ·
                    {{ report.fleet.keys_sandbox_hidden }} sandbox key(s) hidden · cost =
                    {{ report.fleet.cost_units_label }}
                </p>
            </PageCard>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-6">
                <StatCard
                    title="Keys (scoped)"
                    :value="report.fleet.keys_scoped"
                    icon="PhKey"
                    accent-class="bg-indigo-500"
                    icon-bg-class="bg-indigo-50 dark:bg-indigo-500/15"
                    icon-class="text-indigo-600 dark:text-indigo-400"
                    :subtitle="`${report.fleet.keys_active} active`"
                />
                <StatCard
                    title="Fleet turns"
                    :value="report.fleet.turns"
                    icon="PhChatCircleDots"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                    :subtitle="pctLabel(report.fleet.gap_rate, 'gap')"
                />
                <StatCard
                    title="Accept rate"
                    :value="pctDisplay(report.fleet.accept_rate)"
                    icon="PhChecks"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                    :subtitle="`${report.fleet.reviewed} reviewed`"
                />
                <StatCard
                    title="Reject rate"
                    :value="pctDisplay(report.fleet.reject_rate)"
                    icon="PhXCircle"
                    accent-class="bg-orange-500"
                    icon-bg-class="bg-orange-50 dark:bg-orange-500/15"
                    icon-class="text-orange-600 dark:text-orange-400"
                    subtitle="Across fleet reviews"
                />
                <StatCard
                    title="Cost units"
                    :value="report.fleet.cost_units"
                    icon="PhLightning"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                    :subtitle="report.fleet.avg_latency_ms != null ? `avg ${report.fleet.avg_latency_ms} ms` : 'turn-ms sum'"
                />
                <StatCard
                    title="Alerts"
                    :value="report.fleet.alerts_open"
                    icon="PhSiren"
                    accent-class="bg-rose-500"
                    icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                    icon-class="text-rose-600 dark:text-rose-400"
                    :subtitle="`rules ${alerts_version}`"
                />
            </div>

            <PageCard title="Alerts" description="Thresholds from FleetAlerts catalog — observe, not auto-remediate">
                <ul v-if="report.alerts.length" class="space-y-2">
                    <li
                        v-for="(a, idx) in report.alerts"
                        :key="`${a.id}-${a.wise_api_key_id}-${idx}`"
                        class="flex flex-wrap items-start justify-between gap-2 rounded-xl border px-3 py-2.5 text-sm"
                        :class="alertBorder(a.severity)"
                    >
                        <div>
                            <StatusBadge :label="a.severity" :variant="severityVariant(a.severity)" format="none" />
                            <span class="ml-2 font-medium text-gray-800 dark:text-gray-100">{{ a.key_name }}</span>
                            <p class="mt-0.5 text-xs text-gray-500">{{ a.message }}</p>
                        </div>
                        <Link
                            :href="route('wiseAi.intelligence', { key_id: a.wise_api_key_id, days: filters.days })"
                            class="text-xs font-medium text-fuchsia-600 hover:underline"
                        >
                            Open BI →
                        </Link>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    icon="PhSmiley"
                    title="No fleet alerts"
                    description="Keys are within gap/reject/queue/latency thresholds"
                />
            </PageCard>

            <PageCard title="Daily volume" description="Non-sandbox turns by day" :no-padding="false">
                <div class="flex h-24 items-end gap-1">
                    <div
                        v-for="d in report.daily"
                        :key="d.date"
                        class="flex-1 rounded-t bg-indigo-400/80 dark:bg-indigo-500/70"
                        :style="{ height: barHeight(d.turns) }"
                        :title="`${d.date}: ${d.turns}`"
                    />
                </div>
                <p class="mt-2 text-[11px] text-gray-400">
                    {{ report.daily[0]?.date }} → {{ report.daily[report.daily.length - 1]?.date }}
                </p>
            </PageCard>

            <PageCard title="Keys" description="Click name → Merchant Intelligence for that key" :no-padding="report.keys.length > 0">
                <DataTable v-if="report.keys.length" :value="report.keys" size="small" striped-rows class="professional-table">
                    <Column header="Key">
                        <template #body="{ data }">
                            <Link
                                :href="route('wiseAi.intelligence', { key_id: data.wise_api_key_id, days: filters.days })"
                                class="text-sm font-medium text-fuchsia-600 hover:underline"
                            >
                                {{ data.key_name }}
                            </Link>
                            <div class="text-[11px] text-gray-400">
                                {{ data.status }} · {{ data.mode }}
                                <span v-if="data.sandbox"> · sandbox</span>
                                <span v-if="data.allow_auto" class="text-rose-500"> · AUTO</span>
                            </div>
                        </template>
                    </Column>
                    <Column field="turns" header="Turns" />
                    <Column header="Gap %">
                        <template #body="{ data }">{{ pctDisplay(data.gap_rate) }}</template>
                    </Column>
                    <Column header="Accept %">
                        <template #body="{ data }">{{ pctDisplay(data.accept_rate) }}</template>
                    </Column>
                    <Column field="cost_units" header="Cost u." />
                    <Column header="Queues">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-500">
                                g{{ data.gaps_open }} / a{{ data.assist_pending }} / l{{ data.language_open }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Alerts">
                        <template #body="{ data }">
                            <span v-if="data.alert_ids.length" class="text-xs text-rose-600">
                                {{ data.alert_ids.length }}
                            </span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </Column>
                </DataTable>
                <EmptyState
                    v-else
                    icon="PhKey"
                    title="No keys in scope"
                    description="Create a key in Config, or include sandbox"
                />
            </PageCard>

            <PageCard title="Alert rules" :description="`FleetAlerts ${alerts_version}`">
                <ul class="space-y-2 text-xs">
                    <li v-for="r in report.alert_catalog" :key="r.id">
                        <StatusBadge :label="r.severity" :variant="severityVariant(r.severity)" format="none" />
                        <span class="ml-2 font-semibold text-gray-800 dark:text-gray-100">{{ r.label }}</span>
                        <p class="text-gray-500">{{ r.definition }}</p>
                    </li>
                </ul>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, reactive } from "vue";
import { Link, router } from "@inertiajs/vue3";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";

type Alert = {
    id: string;
    severity: string;
    wise_api_key_id: number;
    key_name: string;
    message: string;
};

type Report = {
    metrics_version: string;
    alerts_version: string;
    window: { days: number; since: string; exclude_sandbox: boolean };
    fleet: {
        keys_total: number;
        keys_scoped: number;
        keys_active: number;
        keys_sandbox_hidden: number;
        turns: number;
        gap_rate: number | null;
        accept_rate: number | null;
        reject_rate: number | null;
        reviewed: number;
        avg_latency_ms: number | null;
        cost_units: number;
        cost_units_label: string;
        alerts_open: number;
    };
    keys: Record<string, unknown>[];
    alerts: Alert[];
    alert_catalog: { id: string; severity: string; label: string; definition: string }[];
    daily: { date: string; turns: number }[];
};

const props = defineProps<{
    report: Report;
    brain_version: string;
    metrics_version: string;
    alerts_version: string;
    filters: { days: number; include_sandbox: boolean };
}>();

const form = reactive({
    days: props.filters.days,
    include_sandbox: props.filters.include_sandbox,
});

const maxDaily = computed(() => Math.max(1, ...props.report.daily.map((d) => d.turns)));

const applyFilters = () => {
    router.get(
        route("wiseAi.fleet"),
        {
            days: form.days,
            ...(form.include_sandbox ? { include_sandbox: 1 } : {}),
        },
        { preserveState: true },
    );
};

const pctDisplay = (v: number | null | undefined) => (v == null ? "—" : `${v}%`);
const pctLabel = (v: number | null | undefined, suffix: string) =>
    v == null ? suffix : `${v}% ${suffix}`;

const barHeight = (turns: number) => `${Math.max(4, Math.round((turns / maxDaily.value) * 100))}%`;

const severityVariant = (s: string): "danger" | "warning" | "info" | "neutral" => {
    if (s === "critical") return "danger";
    if (s === "warning") return "warning";
    if (s === "info") return "info";
    return "neutral";
};

const alertBorder = (s: string) => {
    if (s === "critical") return "border-rose-300 bg-rose-50/50 dark:border-rose-500/40 dark:bg-rose-500/10";
    if (s === "warning") return "border-amber-300 bg-amber-50/50 dark:border-amber-500/40 dark:bg-amber-500/10";
    return "border-gray-200 bg-white dark:border-gray-800 dark:bg-slate-900/60";
};
</script>
