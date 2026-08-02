<template>
    <AuthenticatedLayout title="Wise AI — Assist">
        <div class="space-y-5">
            <PageHeader
                title="Assist"
                description="Wise সাজেস্ট করে — মানুষ Approve / Edit / Reject করে। Auto-send নেই।"
                icon="PhHandPalm"
                icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                icon-class="text-amber-600 dark:text-amber-400"
            >
                <template #actions>
                    <StatusBadge
                        :label="`${localStats.pending} pending`"
                        :variant="localStats.pending > 0 ? 'warning' : 'success'"
                        format="none"
                    />
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Assist কীভাবে ব্যবহার করবেন"
                subtitle="Human-in-Control: suggestion → merchant review → (পরে) send"
                badge="রিভিউ"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Link
                    v-for="tab in filterTabs"
                    :key="tab.value"
                    :href="route('wiseAi.assist', { filter: tab.value })"
                    class="rounded-2xl border p-4 transition-shadow hover:shadow-md"
                    :class="
                        filter === tab.value
                            ? 'border-amber-300 bg-amber-50/80 dark:border-amber-500/40 dark:bg-amber-500/10'
                            : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-slate-900/60'
                    "
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ tab.label }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white">{{ tab.count }}</p>
                </Link>
            </div>

            <PageCard title="Suggested replies" :description="listHint" :no-padding="items.length > 0">
                <DataTable v-if="items.length" :value="items" size="small" striped-rows class="professional-table">
                    <Column header="Customer">
                        <template #body="{ data }">
                            <span class="block max-w-[220px] text-sm font-medium" :title="data.text">
                                {{ data.text || "—" }}
                            </span>
                            <span class="text-[11px] text-gray-400">
                                #{{ data.id }} · {{ data.key_name || "key" }} · {{ data.channel }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Suggestion">
                        <template #body="{ data }">
                            <span class="block max-w-[280px] text-sm" :title="data.suggested_reply || ''">
                                {{ data.suggested_reply || "—" }}
                            </span>
                            <span class="text-[11px] text-gray-400">
                                {{ data.intent || "?" }} · {{ data.confidence ?? 0 }}% · {{ data.source || "—" }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Coach" style="width: 12rem">
                        <template #body="{ data }">
                            <div v-if="data.psych" class="space-y-1">
                                <div class="flex flex-wrap gap-1">
                                    <StatusBadge
                                        :label="data.psych.priority || 'normal'"
                                        :variant="priorityVariant(data.psych.priority)"
                                        format="none"
                                    />
                                    <StatusBadge :label="data.psych.emotion || '?'" variant="info" format="none" />
                                </div>
                                <p class="text-[10px] text-gray-400">
                                    {{ data.psych.journey }} · {{ data.psych.style_hint }}
                                </p>
                                <p
                                    v-for="op in (data.opportunities?.items || []).slice(0, 2)"
                                    :key="op.id"
                                    class="text-[10px] text-indigo-600 dark:text-indigo-300"
                                    :title="op.reason"
                                >
                                    → {{ op.title }}
                                </p>
                            </div>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </Column>
                    <Column header="Review">
                        <template #body="{ data }">
                            <StatusBadge
                                v-if="!data.reviewed"
                                label="pending"
                                variant="warning"
                                format="none"
                            />
                            <div v-else class="space-y-0.5">
                                <StatusBadge :label="data.feedback_outcome || 'done'" variant="success" format="none" />
                                <p
                                    v-if="data.feedback_outcome === 'edited' && data.feedback_edited_reply"
                                    class="max-w-[200px] truncate text-[11px] text-gray-400"
                                    :title="data.feedback_edited_reply"
                                >
                                    {{ data.feedback_edited_reply }}
                                </p>
                            </div>
                        </template>
                    </Column>
                    <Column header="When">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-500">{{ data.created_at }}</span>
                        </template>
                    </Column>
                    <Column header="Action">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Button
                                    label="Replay"
                                    size="small"
                                    text
                                    severity="help"
                                    @click="openReplay(data.id)"
                                />
                                <template v-if="!data.reviewed">
                                    <Button
                                        label="Approve"
                                        icon="pi pi-check"
                                        size="small"
                                        severity="success"
                                        text
                                        :loading="busyId === data.id && busyAction === 'approved'"
                                        :disabled="busyId === data.id"
                                        @click="review(data, 'approved')"
                                    />
                                    <Button
                                        label="Edit"
                                        icon="pi pi-pencil"
                                        size="small"
                                        text
                                        :disabled="busyId === data.id"
                                        @click="openEdit(data)"
                                    />
                                    <Button
                                        label="Reject…"
                                        icon="pi pi-times"
                                        size="small"
                                        severity="danger"
                                        text
                                        :disabled="busyId === data.id"
                                        @click="openReject(data)"
                                    />
                                </template>
                            </div>
                        </template>
                    </Column>
                </DataTable>
                <EmptyState
                    v-else
                    icon="PhHandPalm"
                    :title="emptyTitle"
                    :description="emptyDescription"
                >
                    <Link
                        :href="route('wiseAi.playground')"
                        class="mt-3 inline-flex text-sm font-medium text-fuchsia-600 hover:underline dark:text-fuchsia-400"
                    >
                        Playground-এ suggestion তৈরি করুন →
                    </Link>
                </EmptyState>
            </PageCard>
        </div>

        <Dialog
            v-model:visible="showReject"
            header="Reject — pick a reason"
            modal
            :style="{ width: '28rem' }"
            dismissable-mask
        >
            <div v-if="activeItem" class="space-y-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">“{{ activeItem.text }}”</p>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Reason code (Learning)</label>
                    <Select
                        v-model="rejectCode"
                        :options="reasonOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
                <Link
                    :href="route('wiseAi.learning', { kind: 'assist' })"
                    class="text-xs font-medium text-fuchsia-600 hover:underline"
                >
                    Open Learning Inbox →
                </Link>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined size="small" @click="showReject = false" />
                <Button
                    label="Reject"
                    severity="danger"
                    size="small"
                    :disabled="!rejectCode"
                    :loading="busyAction === 'rejected'"
                    @click="submitReject"
                />
            </template>
        </Dialog>

        <Dialog
            v-model:visible="showEdit"
            header="Edit suggested reply"
            modal
            :style="{ width: '36rem' }"
            dismissable-mask
        >
            <div v-if="activeItem" class="space-y-3">
                <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-sm dark:border-gray-800 dark:bg-slate-800/50">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Customer</p>
                    <p class="mt-0.5 text-gray-800 dark:text-gray-100">“{{ activeItem.text }}”</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Your reply (will be logged as edited)</label>
                    <textarea
                        v-model="editedReply"
                        rows="4"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                    />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined size="small" @click="showEdit = false" />
                <Button
                    label="Save edited review"
                    icon="pi pi-check"
                    size="small"
                    :loading="busyAction === 'edited'"
                    :disabled="!editedReply.trim()"
                    @click="submitEdit"
                />
            </template>
        </Dialog>

        <TurnReplayDialog v-model:visible="replayOpen" :turn-id="replayTurnId" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import Dialog from "primevue/dialog";
import Select from "primevue/select";
import { useToast } from "primevue/usetoast";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";
import WiseAiHowTo from "./fragments/WiseAiHowTo.vue";
import TurnReplayDialog from "./fragments/TurnReplayDialog.vue";

type AssistRow = {
    id: number;
    key_name: string | null;
    channel: string;
    text: string | null;
    intent: string | null;
    confidence: number | null;
    source: string | null;
    suggested_reply: string | null;
    psych: {
        emotion?: string;
        journey?: string;
        priority?: string;
        style_hint?: string;
    } | null;
    opportunities: { items?: { id: string; title: string; reason: string }[] } | null;
    reviewed: boolean;
    feedback_outcome: string | null;
    feedback_edited_reply: string | null;
    created_at: string | null;
};

const props = defineProps<{
    filter: "pending" | "reviewed" | "all";
    stats: { pending: number; reviewed: number; all: number };
    items: AssistRow[];
    list_limit: number;
}>();

const toast = useToast();
const items = ref<AssistRow[]>([...props.items]);
const localStats = ref({ ...props.stats });
const showEdit = ref(false);
const replayOpen = ref(false);
const replayTurnId = ref<number | null>(null);
const openReplay = (turnId: number) => {
    replayTurnId.value = turnId;
    replayOpen.value = true;
};
const showReject = ref(false);
const activeItem = ref<AssistRow | null>(null);
const editedReply = ref("");
const rejectCode = ref<string | null>("wrong_fact");
const busyId = ref<number | null>(null);
const busyAction = ref<"approved" | "rejected" | "edited" | null>(null);

const reasonOptions = [
    { value: "wrong_fact", label: "Wrong fact / answer" },
    { value: "wrong_offer", label: "Wrong offer / product" },
    { value: "missing_knowledge", label: "Missing knowledge (should be a gap)" },
    { value: "outdated", label: "Outdated knowledge" },
    { value: "tone", label: "Tone / voice wrong" },
    { value: "language", label: "Language / wording wrong" },
    { value: "policy", label: "Policy / safety concern" },
    { value: "other", label: "Other" },
];

const priorityVariant = (p?: string): "danger" | "warning" | "neutral" => {
    if (p === "critical") return "danger";
    if (p === "high") return "warning";
    return "neutral";
};

const howToSteps = [
    {
        title: "Pending-এ Wise-এর suggested reply দেখুন",
        detail: "Playground বা API থেকে suggest_reply এলে এখানে জমা হয়। Gap/needs_human এখানে আসে না — সেগুলো Gaps মেনুতে।",
    },
    {
        title: "Approve = ঠিক আছে · Reject = ভুল · Edit = নিজের টেক্সট",
        detail: "প্রতিটি রিভিউ wise_feedback-এ লগ হয় (Learning Lifecycle)। Silent auto-learn নেই।",
    },
    {
        title: "এখনো Auto-send নেই",
        detail: "Assist মানে মানুষ রিভিউ করে; পাঠানোর চ্যানেল (Messenger ইত্যাদি) পরে adapter দিয়ে যুক্ত হবে।",
    },
];

const howToTips = [
    "Approve/Reject/Edit API key ছাড়াই admin session দিয়ে কাজ করে — Playground feedback-এর মতোই outcome লগ।",
    "একবার রিভিউ হলে আবার করা যায় না (এই ভার্সনে)। ভুল হলে নতুন turn টেস্ট করুন।",
    "Knowledge gap থাকলে আগে Gaps → Publish, তারপর Assist-এ ভালো suggestion আসবে।",
];

const filterTabs = computed(() => [
    { value: "pending", label: "Pending", count: localStats.value.pending },
    { value: "reviewed", label: "Reviewed", count: localStats.value.reviewed },
    { value: "all", label: "All suggestions", count: localStats.value.all },
]);

const listHint = computed(() => {
    const base = "শুধু action = suggest_reply। Review = মানুষের সিদ্ধান্ত লগ।";
    if (localStats.value.all > props.list_limit) {
        return `${base} Showing latest ${props.list_limit} of ${localStats.value.all}.`;
    }
    return base;
});

const emptyTitle = computed(() =>
    props.filter === "pending" ? "কোনো pending suggestion নেই" : "এই ফিল্টারে কিছু নেই",
);

const emptyDescription = computed(() =>
    props.filter === "pending"
        ? "hi বা published FAQ প্রশ্ন Playground-এ পাঠালে এখানে আসবে"
        : "অন্য ট্যাব চেষ্টা করুন",
);

const applyReviewed = (turn: AssistRow) => {
    const idx = items.value.findIndex((i) => i.id === turn.id);
    if (idx < 0) return;
    if (props.filter === "pending") {
        items.value.splice(idx, 1);
    } else {
        items.value[idx] = turn;
    }
};

const review = async (row: AssistRow, outcome: "approved") => {
    if (busyId.value === row.id) return;
    busyId.value = row.id;
    busyAction.value = outcome;
    try {
        const { data } = await axios.post(route("wiseAi.assist.feedback", { turn: row.id }), {
            outcome,
        });
        if (data.stats) localStats.value = data.stats;
        applyReviewed(data.turn);
        toast.add({ severity: "success", summary: "Approved", life: 2500, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Review failed", life: 3500, group: "br" });
    } finally {
        busyId.value = null;
        busyAction.value = null;
    }
};

const openReject = (row: AssistRow) => {
    activeItem.value = row;
    rejectCode.value = "wrong_fact";
    showReject.value = true;
};

const submitReject = async () => {
    if (!activeItem.value || !rejectCode.value) return;
    busyId.value = activeItem.value.id;
    busyAction.value = "rejected";
    try {
        const { data } = await axios.post(route("wiseAi.assist.feedback", { turn: activeItem.value.id }), {
            outcome: "rejected",
            reason_code: rejectCode.value,
        });
        if (data.stats) localStats.value = data.stats;
        applyReviewed(data.turn);
        showReject.value = false;
        toast.add({ severity: "success", summary: "Rejected with reason", life: 2500, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Reject failed", life: 3500, group: "br" });
    } finally {
        busyId.value = null;
        busyAction.value = null;
    }
};

const openEdit = (row: AssistRow) => {
    activeItem.value = row;
    editedReply.value = row.suggested_reply || "";
    showEdit.value = true;
};

const submitEdit = async () => {
    if (!activeItem.value || !editedReply.value.trim()) return;
    busyId.value = activeItem.value.id;
    busyAction.value = "edited";
    try {
        const { data } = await axios.post(route("wiseAi.assist.feedback", { turn: activeItem.value.id }), {
            outcome: "edited",
            edited_reply: editedReply.value.trim(),
        });
        if (data.stats) localStats.value = data.stats;
        applyReviewed(data.turn);
        showEdit.value = false;
        toast.add({ severity: "success", summary: "Edited review saved", life: 2500, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Edit review failed", life: 3500, group: "br" });
    } finally {
        busyId.value = null;
        busyAction.value = null;
    }
};
</script>
