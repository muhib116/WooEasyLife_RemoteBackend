<template>
    <AuthenticatedLayout title="Wise AI">
        <div class="space-y-5">
            <PageHeader
                title="Wise AI"
                description="Central decision brain — one API, one key, one payload for every product"
                icon="PhBrain"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            >
                <template #actions>
                    <StatusBadge label="v0.2.1 — trust + guides" variant="success" format="none" />
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Wise AI কীভাবে চালাবেন (পুরো ফ্লো)"
                subtitle="সিস্টেম AI-driven — নিচের ধাপ অনুসরণ না করলে Playground-এ সঠিক উত্তর পাবেন না"
                badge="শুরু এখান থেকে"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Turns Today"
                    :value="stats.turns_today"
                    icon="PhChatCircleDots"
                    accent-class="bg-fuchsia-500"
                    icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                    icon-class="text-fuchsia-600 dark:text-fuchsia-400"
                    subtitle="Messages decided today"
                />
                <StatCard
                    title="Needs Human Today"
                    :value="stats.needs_human_today"
                    icon="PhHandPalm"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                    subtitle="Business facts without evidence"
                />
                <StatCard
                    title="Gaps Today"
                    :value="stats.gaps_today"
                    icon="PhWarning"
                    accent-class="bg-rose-500"
                    icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                    icon-class="text-rose-600 dark:text-rose-400"
                    subtitle="Knowledge misses logged"
                />
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
                    title="Brain Pipeline"
                    description="Layer-by-layer status of the Wise AI runtime"
                    class="xl:col-span-1"
                >
                    <ol class="space-y-1.5">
                        <li
                            v-for="(layer, index) in pipeline"
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
                </PageCard>

                <PageCard
                    title="Recent Turns"
                    description="Latest messages and the decisions the brain made"
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
                        <Column header="Src">
                            <template #body="{ data }">
                                <span class="text-xs text-gray-500">{{ data.source || "—" }}</span>
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
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import { AuthenticatedLayout } from "@/layouts";
import { Icon } from "@/plugins";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";
import WiseAiHowTo from "./fragments/WiseAiHowTo.vue";
import type { IconName } from "@/types";

const howToSteps = [
    {
        title: "Config → API Key তৈরি করুন",
        detail: "Generate Key চাপুন, plain key একবারই দেখাবে — Copy করে রাখুন। Playground ও বাইরের সার্ভিস এই key দিয়েই কথা বলে।",
    },
    {
        title: "Knowledge → FAQ/Policy draft সেভ করুন",
        detail: "Title + Answer আবশ্যক। Question ও Keywords দিলে ম্যাচ ভালো হয়। Save as draft = এখনো brain ব্যবহার করবে না।",
    },
    {
        title: "Publish চাপুন (মানুষের অনুমোদন)",
        detail: "শুধু Published knowledge থেকেই ব্যবসায়িক উত্তর (দাম/ডেলিভারি/অর্ডার) আসে। Draft থাকলে needs_human + gap দেখাবে।",
    },
    {
        title: "Playground → key paste → মেসেজ পাঠান",
        detail: "আসল /api/wise/v1/decide এ যায়। Greeting এ উত্তর আসবে; ব্যবসায়িক প্রশ্নে published knowledge লাগবে।",
    },
    {
        title: "Decision Trace → Approve / Reject",
        detail: "প্রতিটি সাজেশন মানুষ রিভিউ করে — silent auto-learn হয় না। Dashboard-এ Recent Turns ও stats দেখুন।",
    },
];

const howToTips = [
    "সামাজিক (হাই/ধন্যবাদ) = knowledge ছাড়াই উত্তর হতে পারে। ব্যবসায়িক ফ্যাক্ট = published knowledge ছাড়া কখনোই নয়।",
    "মেনু অর্ডার: Config → Knowledge → Playground → Dashboard। প্রতিটি পেজে নিজস্ব স্টেপ গাইড আছে।",
    "Auto-send এখন বন্ধ — Assist/Shadow: Wise সাজেস্ট করে, মানুষ পাঠায়।",
];

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
};

defineProps<{
    stats: {
        turns_today: number;
        turns_total: number;
        avg_confidence: number;
        active_keys: number;
        gaps_today: number;
        needs_human_today: number;
        published_knowledge: number;
    };
    recentTurns: TurnRow[];
}>();

type PipelineLayer = {
    name: string;
    hint: string;
    icon: IconName;
    status: string;
    variant: "success" | "warning" | "neutral";
};

const pipeline: PipelineLayer[] = [
    { name: "Admission", hint: "API key auth + sealed config snapshot", icon: "PhShieldCheck", status: "Live", variant: "success" },
    { name: "Perception", hint: "Pattern intent (social vs business)", icon: "PhEye", status: "v1", variant: "success" },
    { name: "Knowledge", hint: "Published FAQ/policy grounding", icon: "PhBooks", status: "v1", variant: "success" },
    { name: "Memory", hint: "Conversation / customer memory", icon: "PhDatabase", status: "Planned", variant: "neutral" },
    { name: "Judge", hint: "No evidence → needs_human + gap", icon: "PhCircuitry", status: "v1", variant: "success" },
    { name: "Language", hint: "Canned social; knowledge answers for business", icon: "PhChatCircleDots", status: "v1", variant: "success" },
    { name: "LLM (optional)", hint: "Last layer — admin-controlled", icon: "PhSparkle", status: "Off", variant: "warning" },
];
</script>
