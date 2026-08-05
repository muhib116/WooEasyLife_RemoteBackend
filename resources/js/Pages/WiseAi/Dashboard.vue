<template>
    <AuthenticatedLayout title="Wise AI">
        <div class="space-y-5">
            <PageHeader
                title="Wise AI"
                description="আজ কী করবেন — review · language · publish · then check health"
                icon="PhBrain"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            >
                <template #actions>
                    <StatusBadge
                        :label="`v${stats.brain_version || '0.6.0'}`"
                        variant="neutral"
                        format="none"
                    />
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <PageCard title="আজ করুন" description="সবচেয়ে জরুরি তিনটা কাজ — ক্লিক করে সরাসরি যান">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Link
                        :href="route('wiseAi.learning', { kind: 'assist' })"
                        class="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 transition hover:shadow-md dark:border-amber-500/30 dark:bg-amber-500/10"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">
                            রিভিউ
                        </p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ stats.assist_pending }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">
                            Suggested replies waiting · Learning →
                        </p>
                    </Link>
                    <Link
                        :href="route('wiseAi.language', { review: 'open' })"
                        class="rounded-xl border border-violet-200 bg-violet-50/80 px-4 py-3 transition hover:shadow-md dark:border-violet-500/30 dark:bg-violet-500/10"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300">
                            ভাষা
                        </p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ stats.language_open ?? 0 }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">
                            Open abbrev / Banglish · Promote →
                        </p>
                    </Link>
                    <Link
                        :href="route('wiseAi.knowledge')"
                        class="rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-3 transition hover:shadow-md dark:border-emerald-500/30 dark:bg-emerald-500/10"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                            পাবলিশ
                        </p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ stats.knowledge_drafts ?? 0 }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">
                            Knowledge drafts · Publish →
                        </p>
                    </Link>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    New?
                    <Link :href="route('wiseAi.tutorials')" class="font-medium text-fuchsia-700 hover:underline dark:text-fuchsia-300">
                        Help checklist
                    </Link>
                    · Missed answers:
                    <Link :href="route('wiseAi.learning', { kind: 'gap' })" class="font-medium text-rose-600 hover:underline">
                        {{ stats.gaps_open }} open gaps
                    </Link>
                </p>
            </PageCard>

            <PageCard
                title="AI Health (live)"
                :description="`Last ${liveLocal.window_hours}h · polls every 15s · ${liveLocal.label}`"
            >
                <div
                    v-if="healAlertsLocal.length"
                    class="mb-4 space-y-2"
                >
                    <Link
                        v-for="alert in healAlertsLocal"
                        :key="alert.id + alert.message"
                        :href="route('wiseAi.learning', { kind: alert.href_kind || 'gap' })"
                        class="block rounded-xl border px-3 py-2 text-sm transition hover:shadow-sm"
                        :class="
                            alert.severity === 'critical'
                                ? 'border-rose-200 bg-rose-50/80 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100'
                                : 'border-amber-200 bg-amber-50/80 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100'
                        "
                    >
                        <span class="font-semibold">{{ alert.label }}</span>
                        — {{ alert.message }}
                    </Link>
                </div>
                <div class="flex flex-wrap items-end gap-6">
                    <div>
                        <p class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white">
                            {{ liveLocal.score }}
                            <span class="text-lg font-medium text-gray-400">/100</span>
                        </p>
                        <p class="mt-1 text-sm text-gray-500">{{ liveLocal.label }}</p>
                    </div>
                    <div class="grid flex-1 grid-cols-2 gap-3 sm:grid-cols-4">
                        <div v-for="m in metricTiles" :key="m.key" class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-slate-800/50">
                            <p class="text-[11px] uppercase tracking-wide text-gray-400">{{ m.label }}</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ m.value }}</p>
                        </div>
                    </div>
                </div>
            </PageCard>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link :href="route('wiseAi.intelligence')" class="block">
                    <StatCard
                        title="Turns Today"
                        :value="stats.turns_today"
                        icon="PhChatCircleDots"
                        accent-class="bg-fuchsia-500"
                        icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                        icon-class="text-fuchsia-600 dark:text-fuchsia-400"
                        subtitle="Merchant Intelligence →"
                    />
                </Link>
                <Link :href="route('wiseAi.learning', { kind: 'assist' })" class="block">
                    <StatCard
                        title="Assist Pending"
                        :value="stats.assist_pending"
                        icon="PhHandPalm"
                        accent-class="bg-amber-500"
                        icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                        icon-class="text-amber-600 dark:text-amber-400"
                        :subtitle="`${stats.needs_human_today} needs_human today — Learning →`"
                    />
                </Link>
                <Link :href="route('wiseAi.learning', { kind: 'gap' })" class="block">
                    <StatCard
                        title="Open Gaps"
                        :value="stats.gaps_open"
                        icon="PhWarning"
                        accent-class="bg-rose-500"
                        icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                        icon-class="text-rose-600 dark:text-rose-400"
                        :subtitle="`${stats.gaps_today} logged today — Learning →`"
                    />
                </Link>
                <StatCard
                    title="Published Knowledge"
                    :value="stats.published_knowledge"
                    icon="PhBooks"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                    subtitle="Live FAQ / policies"
                />
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <PageCard
                    title="Recent Turns"
                    description="Latest customer messages and brain decisions"
                    class="xl:col-span-2"
                    :no-padding="recentTurns.length > 0"
                >
                    <DataTable
                        v-if="recentTurns.length"
                        :value="recentTurns"
                        size="small"
                        striped-rows
                        class="professional-table"
                    >
                        <Column header="Message">
                            <template #body="{ data }">
                                <span class="block max-w-[220px] truncate text-sm" :title="data.text">
                                    {{ data.text }}
                                </span>
                                <span class="text-[11px] text-gray-400">
                                    {{ data.key_name || "unknown key" }} · {{ data.channel }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Intent">
                            <template #body="{ data }">
                                <StatusBadge :label="data.intent || 'unknown'" variant="neutral" format="none" />
                            </template>
                        </Column>
                        <Column header="Conf.">
                            <template #body="{ data }">
                                <span class="text-sm font-medium">{{ data.confidence ?? 0 }}%</span>
                            </template>
                        </Column>
                        <Column header="Action">
                            <template #body="{ data }">
                                <StatusBadge
                                    :label="data.action || '—'"
                                    :variant="data.action === 'needs_human' ? 'warning' : 'success'"
                                    format="none"
                                />
                                <span v-if="data.gap" class="ml-1 text-[10px] text-rose-500">gap</span>
                            </template>
                        </Column>
                        <Column header="Meta">
                            <template #body="{ data }">
                                <span class="text-xs text-gray-500">{{ data.source || "—" }}</span>
                                <span v-if="data.llm_applied" class="ml-1 text-[10px] text-violet-600">llm</span>
                                <span
                                    v-if="data.experience_net != null"
                                    class="ml-1 text-[10px] text-sky-600"
                                >exp {{ data.experience_net }}</span>
                            </template>
                        </Column>
                        <Column header="When">
                            <template #body="{ data }">
                                <span class="text-xs text-gray-500">{{ data.created_at }}</span>
                            </template>
                        </Column>
                    </DataTable>
                    <EmptyState
                        v-else
                        icon="PhChatCircleDots"
                        title="No turns recorded yet"
                        description="Create an API key in Config, then send a message from the Playground — it will show up here"
                    />
                </PageCard>

                <PageCard title="Technical" description="Runtime layers — optional detail">
                    <button
                        type="button"
                        class="mb-2 text-xs font-semibold text-fuchsia-700 hover:underline dark:text-fuchsia-300"
                        @click="showPipeline = !showPipeline"
                    >
                        {{ showPipeline ? "Hide pipeline" : "Show brain pipeline" }}
                    </button>
                    <ol v-if="showPipeline" class="space-y-1.5">
                        <li
                            v-for="(layer, index) in pipelineLayers"
                            :key="layer.name"
                            class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50/60 px-3 py-2.5 dark:border-gray-800 dark:bg-slate-800/40"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-fuchsia-600 shadow-sm dark:bg-slate-900 dark:text-fuchsia-400"
                            >
                                <Icon :name="layer.icon" class="text-lg" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                                    {{ index + 1 }}. {{ layer.name }}
                                </p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ layer.hint }}
                                </p>
                            </div>
                            <StatusBadge :label="layer.status" :variant="layer.variant" format="none" />
                        </li>
                    </ol>
                    <p v-else class="text-xs text-gray-500">
                        LLM: {{ llmLocal.enabled ? (llmLocal.key_set ? llmLocal.model : "no key") : "off" }}
                    </p>
                </PageCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import axios from "axios";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import { AuthenticatedLayout } from "@/layouts";
import { Icon } from "@/plugins";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import { Link } from "@inertiajs/vue3";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";
import type { IconName } from "@/types";

type TurnRow = {
    id: number;
    key_name: string | null;
    channel: string;
    text: string | null;
    intent: string | null;
    confidence: number | null;
    action: string | null;
    source: string | null;
    gap: boolean;
    latency_ms: number | null;
    created_at: string | null;
    llm_applied?: boolean;
    experience_net?: number | null;
};

type LivePayload = {
    version: string;
    score: number;
    label: string;
    window_hours: number;
    metrics: Record<string, number>;
    llm: { platform_enabled: boolean; key_set: boolean; applied_rate: number };
};

type HealAlert = {
    id: string;
    severity: string;
    label: string;
    message: string;
    href_kind?: string;
};

const props = defineProps<{
    stats: {
        turns_today: number;
        turns_total: number;
        avg_confidence: number;
        active_keys: number;
        gaps_today: number;
        gaps_open: number;
        assist_pending: number;
        language_open?: number;
        knowledge_drafts?: number;
        needs_human_today: number;
        published_knowledge: number;
        brain_version?: string;
    };
    live: LivePayload;
    heal_alerts?: HealAlert[];
    heal_alerts_version?: string;
    llm_pipeline: { enabled: boolean; key_set: boolean; model: string };
    recentTurns: TurnRow[];
}>();

const liveLocal = ref<LivePayload>({ ...props.live });
const healAlertsLocal = ref<HealAlert[]>([...(props.heal_alerts || [])]);
const llmLocal = ref({ ...props.llm_pipeline });
const showPipeline = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;

const metricTiles = computed(() => {
    const m = liveLocal.value.metrics || {};
    return [
        { key: "accept", label: "Accept", value: `${m.accept_rate ?? 0}%` },
        { key: "reject", label: "Reject", value: `${m.reject_rate ?? 0}%` },
        { key: "gap", label: "Gap", value: `${m.gap_rate ?? 0}%` },
        { key: "clarify", label: "Clarify", value: `${m.clarify_rate ?? 0}%` },
        { key: "conf", label: "Avg conf", value: `${m.avg_confidence ?? 0}%` },
        { key: "lat", label: "Latency", value: `${m.avg_latency_ms ?? 0} ms` },
        { key: "exp", label: "Experience", value: `${m.experience_net ?? 0}` },
        { key: "llm", label: "LLM applied", value: `${m.llm_applied_rate ?? 0}%` },
    ];
});

type PipelineLayer = {
    name: string;
    hint: string;
    icon: IconName;
    status: string;
    variant: "success" | "warning" | "neutral";
};

const pipelineLayers = computed<PipelineLayer[]>(() => {
    const llmOn = llmLocal.value.enabled && llmLocal.value.key_set;
    const llmStatus = !llmLocal.value.enabled
        ? "Off"
        : llmLocal.value.key_set
          ? "On"
          : "No key";
    return [
        { name: "Admission", hint: "API key auth + sealed config snapshot", icon: "PhShieldCheck", status: "Live", variant: "success" },
        { name: "Perception", hint: "BCLC + pattern intent", icon: "PhEye", status: "v1", variant: "success" },
        { name: "Knowledge", hint: "Published FAQ/policy grounding", icon: "PhBooks", status: "v1", variant: "success" },
        { name: "Memory", hint: "Conversation follow-ups", icon: "PhDatabase", status: "v0", variant: "success" },
        { name: "Experience", hint: "What worked (soft hints only)", icon: "PhChartLineUp", status: "v0", variant: "success" },
        { name: "Judge", hint: "Evidence + contracts before wording", icon: "PhCircuitry", status: "v1", variant: "success" },
        { name: "Language", hint: "Scripts + knowledge answers", icon: "PhChatCircleDots", status: "v1", variant: "success" },
        {
            name: "LLM (optional)",
            hint: llmLocal.value.model || "wording specialist",
            icon: "PhSparkle",
            status: llmStatus,
            variant: llmOn ? "success" : "warning",
        },
    ];
});

const poll = async () => {
    try {
        const { data } = await axios.get(route("wiseAi.dashboard.live"));
        if (data?.live) liveLocal.value = data.live;
        if (data?.llm_pipeline) llmLocal.value = data.llm_pipeline;
        if (Array.isArray(data?.heal_alerts)) healAlertsLocal.value = data.heal_alerts;
    } catch {
        // keep last snapshot
    }
};

onMounted(() => {
    timer = setInterval(poll, 15000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>
