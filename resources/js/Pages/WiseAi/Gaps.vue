<template>
    <AuthenticatedLayout title="Wise AI — Gaps">
        <div class="space-y-5">
            <PageHeader
                title="Gap Inbox"
                description="Knowledge miss লগ — এখান থেকে draft FAQ বানিয়ে Publish করলে brain শিখে"
                icon="PhWarning"
                icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                icon-class="text-rose-600 dark:text-rose-400"
            >
                <template #actions>
                    <StatusBadge
                        :label="`${localStats.open} open`"
                        :variant="localStats.open > 0 ? 'danger' : 'success'"
                        format="none"
                    />
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Gap Inbox কীভাবে ব্যবহার করবেন"
                subtitle="Learning Lifecycle: প্রশ্ন → gap → draft knowledge → Publish → ভালো উত্তর"
                badge="শেখা"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Link
                    v-for="tab in filterTabs"
                    :key="tab.value"
                    :href="route('wiseAi.gaps', { filter: tab.value })"
                    class="rounded-2xl border p-4 transition-shadow hover:shadow-md"
                    :class="
                        filter === tab.value
                            ? 'border-fuchsia-300 bg-fuchsia-50/80 dark:border-fuchsia-500/40 dark:bg-fuchsia-500/10'
                            : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-slate-900/60'
                    "
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ tab.label }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white">{{ tab.count }}</p>
                </Link>
            </div>

            <PageCard
                title="Knowledge gaps"
                :description="listHint"
                :no-padding="gaps.length > 0"
            >
                <DataTable v-if="gaps.length" :value="gaps" size="small" striped-rows class="professional-table">
                    <Column header="Customer message">
                        <template #body="{ data }">
                            <span class="block max-w-[280px] text-sm font-medium" :title="data.text">
                                {{ data.text || "—" }}
                            </span>
                            <span class="text-[11px] text-gray-400">
                                #{{ data.id }} · {{ data.key_name || "key" }} · {{ data.channel }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Intent">
                        <template #body="{ data }">
                            <StatusBadge :label="data.intent || 'unknown'" variant="neutral" format="none" />
                        </template>
                    </Column>
                    <Column header="Status">
                        <template #body="{ data }">
                            <StatusBadge
                                v-if="!data.gap_handled"
                                label="open"
                                variant="danger"
                                format="none"
                            />
                            <div v-else class="space-y-0.5">
                                <StatusBadge label="handled" variant="success" format="none" />
                                <p v-if="data.gap_knowledge_title" class="text-[11px] text-gray-400">
                                    {{ data.gap_knowledge_title }}
                                    <span v-if="data.gap_knowledge_status">({{ data.gap_knowledge_status }})</span>
                                </p>
                                <p v-else class="text-[11px] text-gray-400">Ignored</p>
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
                            <div v-if="!data.gap_handled" class="flex flex-wrap gap-1">
                                <Button
                                    v-if="can_edit"
                                    label="Draft FAQ"
                                    icon="pi pi-file-edit"
                                    size="small"
                                    text
                                    @click="openDraft(data)"
                                />
                                <Button
                                    label="Ignore"
                                    icon="pi pi-eye-slash"
                                    size="small"
                                    severity="secondary"
                                    text
                                    :loading="ignoringId === data.id"
                                    :disabled="ignoringId === data.id"
                                    @click="ignoreItem(data)"
                                />
                            </div>
                            <Link
                                v-else-if="data.gap_knowledge_id"
                                :href="route('wiseAi.knowledge')"
                                class="text-xs font-medium text-fuchsia-600 hover:underline dark:text-fuchsia-400"
                            >
                                Open Knowledge →
                            </Link>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </template>
                    </Column>
                </DataTable>
                <EmptyState
                    v-else
                    icon="PhWarning"
                    :title="emptyTitle"
                    :description="emptyDescription"
                >
                    <Link
                        :href="route('wiseAi.playground')"
                        class="mt-3 inline-flex text-sm font-medium text-fuchsia-600 hover:underline dark:text-fuchsia-400"
                    >
                        Playground-এ ব্যবসায়িক প্রশ্ন টেস্ট করুন →
                    </Link>
                </EmptyState>
            </PageCard>
        </div>

        <Dialog
            v-model:visible="showDraft"
            header="Gap থেকে draft knowledge"
            modal
            :style="{ width: '36rem' }"
            dismissable-mask
        >
            <div v-if="activeGap" class="space-y-3">
                <div class="rounded-xl border border-rose-100 bg-rose-50/70 px-3 py-2 text-sm dark:border-rose-500/20 dark:bg-rose-500/10">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-500">Customer asked</p>
                    <p class="mt-0.5 text-gray-800 dark:text-gray-100">“{{ activeGap.text }}”</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs text-gray-500">Kind</label>
                    <Select
                        v-model="draftForm.type"
                        :options="types"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Scope</label>
                    <Select
                        v-model="draftForm.scope"
                        :options="scopes"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Title</label>
                    <input
                        v-model="draftForm.title"
                        type="text"
                        class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                        placeholder="Delivery charge"
                    />
                </div>
                <div v-if="draftForm.type === 'product' || draftForm.scope === 'offer'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">External ID (context.product_id)</label>
                        <input
                            v-model="draftForm.external_id"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 font-mono text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="45 or svc-1"
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
                            placeholder="optional"
                            class="w-full"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Platform</label>
                        <input
                            v-model="draftForm.platform"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="custom / shopify"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">SKU (optional)</label>
                        <input
                            v-model="draftForm.sku"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                        />
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Question (match text)</label>
                    <input
                        v-model="draftForm.question"
                        type="text"
                        class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Answer (কাস্টমারকে যা বলবেন)</label>
                    <textarea
                        v-model="draftForm.answer"
                        rows="3"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                        placeholder="ঢাকায় ৬০ টাকা, ঢাকার বাইরে ১২০ টাকা।"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Keywords (comma)</label>
                    <input
                        v-model="keywordsText"
                        type="text"
                        class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                        placeholder="delivery, চার্জ"
                    />
                </div>
                <p class="text-xs text-amber-700 dark:text-amber-300">
                    Save = draft only. Brain ব্যবহার করবে Knowledge পেজে Publish করার পর।
                    Price gap হলে Type = Product + External ID দিন।
                </p>
            </div>

            <template #footer>
                <Button label="Cancel" severity="secondary" outlined size="small" @click="showDraft = false" />
                <Button
                    label="Save as draft"
                    icon="pi pi-save"
                    size="small"
                    :loading="saving"
                    :disabled="!canSaveDraft"
                    @click="saveDraft"
                />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
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

type GapRow = {
    id: number;
    wise_api_key_id: number;
    key_name: string | null;
    channel: string;
    text: string | null;
    intent: string | null;
    confidence: number | null;
    action: string | null;
    source: string | null;
    gap: boolean;
    gap_handled: boolean;
    gap_handled_at: string | null;
    gap_knowledge_id: number | null;
    gap_knowledge_title: string | null;
    gap_knowledge_status: string | null;
    created_at: string | null;
};

const props = defineProps<{
    filter: "open" | "handled" | "all";
    stats: { open: number; handled: number; all: number };
    gaps: GapRow[];
    list_limit: number;
    can_edit?: boolean;
}>();

const can_edit = props.can_edit !== false;

const toast = useToast();
const gaps = ref<GapRow[]>([...props.gaps]);
const localStats = ref({ ...props.stats });
const showDraft = ref(false);
const saving = ref(false);
const ignoringId = ref<number | null>(null);
const activeGap = ref<GapRow | null>(null);
const keywordsText = ref("");

const draftForm = reactive({
    type: "faq",
    scope: "merchant",
    title: "",
    question: "",
    answer: "",
    external_id: "",
    platform: "",
    offer_kind: null as string | null,
    sku: "",
});

const types = [
    { label: "FAQ (Q→A)", value: "faq" },
    { label: "Product / Offer", value: "product" },
    { label: "Policy", value: "policy" },
    { label: "Fact", value: "fact" },
    { label: "Script", value: "script" },
    { label: "Campaign", value: "campaign" },
];

const scopes = [
    { label: "Merchant", value: "merchant" },
    { label: "Offer", value: "offer" },
    { label: "Region", value: "region" },
    { label: "Platform", value: "platform" },
];

const offerKinds = [
    { label: "physical", value: "physical" },
    { label: "digital", value: "digital" },
    { label: "service", value: "service" },
    { label: "subscription", value: "subscription" },
    { label: "other", value: "other" },
];

const howToSteps = [
    {
        title: "Open tab-এ মিস হওয়া প্রশ্ন দেখুন",
        detail: "Playground বা API থেকে ব্যবসায়িক প্রশ্নে knowledge না থাকলে এখানে জমা হয়।",
    },
    {
        title: "Draft FAQ চাপুন → Answer লিখুন → Save",
        detail: "Question আগে থেকেই কাস্টমারের মেসেজ দিয়ে ভরা থাকে। Save = draft (এখনো লাইভ নয়)।",
    },
    {
        title: "Knowledge পেজে গিয়ে Publish",
        detail: "Publish = মানুষের অনুমোদন। এরপর একই প্রশ্ন Playground-এ টেস্ট করুন।",
    },
    {
        title: "অপ্রয়োজনীয় gap Ignore করতে পারেন",
        detail: "Spam বা এককালীন প্রশ্ন — Ignore করলে open তালিকা থেকে সরে যায় (turn লগ থাকে)।",
    },
];

const howToTips = [
    "Gap = knowledge মিস (context আছে কিন্তু fact নেই)। শুধু ‘price koto?’ এখন Gap নয় — Clarify।",
    "Price gap → Type = Product + External ID (যে id adapter পাঠাবে) + দাম। FAQ-তে generic price নয়।",
    "Draft সেভের পর Knowledge → Publish না করলে উত্তর আসবে না।",
];

const filterTabs = computed(() => [
    { value: "open", label: "Open", count: localStats.value.open },
    { value: "handled", label: "Handled", count: localStats.value.handled },
    { value: "all", label: "All gaps", count: localStats.value.all },
]);

const listHint = computed(() => {
    const base = "Open gap = মিস হওয়া ব্যবসায়িক প্রশ্ন। Draft বানিয়ে Knowledge-এ গিয়ে Publish করুন।";
    if (localStats.value.all > props.list_limit) {
        return `${base} Showing latest ${props.list_limit} of ${localStats.value.all}.`;
    }
    return base;
});

const emptyTitle = computed(() =>
    props.filter === "open" ? "কোনো open gap নেই" : "এই ফিল্টারে কিছু নেই",
);

const emptyDescription = computed(() =>
    props.filter === "open"
        ? "Playground-এ দাম/ডেলিভারি জিজ্ঞাসা করলে (knowledge না থাকলে) এখানে দেখাবে"
        : "অন্য ট্যাব চেষ্টা করুন",
);

const canSaveDraft = computed(() => {
    if (draftForm.title.trim() === "" || draftForm.answer.trim() === "") return false;
    if ((draftForm.type === "product" || draftForm.scope === "offer") && draftForm.external_id.trim() === "") {
        return false;
    }
    return true;
});

const openDraft = (row: GapRow) => {
    activeGap.value = row;
    const isPrice = row.intent === "price";
    draftForm.type = isPrice ? "product" : "faq";
    draftForm.scope = "merchant";
    draftForm.title = isPrice
        ? (row.text ? `Offer — ${row.text.slice(0, 40)}` : "Offer price")
        : (row.intent ? `${row.intent} FAQ` : "FAQ");
    draftForm.question = row.text || "";
    draftForm.answer = "";
    draftForm.external_id = "";
    draftForm.platform = "";
    draftForm.offer_kind = isPrice ? "physical" : null;
    draftForm.sku = "";
    keywordsText.value = row.intent || "";
    showDraft.value = true;
};

const saveDraft = async () => {
    if (!activeGap.value || !canSaveDraft.value || saving.value) return;
    saving.value = true;
    try {
        const keywords = keywordsText.value
            .split(",")
            .map((k) => k.trim())
            .filter(Boolean);
        const { data } = await axios.post(route("wiseAi.gaps.draft", { turn: activeGap.value.id }), {
            ...draftForm,
            keywords,
        });
        if (data.stats) localStats.value = data.stats;
        const idx = gaps.value.findIndex((g) => g.id === activeGap.value!.id);
        if (idx >= 0) {
            if (props.filter === "open") {
                gaps.value.splice(idx, 1);
            } else {
                gaps.value[idx] = data.turn;
            }
        }
        showDraft.value = false;
        toast.add({
            severity: "success",
            summary: "Draft saved",
            detail: "এখন Knowledge → Publish করুন",
            life: 3500,
            group: "br",
        });
    } catch {
        toast.add({ severity: "error", summary: "Could not save draft", life: 3500, group: "br" });
    } finally {
        saving.value = false;
    }
};

const ignoreItem = async (row: GapRow) => {
    if (ignoringId.value === row.id) return;
    ignoringId.value = row.id;
    try {
        const { data } = await axios.post(route("wiseAi.gaps.ignore", { turn: row.id }));
        if (data.stats) localStats.value = data.stats;
        const idx = gaps.value.findIndex((g) => g.id === row.id);
        if (idx >= 0) {
            if (props.filter === "open") {
                gaps.value.splice(idx, 1);
            } else {
                gaps.value[idx] = data.turn;
            }
        }
        toast.add({ severity: "success", summary: "Gap ignored", life: 2500, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Ignore failed", life: 3500, group: "br" });
    } finally {
        ignoringId.value = null;
    }
};
</script>
