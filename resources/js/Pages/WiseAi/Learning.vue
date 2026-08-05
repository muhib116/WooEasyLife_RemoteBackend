<template>
    <AuthenticatedLayout title="Wise AI — Learning">
        <div class="space-y-5">
            <PageHeader
                title="Learning"
                description="কাজের তালিকা — review suggestions, missed answers, language maps. Nothing auto-publishes."
                icon="PhTray"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <StatusBadge
                        :label="`${stats.open_total} open`"
                        :variant="stats.open_total > 0 ? 'warning' : 'success'"
                        format="none"
                    />
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Learning কীভাবে ব্যবহার করবেন"
                subtitle="এক জায়গায় — gap draft/publish · language · assist · seeded seeds"
                badge="মানুষ অনুমোদন"
                storage-key="learning"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <PageCard
                v-if="seededDrafts.length > 0"
                title="Seeded drafts (platform)"
                description="Catalog seeds — এখান থেকেই Publish, Knowledge পেজে যেতে হবে না"
            >
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <Button
                        v-if="can_publish"
                        label="Publish selected"
                        icon="pi pi-check"
                        size="small"
                        severity="success"
                        :disabled="selectedSeedIds.length === 0"
                        :loading="bulkPublishing"
                        @click="bulkPublishSeeds"
                    />
                    <span class="text-xs text-gray-500">{{ seededDrafts.length }} draft(s)</span>
                </div>
                <DataTable
                    v-model:selection="selectedSeeds"
                    :value="seededDrafts"
                    data-key="id"
                    size="small"
                    striped-rows
                    class="professional-table"
                >
                    <Column selection-mode="multiple" header-style="width: 3rem" />
                    <Column header="Title">
                        <template #body="{ data }">
                            <div class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ data.title }}</div>
                            <div class="text-[11px] text-gray-400">{{ data.seeded_from || "seed" }} · {{ data.scope }}</div>
                        </template>
                    </Column>
                    <Column header="Answer">
                        <template #body="{ data }">
                            <p class="max-w-md truncate text-xs text-gray-500" :title="data.answer">{{ data.answer }}</p>
                        </template>
                    </Column>
                </DataTable>
            </PageCard>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <Link
                    v-for="tab in kindTabs"
                    :key="tab.value"
                    :href="route('wiseAi.learning', { kind: tab.value })"
                    class="rounded-2xl border p-3.5 transition-shadow hover:shadow-md"
                    :class="
                        kind === tab.value
                            ? 'border-violet-300 bg-violet-50/80 dark:border-violet-500/40 dark:bg-violet-500/10'
                            : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-slate-900/60'
                    "
                >
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ tab.label }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white">{{ tab.count }}</p>
                </Link>
            </div>

            <PageCard title="Queue" :description="listHint" :no-padding="items.length > 0">
                <DataTable v-if="items.length" :value="items" size="small" striped-rows class="professional-table">
                    <Column header="Kind" style="width: 7rem">
                        <template #body="{ data }">
                            <StatusBadge :label="data.kind" :variant="kindVariant(data.kind)" format="none" />
                        </template>
                    </Column>
                    <Column header="Item">
                        <template #body="{ data }">
                            <div class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ data.title }}</div>
                            <div class="text-[11px] text-gray-400">
                                {{ data.detail }}
                                <span v-if="data.key_name"> · {{ data.key_name }}</span>
                            </div>
                            <p
                                v-if="data.suggested_reply"
                                class="mt-1 max-w-md truncate text-[11px] text-gray-500"
                                :title="data.suggested_reply"
                            >
                                → {{ data.suggested_reply }}
                            </p>
                            <div v-if="data.psych" class="mt-1 flex flex-wrap gap-1">
                                <StatusBadge
                                    :label="data.psych.priority || 'normal'"
                                    :variant="data.psych.priority === 'critical' ? 'danger' : data.psych.priority === 'high' ? 'warning' : 'neutral'"
                                    format="none"
                                />
                                <StatusBadge :label="data.psych.emotion || '?'" variant="info" format="none" />
                                <span
                                    v-for="op in (data.opportunities || []).slice(0, 1)"
                                    :key="op.id"
                                    class="text-[10px] text-indigo-600 dark:text-indigo-300"
                                >
                                    {{ op.title }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="When" style="width: 9rem">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-500">{{ data.occurred_at }}</span>
                        </template>
                    </Column>
                    <Column header="Action" style="width: 14rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Button
                                    v-if="data.turn_id"
                                    label="Replay"
                                    size="small"
                                    text
                                    severity="help"
                                    @click="openReplay(data.turn_id)"
                                />
                                <template v-if="data.kind === 'gap'">
                                    <Button
                                        v-if="can_edit"
                                        :label="data.auto_draft_id ? 'Review draft' : 'Draft FAQ'"
                                        size="small"
                                        text
                                        @click="openDraft(data)"
                                    />
                                    <Button
                                        v-if="can_edit"
                                        label="Ignore"
                                        size="small"
                                        text
                                        severity="secondary"
                                        :loading="busyUid === data.uid"
                                        @click="ignoreGap(data)"
                                    />
                                </template>
                                <template v-else-if="data.kind === 'language'">
                                    <Button
                                        v-if="can_edit"
                                        label="Create entry"
                                        size="small"
                                        text
                                        @click="openPromote(data)"
                                    />
                                    <Button
                                        v-if="can_edit"
                                        label="Ignore"
                                        size="small"
                                        text
                                        severity="secondary"
                                        :loading="busyUid === data.uid"
                                        @click="ignoreLanguage(data)"
                                    />
                                    <span v-else class="text-[11px] text-gray-400">Editor required</span>
                                </template>
                                <template v-else-if="data.kind === 'assist'">
                                    <Button
                                        label="Approve"
                                        size="small"
                                        text
                                        severity="success"
                                        :loading="busyUid === data.uid && busyAction === 'approved'"
                                        @click="assistReview(data, 'approved')"
                                    />
                                    <Button label="Edit…" size="small" text @click="openEdit(data)" />
                                    <Button
                                        label="Reject…"
                                        size="small"
                                        text
                                        severity="danger"
                                        @click="openReject(data)"
                                    />
                                </template>
                                <template v-else>
                                    <StatusBadge
                                        :label="data.reason_label || data.reason_code || 'reject'"
                                        variant="danger"
                                        format="none"
                                    />
                                    <Link
                                        v-if="data.reason_code === 'missing_knowledge'"
                                        :href="route('wiseAi.learning', { kind: 'gap' })"
                                        class="text-[11px] font-medium text-fuchsia-600 hover:underline"
                                    >
                                        Missed answers →
                                    </Link>
                                </template>
                            </div>
                        </template>
                    </Column>
                </DataTable>
                <EmptyState
                    v-else
                    icon="PhTray"
                    title="Inbox খালি"
                    description="Open gap, unknown token, বা pending suggestion থাকলে এখানে দেখাবে"
                />
            </PageCard>
        </div>

        <Dialog v-model:visible="showEdit" header="Edit suggested reply" modal :style="{ width: '32rem' }" dismissable-mask>
            <div v-if="active" class="space-y-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">Customer: “{{ active.title }}”</p>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Your reply</label>
                    <BanglaField v-model="editedReply" multiline :rows="4" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Reason (optional)</label>
                    <Select
                        v-model="editReason"
                        :options="reason_codes"
                        option-label="label"
                        option-value="value"
                        show-clear
                        placeholder="e.g. tone / language"
                        class="w-full"
                    />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined size="small" @click="showEdit = false" />
                <Button
                    label="Save edited review"
                    size="small"
                    :disabled="!editedReply.trim()"
                    :loading="busyAction === 'edited'"
                    @click="submitEdit"
                />
            </template>
        </Dialog>

        <Dialog v-model:visible="showReject" header="Reject — pick a reason" modal :style="{ width: '28rem' }" dismissable-mask>
            <div v-if="active" class="space-y-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">“{{ active.title }}”</p>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Reason code</label>
                    <Select
                        v-model="rejectCode"
                        :options="reason_codes"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                        placeholder="Why is this wrong?"
                    />
                </div>
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

        <Dialog v-model:visible="showPromote" header="Create language entry" modal :style="{ width: '28rem' }" dismissable-mask>
            <div v-if="active" class="space-y-3">
                <p class="text-sm">
                    Token: <span class="font-mono font-semibold">{{ active.title }}</span>
                </p>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Type</label>
                    <Select v-model="promoteForm.type" :options="langTypes" option-label="label" option-value="value" class="w-full" />
                </div>
                <div v-if="promoteForm.type !== 'filler'">
                    <label class="mb-1 block text-xs text-gray-500">Canonical / to_text</label>
                    <BanglaField v-model="promoteForm.to_text" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Scope</label>
                    <Select
                        v-model="promoteForm.scope"
                        :options="langScopes"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined size="small" @click="showPromote = false" />
                <Button label="Publish entry" size="small" :loading="busyAction === 'promote'" @click="submitPromote" />
            </template>
        </Dialog>

        <Dialog
            v-model:visible="showDraft"
            header="Gap → knowledge (in place)"
            modal
            :style="{ width: '36rem' }"
            dismissable-mask
        >
            <div v-if="active" class="space-y-3">
                <div class="rounded-xl border border-rose-100 bg-rose-50/70 px-3 py-2 text-sm dark:border-rose-500/20 dark:bg-rose-500/10">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-500">Customer asked</p>
                    <p class="mt-0.5 text-gray-800 dark:text-gray-100">“{{ active.title }}”</p>
                    <p v-if="active.auto_draft_id" class="mt-1 text-[11px] text-rose-600 dark:text-rose-300">
                        Auto-draft #{{ active.auto_draft_id }} ready — review, then Save / Publish (never silent).
                    </p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs text-gray-500">Audience</label>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            label="গ্লোবাল (Platform)"
                            size="small"
                            :outlined="draftAudience !== 'platform'"
                            :disabled="Boolean(active?.auto_draft_id)"
                            @click="setDraftAudience('platform')"
                        />
                        <Button
                            type="button"
                            label="এক দোকান (Merchant)"
                            size="small"
                            :outlined="draftAudience !== 'merchant'"
                            @click="setDraftAudience('merchant')"
                        />
                    </div>
                    <p v-if="active?.auto_draft_id" class="mt-1 text-[11px] text-gray-400">
                        Auto-draft stays merchant-scoped for this store key.
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Kind</label>
                    <Select v-model="draftForm.type" :options="draftTypes" option-label="label" option-value="value" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Title</label>
                    <BanglaField v-model="draftForm.title" />
                </div>
                <div v-if="draftForm.type === 'product'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">External ID</label>
                        <input
                            v-model="draftForm.external_id"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 font-mono text-sm outline-none dark:border-gray-700 dark:bg-slate-900"
                            placeholder="product / offer id"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Offer kind</label>
                        <Select
                            v-model="draftForm.offer_kind"
                            :options="offerKinds"
                            option-label="label"
                            option-value="value"
                            show-clear
                            class="w-full"
                        />
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Question</label>
                    <BanglaField v-model="draftForm.question" />
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label class="block text-xs text-gray-500">Answer</label>
                        <Button
                            v-if="can_edit"
                            label="AI rewrite"
                            size="small"
                            text
                            :loading="proposingAnswer"
                            :disabled="!draftForm.answer.trim()"
                            @click="proposeDraftAnswer"
                        />
                    </div>
                    <BanglaField
                        v-model="draftForm.answer"
                        multiline
                        :rows="4"
                        placeholder="Clarify / handoff — no invented fees"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Keywords (comma)</label>
                    <BanglaField v-model="keywordsText" />
                </div>
                <div v-if="relatedLoading || relatedItems.length" class="rounded-xl border border-gray-200 bg-gray-50/80 p-3 dark:border-gray-700 dark:bg-slate-800/40">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Related questions</p>
                    <p class="mt-0.5 text-[11px] text-gray-400">Published FAQs / sibling gaps — questions only, no invented answers</p>
                    <p v-if="relatedLoading" class="mt-2 text-xs text-gray-500">Loading…</p>
                    <ul v-else class="mt-2 max-h-36 space-y-1.5 overflow-y-auto">
                        <li v-for="(rq, idx) in relatedItems" :key="`${rq.knowledge_id || 'g'}-${idx}`" class="text-xs text-gray-700 dark:text-gray-200">
                            <button
                                type="button"
                                class="text-left font-medium text-fuchsia-700 hover:underline dark:text-fuchsia-300"
                                @click="applyRelatedQuestion(rq.question)"
                            >
                                {{ rq.question }}
                            </button>
                            <span class="text-gray-400"> · {{ rq.reason }}{{ rq.knowledge_id ? ` #${rq.knowledge_id}` : "" }}</span>
                        </li>
                    </ul>
                </div>
                <p class="text-xs text-gray-500">
                    Save = draft · Save &amp; Publish = লাইভ (publisher)। গ্লোবাল হলে API key লাগে না।
                </p>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined size="small" @click="showDraft = false" />
                <Button
                    label="Save as draft"
                    icon="pi pi-save"
                    size="small"
                    severity="secondary"
                    :loading="savingDraft && !publishingDraft"
                    :disabled="!canSaveDraft || savingDraft"
                    @click="saveDraft(false)"
                />
                <Button
                    v-if="can_publish"
                    label="Save & Publish"
                    icon="pi pi-check"
                    size="small"
                    severity="success"
                    :loading="publishingDraft"
                    :disabled="!canSaveDraft || savingDraft"
                    @click="saveDraft(true)"
                />
            </template>
        </Dialog>

        <TurnReplayDialog v-model:visible="replayOpen" :turn-id="replayTurnId" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import axios from "axios";
import { Link, router } from "@inertiajs/vue3";
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
import BanglaField from "@/components/BanglaField.vue";
import { feeInvented } from "@/utils/wiseFeeGuard";

type ReasonOpt = { value: string; label: string };
type LearningRow = {
    uid: string;
    kind: "gap" | "language" | "assist" | "reject";
    ref_id: number;
    turn_id?: number;
    key_name: string | null;
    title: string;
    detail: string;
    suggested_reply: string | null;
    auto_draft_id?: number | null;
    auto_draft_status?: string | null;
    auto_draft_title?: string | null;
    psych?: { emotion?: string; priority?: string; journey?: string } | null;
    opportunities?: { id: string; title: string }[];
    suggested_pack?: string | null;
    suggested_category?: string | null;
    rank_score?: number | null;
    reason_code: string | null;
    reason_label: string | null;
    occurred_at: string | null;
};
type SeededDraft = {
    id: number;
    title: string;
    answer: string;
    scope?: string;
    seeded_from?: string | null;
    bulk_eligible?: boolean;
};

const props = defineProps<{
    kind: string;
    stats: {
        gaps_open: number;
        language_open: number;
        assist_pending: number;
        rejects_recent: number;
        open_total: number;
    };
    items: LearningRow[];
    list_limit: number;
    reason_codes: ReasonOpt[];
    reason_codes_version: string;
    can_edit?: boolean;
    can_publish?: boolean;
    api_keys?: { id: number; name: string; key_prefix: string }[];
    seeded_drafts?: SeededDraft[];
}>();

const can_edit = props.can_edit !== false;
const can_publish = props.can_publish === true;
const toast = useToast();
const items = ref<LearningRow[]>([...props.items]);
const stats = ref({ ...props.stats });
const seededDrafts = ref<SeededDraft[]>([...(props.seeded_drafts || [])]);
const selectedSeeds = ref<SeededDraft[]>([]);
const selectedSeedIds = computed(() => selectedSeeds.value.map((s) => s.id));
const bulkPublishing = ref(false);
const busyUid = ref<string | null>(null);
const busyAction = ref<string | null>(null);
const showReject = ref(false);
const showEdit = ref(false);
const showPromote = ref(false);
const showDraft = ref(false);
const relatedLoading = ref(false);
const relatedItems = ref<{ question: string; knowledge_id?: number | null; reason: string }[]>([]);
const savingDraft = ref(false);
const publishingDraft = ref(false);
const proposingAnswer = ref(false);
const draftAudience = ref<"platform" | "merchant">("platform");
const keywordsText = ref("");
const replayOpen = ref(false);
const replayTurnId = ref<number | null>(null);
const active = ref<LearningRow | null>(null);
const draftForm = reactive({
    type: "faq",
    scope: "platform",
    title: "",
    question: "",
    answer: "",
    external_id: "",
    platform: "",
    offer_kind: null as string | null,
    sku: "",
});
const draftTypes = [
    { label: "FAQ (Q→A)", value: "faq" },
    { label: "Product / Offer", value: "product" },
    { label: "Policy", value: "policy" },
];
const offerKinds = [
    { label: "Physical", value: "physical" },
    { label: "Digital", value: "digital" },
    { label: "Service", value: "service" },
    { label: "Subscription", value: "subscription" },
];
const canSaveDraft = computed(() => {
    if (draftForm.title.trim() === "" || draftForm.answer.trim() === "") return false;
    if (draftForm.type === "product" && draftForm.external_id.trim() === "") return false;
    return true;
});

const openReplay = (turnId: number) => {
    replayTurnId.value = turnId;
    replayOpen.value = true;
};
const rejectCode = ref<string | null>(null);
const editReason = ref<string | null>(null);
const editedReply = ref("");
const promoteForm = reactive({
    type: "banglish",
    to_text: "",
    scope: "merchant",
    pack_slug: "core-bd",
    category: "banglish",
});

const langTypes = [
    { label: "Banglish", value: "banglish" },
    { label: "Abbrev", value: "abbrev" },
    { label: "SMS", value: "sms" },
    { label: "Phonetic", value: "phonetic" },
    { label: "Commerce", value: "commerce" },
    { label: "Messenger", value: "messenger" },
    { label: "Filler", value: "filler" },
];
const langScopes = [
    { label: "Merchant", value: "merchant" },
    { label: "Platform", value: "platform" },
];

const howToSteps = [
    {
        title: "এক Inbox-এ সব শেখার কাজ",
        detail: "Gap = knowledge মিস · Language = unknown token · Assist = suggestion রিভিউ · Seeded = catalog draft publish।",
    },
    {
        title: "Gap থেকে Draft বা Save & Publish",
        detail: "গ্লোবাল (Platform) ডিফল্ট — API key লাগে না। AI rewrite দিয়ে উত্তর নরম করতে পারেন; fee invent হবে না।",
    },
    {
        title: "Publish = মানুষের অনুমোদন",
        detail: "Language promote বা Knowledge Publish ছাড়া production truth বদলায় না — কিন্তু Learning থেকেই করা যায়।",
    },
];

const howToTips = [
    "Draft FAQ এখান থেকেই Save & Publish — Knowledge পেজে যেতে হবে না।",
    "Seeded drafts স্ট্রিপ থেকে bulk Publish করুন।",
    "Auto-learn নেই। Reject-এ সবসময় reason বাছুন।",
    `Reason taxonomy v${props.reason_codes_version}.`,
];

const kindTabs = computed(() => [
    { value: "all", label: "All", count: stats.value.open_total },
    { value: "assist", label: "Needs reply", count: stats.value.assist_pending },
    { value: "gap", label: "Missed answers", count: stats.value.gaps_open },
    { value: "language", label: "Language", count: stats.value.language_open },
    { value: "reject", label: "Rejected", count: stats.value.rejects_recent },
]);

const listHint = computed(() => {
    return `Approve · Draft FAQ · Promote language · Ignore. Showing up to ${props.list_limit}.`;
});

const kindVariant = (kind: string) => {
    if (kind === "gap") return "danger";
    if (kind === "language") return "warning";
    if (kind === "assist") return "success";
    return "neutral";
};

const removeUid = (uid: string) => {
    items.value = items.value.filter((i) => i.uid !== uid);
};

const openDraft = (row: LearningRow) => {
    active.value = row;
    const priceHint = /price|dam|দাম/i.test(`${row.title} ${row.detail}`);
    draftAudience.value = row.auto_draft_id ? "merchant" : "platform";
    draftForm.type = priceHint ? "product" : "faq";
    draftForm.scope = draftAudience.value === "platform" ? "platform" : "merchant";
    draftForm.title = row.auto_draft_title
        || (priceHint ? `Offer — ${row.title.slice(0, 40)}` : row.title.slice(0, 80) || "FAQ");
    draftForm.question = row.title || "";
    draftForm.answer = (row.suggested_reply || "").trim();
    draftForm.external_id = "";
    draftForm.platform = "";
    draftForm.offer_kind = priceHint ? "physical" : null;
    draftForm.sku = "";
    keywordsText.value = "";
    relatedItems.value = [];
    showDraft.value = true;
    void loadRelatedQuestions(row.turn_id || row.ref_id);
};

const loadRelatedQuestions = async (turnId: number) => {
    if (!turnId) return;
    relatedLoading.value = true;
    try {
        const { data } = await axios.get(route("wiseAi.turns.relatedQuestions", { turn: turnId }));
        relatedItems.value = Array.isArray(data?.items) ? data.items : [];
    } catch {
        relatedItems.value = [];
    } finally {
        relatedLoading.value = false;
    }
};

const applyRelatedQuestion = (question: string) => {
    draftForm.question = question;
    if (!draftForm.title.trim() || draftForm.title.startsWith("Gap ·") || draftForm.title === "FAQ") {
        draftForm.title = question.slice(0, 80);
    }
};

const setDraftAudience = (next: "platform" | "merchant") => {
    draftAudience.value = next;
    draftForm.scope = next === "platform" ? "platform" : "merchant";
};

const proposeDraftAnswer = async () => {
    if (!draftForm.answer.trim() || proposingAnswer.value) return;
    if (feeInvented(draftForm.answer)) {
        toast.add({
            severity: "warn",
            summary: "Fee digits detected",
            detail: "digit / টাকা / phone / % সরিয়ে নিন — Evidence First।",
            life: 4000,
            group: "br",
        });
        return;
    }
    proposingAnswer.value = true;
    try {
        const { data } = await axios.post(route("wiseAi.knowledge.proposeAnswer"), {
            title: draftForm.title,
            question: draftForm.question,
            answer: draftForm.answer,
            scope: draftForm.scope,
        });
        if (data.proposed_answer) {
            draftForm.answer = data.proposed_answer;
            toast.add({ severity: "success", summary: "AI rewrite ready — review before publish", life: 2500, group: "br" });
        }
    } catch (e: unknown) {
        const msg =
            typeof e === "object" && e && "response" in e
                ? (e as { response?: { data?: { message?: string } } }).response?.data?.message
                : null;
        toast.add({
            severity: "error",
            summary: msg || "AI rewrite failed",
            life: 4000,
            group: "br",
        });
    } finally {
        proposingAnswer.value = false;
    }
};

const saveDraft = async (publishNow = false) => {
    if (!active.value || !canSaveDraft.value || savingDraft.value) return;
    if (feeInvented(draftForm.answer)) {
        toast.add({
            severity: "warn",
            summary: "Invented fee blocked",
            detail: "digit / টাকা / phone / % সরিয়ে নিন — Evidence First।",
            life: 4000,
            group: "br",
        });
        return;
    }
    savingDraft.value = true;
    publishingDraft.value = publishNow;
    try {
        const keywords = keywordsText.value
            .split(",")
            .map((k) => k.trim())
            .filter(Boolean);
        const { data } = await axios.post(route("wiseAi.gaps.draft", { turn: active.value.ref_id }), {
            ...draftForm,
            scope: draftAudience.value === "platform" ? "platform" : "merchant",
            keywords,
            publish_now: publishNow,
        });
        removeUid(active.value.uid);
        stats.value.gaps_open = Math.max(0, stats.value.gaps_open - 1);
        stats.value.open_total = Math.max(0, stats.value.open_total - 1);
        showDraft.value = false;
        toast.add({
            severity: "success",
            summary: data.published ? "Published" : "Draft saved",
            detail: data.published ? "Live for decide grounding" : "Stays draft until Publish",
            life: 3500,
            group: "br",
        });
    } catch {
        toast.add({ severity: "error", summary: "Could not save draft", life: 3500, group: "br" });
    } finally {
        savingDraft.value = false;
        publishingDraft.value = false;
    }
};

const bulkPublishSeeds = async () => {
    if (!can_publish || selectedSeedIds.value.length === 0 || bulkPublishing.value) return;
    bulkPublishing.value = true;
    try {
        const { data } = await axios.post(route("wiseAi.knowledge.bulkPublish"), {
            ids: selectedSeedIds.value,
        });
        const publishedCount = Number(data.published_count ?? 0);
        const skippedCount = Number(data.skipped_count ?? 0);
        const publishedIds = new Set<number>(
            (data.items || []).map((i: { id: number }) => i.id),
        );
        if (publishedCount === 0 || publishedIds.size === 0) {
            toast.add({
                severity: "warn",
                summary: "Nothing published",
                detail:
                    skippedCount > 0
                        ? `${skippedCount} selected item(s) were not eligible (already live, not seeded, or missing).`
                        : "No eligible seeded drafts in this selection.",
                life: 4500,
                group: "br",
            });
            return;
        }
        seededDrafts.value = seededDrafts.value.filter((s) => !publishedIds.has(s.id));
        selectedSeeds.value = [];
        toast.add({
            severity: "success",
            summary: `Published ${publishedCount} seed(s)` + (skippedCount ? ` · skipped ${skippedCount}` : ""),
            life: 3000,
            group: "br",
        });
    } catch {
        toast.add({ severity: "error", summary: "Bulk publish failed", life: 3500, group: "br" });
    } finally {
        bulkPublishing.value = false;
    }
};

const ignoreGap = async (row: LearningRow) => {
    busyUid.value = row.uid;
    try {
        await axios.post(route("wiseAi.gaps.ignore", { turn: row.ref_id }));
        removeUid(row.uid);
        stats.value.gaps_open = Math.max(0, stats.value.gaps_open - 1);
        stats.value.open_total = Math.max(0, stats.value.open_total - 1);
        toast.add({ severity: "success", summary: "Gap ignored", life: 2000, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Ignore failed", life: 3000, group: "br" });
    } finally {
        busyUid.value = null;
    }
};

const ignoreLanguage = async (row: LearningRow) => {
    busyUid.value = row.uid;
    try {
        await axios.post(route("wiseAi.language.reviews.ignore", { review: row.ref_id }));
        removeUid(row.uid);
        stats.value.language_open = Math.max(0, stats.value.language_open - 1);
        stats.value.open_total = Math.max(0, stats.value.open_total - 1);
        toast.add({ severity: "success", summary: "Language ignored", life: 2000, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Ignore failed", life: 3000, group: "br" });
    } finally {
        busyUid.value = null;
    }
};

const openPromote = (row: LearningRow) => {
    active.value = row;
    const cat = row.suggested_category || "banglish";
    promoteForm.type = ["abbrev", "sms", "banglish", "phonetic", "commerce", "filler", "messenger"].includes(cat)
        ? cat
        : "banglish";
    promoteForm.to_text = "";
    promoteForm.scope = "merchant";
    promoteForm.pack_slug = row.suggested_pack || "core-bd";
    promoteForm.category = cat;
    showPromote.value = true;
};

const submitPromote = async () => {
    if (!active.value) return;
    busyAction.value = "promote";
    try {
        await axios.post(route("wiseAi.language.reviews.promote", { review: active.value.ref_id }), {
            ...promoteForm,
            to_text: promoteForm.type === "filler" ? null : promoteForm.to_text,
        });
        removeUid(active.value.uid);
        stats.value.language_open = Math.max(0, stats.value.language_open - 1);
        stats.value.open_total = Math.max(0, stats.value.open_total - 1);
        showPromote.value = false;
        toast.add({ severity: "success", summary: "Language entry published", life: 2500, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Promote failed", life: 3000, group: "br" });
    } finally {
        busyAction.value = null;
    }
};

const assistReview = async (row: LearningRow, outcome: "approved") => {
    busyUid.value = row.uid;
    busyAction.value = outcome;
    try {
        const { data } = await axios.post(route("wiseAi.assist.feedback", { turn: row.ref_id }), { outcome });
        removeUid(row.uid);
        if (data.learning_stats) stats.value = data.learning_stats;
        else {
            stats.value.assist_pending = Math.max(0, stats.value.assist_pending - 1);
            stats.value.open_total = Math.max(0, stats.value.open_total - 1);
        }
        toast.add({ severity: "success", summary: "Approved", life: 2000, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Review failed", life: 3000, group: "br" });
    } finally {
        busyUid.value = null;
        busyAction.value = null;
    }
};

const openEdit = (row: LearningRow) => {
    active.value = row;
    editedReply.value = row.suggested_reply || "";
    editReason.value = "language";
    showEdit.value = true;
};

const submitEdit = async () => {
    if (!active.value || !editedReply.value.trim()) return;
    busyAction.value = "edited";
    busyUid.value = active.value.uid;
    try {
        const { data } = await axios.post(route("wiseAi.assist.feedback", { turn: active.value.ref_id }), {
            outcome: "edited",
            edited_reply: editedReply.value.trim(),
            reason_code: editReason.value || "assist_edit",
        });
        removeUid(active.value.uid);
        if (data.learning_stats) stats.value = data.learning_stats;
        else {
            stats.value.assist_pending = Math.max(0, stats.value.assist_pending - 1);
            stats.value.open_total = Math.max(0, stats.value.open_total - 1);
        }
        showEdit.value = false;
        toast.add({ severity: "success", summary: "Edited review saved", life: 2500, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Edit failed", life: 3000, group: "br" });
    } finally {
        busyUid.value = null;
        busyAction.value = null;
    }
};

const openReject = (row: LearningRow) => {
    active.value = row;
    rejectCode.value = props.reason_codes[0]?.value ?? null;
    showReject.value = true;
};

const submitReject = async () => {
    if (!active.value || !rejectCode.value) return;
    busyAction.value = "rejected";
    busyUid.value = active.value.uid;
    try {
        const { data } = await axios.post(route("wiseAi.assist.feedback", { turn: active.value.ref_id }), {
            outcome: "rejected",
            reason_code: rejectCode.value,
        });
        removeUid(active.value.uid);
        if (data.learning_stats) stats.value = data.learning_stats;
        else {
            stats.value.assist_pending = Math.max(0, stats.value.assist_pending - 1);
            stats.value.open_total = Math.max(0, stats.value.open_total - 1);
            stats.value.rejects_recent += 1;
        }
        showReject.value = false;
        toast.add({ severity: "success", summary: "Rejected with reason", life: 2500, group: "br" });
        if (props.kind === "reject") router.reload({ only: ["items", "stats"] });
    } catch {
        toast.add({ severity: "error", summary: "Reject failed — pick a valid reason", life: 3500, group: "br" });
    } finally {
        busyUid.value = null;
        busyAction.value = null;
    }
};
</script>
