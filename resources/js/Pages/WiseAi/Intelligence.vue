<template>
    <AuthenticatedLayout title="Wise AI — Intelligence">
        <div class="space-y-5">
            <PageHeader
                title="Merchant Intelligence"
                description="Advanced reports — quality rates, gaps, clarify mix (sandbox excluded by default)"
                icon="PhChartLineUp"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
            >
                <template #actions>
                    <StatusBadge label="Advanced" variant="neutral" format="none" />
                    <StatusBadge
                        :label="`metrics ${metrics_version}`"
                        variant="neutral"
                        format="none"
                    />
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <PageCard title="Filters" description="Windowed rates from sealed turns + feedback. Live queues are current open work.">
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
                    <label class="text-xs text-gray-500">
                        API key
                        <select v-model="form.key_id" class="mt-1 block min-w-[12rem] rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-slate-900">
                            <option :value="null">All keys</option>
                            <option v-for="k in keys" :key="k.id" :value="k.id">
                                {{ k.name }}{{ k.sandbox ? " (sandbox)" : "" }}
                            </option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                        <input v-model="form.include_sandbox" type="checkbox" class="rounded border-gray-300" />
                        Include sandbox
                    </label>
                    <Button type="submit" label="Apply" size="small" />
                    <Link
                        :href="route('wiseAi.fleet')"
                        class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        Fleet →
                    </Link>
                    <Link
                        :href="route('wiseAi.learning')"
                        class="text-sm font-medium text-fuchsia-600 hover:underline dark:text-fuchsia-400"
                    >
                        Open Learning →
                    </Link>
                </form>
                <p class="mt-3 text-[11px] text-gray-400">
                    Since {{ report.window.since }} · sandbox
                    {{ report.window.exclude_sandbox ? "excluded" : "included" }}
                </p>
            </PageCard>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-6">
                <StatCard
                    title="Turns"
                    :value="report.metrics.turns"
                    icon="PhChatCircleDots"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                    :subtitle="pctLabel(report.metrics.suggest_rate, 'suggest')"
                />
                <StatCard
                    title="Gap rate"
                    :value="pctDisplay(report.metrics.gap_rate)"
                    icon="PhWarning"
                    accent-class="bg-rose-500"
                    icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                    icon-class="text-rose-600 dark:text-rose-400"
                    subtitle="Windowed"
                />
                <StatCard
                    title="Clarify rate"
                    :value="pctDisplay(report.metrics.clarify_rate)"
                    icon="PhChatTeardropDots"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                    subtitle="Missing context / soft"
                />
                <StatCard
                    title="Accept rate"
                    :value="pctDisplay(report.metrics.accept_rate)"
                    icon="PhChecks"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                    :subtitle="`${report.feedback_mix.reviewed} reviewed`"
                />
                <StatCard
                    title="Reject rate"
                    :value="pctDisplay(report.metrics.reject_rate)"
                    icon="PhXCircle"
                    accent-class="bg-orange-500"
                    icon-bg-class="bg-orange-50 dark:bg-orange-500/15"
                    icon-class="text-orange-600 dark:text-orange-400"
                    :subtitle="pctLabel(report.metrics.knowledge_leak_proxy, 'knowledge leak')"
                />
                <Link :href="route('wiseAi.learning')" class="block">
                    <StatCard
                        title="Open queues"
                        :value="report.queues.open_total"
                        icon="PhTray"
                        accent-class="bg-violet-500"
                        icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                        icon-class="text-violet-600 dark:text-violet-400"
                        :subtitle="`gap ${report.queues.gaps_open} · assist ${report.queues.assist_pending} · lang ${report.queues.language_open}`"
                    />
                </Link>
            </div>

            <PageCard
                title="Commerce attribution"
                description="Honest money only from adapter commerce events joined to prior conversation turns"
            >
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                        <p class="text-[11px] uppercase text-gray-400">Attributed orders</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ report.commerce?.attributed_orders ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                        <p class="text-[11px] uppercase text-gray-400">Assisted order rate</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ pctDisplay(report.commerce?.assisted_order_rate) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                        <p class="text-[11px] uppercase text-gray-400">Attributed GMV</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            <template v-if="report.commerce?.attributed_gmv != null">
                                {{ report.commerce.attributed_gmv }}
                                <span v-if="report.commerce.attributed_gmv_currency" class="text-sm font-medium text-gray-500">
                                    {{ report.commerce.attributed_gmv_currency }}
                                </span>
                            </template>
                            <span v-else class="text-base font-medium text-gray-400">n/a</span>
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                        <p class="text-[11px] uppercase text-gray-400">Lost sales (attributed)</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ report.commerce?.lost_sales_attributed ?? 0 }}
                        </p>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">
                    {{ report.commerce?.attributed_gmv_note || "POST /api/wise/v1/commerce/events with conversation_id" }}
                    · events {{ report.commerce?.events_total ?? 0 }}
                    · schema {{ report.commerce?.schema_version || "?" }}
                </p>
            </PageCard>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <PageCard title="Action mix" description="What the brain chose in-window">
                    <ul class="space-y-2 text-sm">
                        <li v-for="row in actionRows" :key="row.label" class="flex justify-between gap-3">
                            <span class="text-gray-600 dark:text-gray-300">{{ row.label }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ row.count }}</span>
                        </li>
                    </ul>
                    <p v-if="report.metrics.avg_latency_ms != null" class="mt-4 text-xs text-gray-400">
                        Avg latency {{ report.metrics.avg_latency_ms }} ms
                    </p>
                </PageCard>

                <PageCard title="Reject reasons" description="Taxonomy from Learning (reason_codes)">
                    <ul v-if="report.reject_reasons.length" class="space-y-2 text-sm">
                        <li
                            v-for="row in report.reject_reasons"
                            :key="row.code"
                            class="flex justify-between gap-3"
                        >
                            <span class="text-gray-600 dark:text-gray-300">{{ row.label }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ row.count }}</span>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        icon="PhSmiley"
                        title="No rejects in window"
                        description="Reject with a reason code in Learning or Playground"
                    />
                    <Link
                        :href="route('wiseAi.learning', { kind: 'reject' })"
                        class="mt-3 inline-block text-xs font-medium text-fuchsia-600 hover:underline"
                    >
                        Reject feed →
                    </Link>
                </PageCard>

                <PageCard title="Metric definitions" :description="`Registry ${metrics_version} (sealed on turns)`">
                    <ul class="max-h-64 space-y-2 overflow-y-auto text-xs">
                        <li v-for="d in report.definitions" :key="d.id">
                            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ d.label }}</span>
                            <span class="text-gray-400"> · {{ d.group }}</span>
                            <p class="text-gray-500 dark:text-gray-400">{{ d.definition }}</p>
                        </li>
                    </ul>
                </PageCard>
            </div>

            <PageCard
                v-if="report.by_key.length"
                title="By API key"
                description="Volume + gap rate (click to filter)"
                :no-padding="true"
            >
                <DataTable :value="report.by_key" size="small" striped-rows class="professional-table">
                    <Column header="Key">
                        <template #body="{ data }">
                            <Link
                                :href="route('wiseAi.intelligence', { ...filterParams, key_id: data.wise_api_key_id })"
                                class="text-sm font-medium text-fuchsia-600 hover:underline"
                            >
                                {{ data.key_name }}
                            </Link>
                        </template>
                    </Column>
                    <Column field="turns" header="Turns" />
                    <Column field="gaps" header="Gaps" />
                    <Column header="Gap %">
                        <template #body="{ data }">{{ pctDisplay(data.gap_rate) }}</template>
                    </Column>
                </DataTable>
            </PageCard>

            <PageCard title="Drill" description="Gaps, clarify/needs_human, rejects → Explain or Learning" :no-padding="report.drill.length > 0">
                <DataTable v-if="report.drill.length" :value="report.drill" size="small" striped-rows class="professional-table">
                    <Column header="Message">
                        <template #body="{ data }">
                            <span class="block max-w-[260px] truncate text-sm" :title="data.text">{{ data.text }}</span>
                            <span class="text-[11px] text-gray-400">
                                #{{ data.turn_id }} · {{ data.key_name || "—" }} · {{ data.intent }}/{{ data.action }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Flags" style="width: 8rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <StatusBadge v-if="data.gap" label="gap" variant="danger" format="none" />
                                <StatusBadge
                                    v-if="data.feedback"
                                    :label="data.feedback"
                                    :variant="data.feedback === 'rejected' ? 'warning' : 'info'"
                                    format="none"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column header="When" style="width: 9rem">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-500">{{ data.created_at }}</span>
                        </template>
                    </Column>
                    <Column header="Drill" style="width: 10rem">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-1">
                                <button
                                    type="button"
                                    class="text-left text-xs font-medium text-sky-600 hover:underline dark:text-sky-400"
                                    @click="openReplay(data.turn_id)"
                                >
                                    Replay →
                                </button>
                                <Link
                                    v-if="data.drill === 'learning_gap'"
                                    :href="route('wiseAi.learning', { kind: 'gap' })"
                                    class="text-xs font-medium text-fuchsia-600 hover:underline"
                                >
                                    Learning gaps →
                                </Link>
                                <Link
                                    v-else-if="data.drill === 'learning_reject'"
                                    :href="route('wiseAi.learning', { kind: 'reject' })"
                                    class="text-xs font-medium text-fuchsia-600 hover:underline"
                                >
                                    Learning rejects →
                                </Link>
                            </div>
                        </template>
                    </Column>
                </DataTable>
                <EmptyState
                    v-else
                    icon="PhMagnifyingGlass"
                    title="Nothing to drill"
                    description="No gaps, clarify/needs_human, or rejects in this filter"
                />
            </PageCard>

            <TurnReplayDialog v-model:visible="replayOpen" :turn-id="replayTurnId" />
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
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
import TurnReplayDialog from "./fragments/TurnReplayDialog.vue";

type MetricDef = {
    id: string;
    label: string;
    unit: string;
    definition: string;
    group: string;
};

type Report = {
    metrics_version: string;
    definitions: MetricDef[];
    window: {
        days: number;
        since: string;
        exclude_sandbox: boolean;
        wise_api_key_id: number | null;
    };
    metrics: Record<string, number | null>;
    action_mix: Record<string, number>;
    feedback_mix: Record<string, number>;
    reject_reasons: { code: string; label: string; count: number }[];
    commerce?: {
        schema_version?: string;
        events_total?: number;
        attributed_orders?: number;
        assisted_order_rate?: number | null;
        attributed_gmv?: number | null;
        attributed_gmv_currency?: string | null;
        attributed_gmv_note?: string;
        lost_sales_attributed?: number;
    };
    queues: {
        assist_pending: number;
        gaps_open: number;
        language_open: number;
        open_total: number;
    };
    by_key: {
        wise_api_key_id: number;
        key_name: string;
        turns: number;
        gaps: number;
        gap_rate: number | null;
    }[];
    drill: {
        turn_id: number;
        text: string;
        key_name: string | null;
        action: string | null;
        intent: string | null;
        gap: boolean;
        feedback: string | null;
        reason_code: string | null;
        created_at: string | null;
        drill: string;
    }[];
};

const props = defineProps<{
    report: Report;
    brain_version: string;
    metrics_version: string;
    keys: { id: number; name: string; status: string; sandbox: boolean }[];
    filters: { days: number; key_id: number | null; include_sandbox: boolean };
}>();

const form = reactive({
    days: props.filters.days,
    key_id: props.filters.key_id as number | null,
    include_sandbox: props.filters.include_sandbox,
});

const filterParams = computed(() => ({
    days: form.days,
    ...(form.key_id ? { key_id: form.key_id } : {}),
    ...(form.include_sandbox ? { include_sandbox: 1 } : {}),
}));

const actionRows = computed(() => [
    { label: "Suggest reply", count: props.report.action_mix.suggest_reply },
    { label: "Clarify", count: props.report.action_mix.clarify },
    { label: "Needs human", count: props.report.action_mix.needs_human },
    { label: "Other", count: props.report.action_mix.other },
]);

const pctDisplay = (v: number | null | undefined) => (v == null ? "—" : `${v}%`);
const pctLabel = (v: number | null | undefined, suffix: string) =>
    v == null ? suffix : `${v}% ${suffix}`;

const applyFilters = () => {
    router.get(route("wiseAi.intelligence"), filterParams.value, { preserveState: true });
};

const replayOpen = ref(false);
const replayTurnId = ref<number | null>(null);

const openReplay = (turnId: number) => {
    replayTurnId.value = turnId;
    replayOpen.value = true;
};
</script>
