<template>
    <AuthenticatedLayout title="Wise AI — Knowledge">
        <div class="space-y-5">
            <PageHeader
                title="Knowledge"
                description="গ্লোবাল বা এক দোকানের সত্য — Draft সেভ → Publish করলেই brain উত্তর দেয়"
                icon="PhBooks"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            />

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Knowledge কীভাবে যোগ করবেন"
                subtitle="গ্লোবাল = Platform (API key লাগে না) · এক দোকান = Merchant + key"
                badge="অনুমোদন দরকার"
                storage-key="knowledge"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <PageCard title="Add FAQ / policy" description="সাধারণ প্রশ্ন-উত্তর — আগে Draft, পরে নিচে Publish">
                <form class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="createItem">
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-xs text-gray-500">Audience</label>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                label="গ্লোবাল (Platform)"
                                size="small"
                                :outlined="audience !== 'platform'"
                                :severity="audience === 'platform' ? undefined : 'secondary'"
                                @click="setAudience('platform')"
                            />
                            <Button
                                type="button"
                                label="এক দোকান (Merchant)"
                                size="small"
                                :outlined="audience !== 'merchant'"
                                :severity="audience === 'merchant' ? undefined : 'secondary'"
                                @click="setAudience('merchant')"
                            />
                        </div>
                        <p class="mt-1.5 text-[11px] text-gray-500">
                            গ্লোবাল ট্রেনিং = Platform — সব দোকানে shared, API Key লাগে না।
                        </p>
                    </div>
                    <div v-if="audience === 'merchant'">
                        <label class="mb-1 block text-xs text-gray-500">API Key</label>
                        <Select
                            v-model="form.wise_api_key_id"
                            :options="apiKeys"
                            option-label="label"
                            option-value="id"
                            placeholder="Select key"
                            class="w-full"
                        />
                    </div>
                    <div :class="audience === 'merchant' ? '' : 'md:col-span-2'">
                        <label class="mb-1 block text-xs text-gray-500">Kind</label>
                        <Select
                            v-model="form.type"
                            :options="types"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">Title</label>
                        <BanglaField v-model="form.title" placeholder="Delivery charge" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">Question (কাস্টমার যা জিজ্ঞাসা করে)</label>
                        <BanglaField v-model="form.question" placeholder="ডেলিভারি চার্জ কত?" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">Answer</label>
                        <BanglaField
                            v-model="form.answer"
                            multiline
                            :rows="3"
                            placeholder="এলাকা বললে দেখে চার্জ জানাই; আন্দাজ করে বলব না।"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">Keywords (comma)</label>
                        <BanglaField v-model="keywordsText" placeholder="delivery, চার্জ, courier" />
                    </div>
                    <div class="md:col-span-2">
                        <button
                            type="button"
                            class="text-xs font-semibold text-fuchsia-700 hover:underline dark:text-fuchsia-300"
                            @click="showAdvancedFields = !showAdvancedFields"
                        >
                            {{ showAdvancedFields ? "Hide advanced fields" : "Advanced fields (offer / region / S9)" }}
                        </button>
                    </div>
                    <template v-if="showAdvancedFields">
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Scope override</label>
                            <Select
                                v-model="form.scope"
                                :options="advancedScopes"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                                @update:model-value="onAdvancedScope"
                            />
                        </div>
                        <div v-if="form.scope === 'region'">
                            <label class="mb-1 block text-xs text-gray-500">Region</label>
                            <input
                                v-model="form.region"
                                type="text"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none dark:border-gray-700 dark:bg-slate-900"
                                placeholder="dhaka · chittagong"
                            />
                        </div>
                        <div
                            v-if="form.type === 'faq' || form.type === 'policy' || form.type === 'fact' || form.type === 'script' || form.type === 'other'"
                            class="md:col-span-2"
                        >
                            <label class="flex items-start gap-2.5 rounded-xl border border-gray-200 bg-gray-50/80 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-slate-900/60">
                                <input v-model="form.pricing_menu" type="checkbox" class="mt-0.5" />
                                <span>
                                    <span class="font-medium text-gray-800 dark:text-gray-100">Pricing menu (S9)</span>
                                    <span class="mt-0.5 block text-[11px] text-gray-500">
                                        Bare “dam / price” এ এই FAQ দিয়ে উত্তর (SaaS মেনু)। অনেক SKU দোকানে চালাবেন না।
                                    </span>
                                </span>
                            </label>
                        </div>
                        <template v-if="form.type === 'product' || form.scope === 'offer'">
                            <div>
                                <label class="mb-1 block text-xs text-gray-500">Offer kind</label>
                                <Select
                                    v-model="form.offer_kind"
                                    :options="offerKinds"
                                    option-label="label"
                                    option-value="value"
                                    class="w-full"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-gray-500">External offer ID</label>
                                <input
                                    v-model="form.external_id"
                                    type="text"
                                    class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none dark:border-gray-700 dark:bg-slate-900"
                                    placeholder="45 · course_9"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-gray-500">Platform</label>
                                <input
                                    v-model="form.platform"
                                    type="text"
                                    class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none dark:border-gray-700 dark:bg-slate-900"
                                    placeholder="woocommerce"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-gray-500">SKU</label>
                                <input
                                    v-model="form.sku"
                                    type="text"
                                    class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none dark:border-gray-700 dark:bg-slate-900"
                                />
                            </div>
                        </template>
                    </template>
                    <div class="md:col-span-2 flex justify-end gap-2">
                        <Button
                            type="submit"
                            label="Save as draft"
                            icon="pi pi-save"
                            size="small"
                            :loading="saving"
                            :disabled="!canSave || !can_edit"
                        />
                    </div>
                    <p v-if="!can_edit" class="md:col-span-2 text-xs text-amber-700 dark:text-amber-300">
                        Editor role required to create knowledge drafts.
                    </p>
                </form>
            </PageCard>

            <PageCard
                title="Knowledge items"
                description="Status = published হলেই Playground/API ব্যবসায়িক উত্তর দিতে পারবে"
                :no-padding="items.length > 0"
            >
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <Button
                        v-for="opt in filterOptions"
                        :key="opt.value"
                        :label="opt.label"
                        size="small"
                        :outlined="filter !== opt.value"
                        :severity="filter === opt.value ? undefined : 'secondary'"
                        @click="setFilter(opt.value)"
                    />
                    <span
                        v-if="seededDraftCount > 0"
                        class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-200"
                    >
                        Seeded review: {{ seededDraftCount }}
                    </span>
                    <div class="ml-auto flex flex-wrap gap-2">
                        <Button
                            v-if="can_publish"
                            label="Publish selected seeds"
                            icon="pi pi-check"
                            size="small"
                            severity="success"
                            :disabled="selectedEligibleIds.length === 0"
                            :loading="bulkPublishing"
                            @click="confirmBulkOpen = true"
                        />
                    </div>
                </div>

                <DataTable
                    v-if="items.length"
                    v-model:selection="selectedRows"
                    :value="items"
                    data-key="id"
                    size="small"
                    striped-rows
                    class="professional-table"
                >
                    <Column selection-mode="multiple" header-style="width: 3rem" :pt="{ headerCell: { class: 'w-12' } }" />
                    <Column header="Title">
                        <template #body="{ data }">
                            <div class="text-sm font-medium">{{ data.title }}</div>
                            <div class="text-[11px] text-gray-400">
                                {{ data.key_name || "platform" }} · {{ data.type }}/{{ data.scope || "merchant" }} · v{{ data.version }}
                                <span v-if="data.is_seeded" class="text-amber-600 dark:text-amber-300"> · seeded</span>
                                <span v-if="data.offer_kind"> · {{ data.offer_kind }}</span>
                                <span v-if="data.external_id"> · ext:{{ data.external_id }}</span>
                                <span v-if="data.region"> · {{ data.region }}</span>
                                <span v-if="data.platform"> · {{ data.platform }}</span>
                                <span v-if="data.pricing_menu"> · pricing menu</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Answer">
                        <template #body="{ data }">
                            <span class="block max-w-[280px] truncate text-sm" :title="data.answer">{{ data.answer }}</span>
                        </template>
                    </Column>
                    <Column header="Status">
                        <template #body="{ data }">
                            <StatusBadge
                                :label="data.status"
                                :variant="data.status === 'published' ? 'success' : 'warning'"
                                format="none"
                            />
                        </template>
                    </Column>
                    <Column header="Action">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Button
                                    v-if="can_edit"
                                    label="AI fix"
                                    icon="pi pi-sparkles"
                                    size="small"
                                    severity="help"
                                    text
                                    @click="openRegenerate(data)"
                                />
                                <Button
                                    v-if="can_publish && data.status !== 'published'"
                                    label="Publish"
                                    icon="pi pi-check"
                                    size="small"
                                    text
                                    @click="publishItem(data)"
                                />
                                <Button
                                    v-else-if="can_publish"
                                    label="Unpublish"
                                    icon="pi pi-eye-slash"
                                    size="small"
                                    severity="secondary"
                                    text
                                    @click="unpublishItem(data)"
                                />
                                <span v-else class="text-[11px] text-gray-400">Publisher only</span>
                            </div>
                        </template>
                    </Column>
                </DataTable>
                <EmptyState
                    v-else
                    icon="PhBooks"
                    title="এখনো কোনো knowledge নেই"
                    description="ডেলিভারি বা দামের FAQ সেভ করুন → Publish → Playground-এ জিজ্ঞাসা করুন"
                />
            </PageCard>

            <Dialog
                v-model:visible="confirmBulkOpen"
                modal
                header="Bulk publish seeded drafts?"
                class="w-full max-w-md"
            >
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ selectedEligibleIds.length }}টি seeded draft publish হবে। শুধু platform/regional seed rows — merchant FAQ আলাদা।
                </p>
                <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                    Publish = মানুষের অনুমোদন। AI নিজে publish করে না।
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <Button label="Cancel" size="small" severity="secondary" text @click="confirmBulkOpen = false" />
                    <Button
                        label="Publish selected"
                        size="small"
                        severity="success"
                        :loading="bulkPublishing"
                        @click="bulkPublishSelected"
                    />
                </div>
            </Dialog>

            <Dialog
                v-model:visible="regenOpen"
                modal
                header="AI fix answer"
                class="w-full max-w-2xl"
                @hide="resetRegen"
            >
                <div v-if="regenItem" class="space-y-3">
                    <div>
                        <div class="text-xs font-semibold text-gray-500">Question</div>
                        <p class="text-sm">{{ regenItem.question || regenItem.title }}</p>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-gray-500">Current answer</div>
                        <p class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-700 dark:bg-slate-900">
                            {{ regenItem.answer }}
                        </p>
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <div class="text-xs font-semibold text-gray-500">Proposed answer</div>
                            <Button
                                :label="proposedAnswer.trim() ? 'Regenerate' : 'Generate with AI'"
                                :icon="proposedAnswer.trim() ? 'pi pi-refresh' : 'pi pi-sparkles'"
                                size="small"
                                text
                                :loading="regenLoadingId === regenItem.id"
                                :disabled="!can_edit"
                                @click="runRegenerate"
                            />
                        </div>
                        <BanglaField
                            v-model="proposedAnswer"
                            multiline
                            :rows="5"
                            placeholder="Generate with AI চাপুন — অথবা নিজে উত্তর লিখুন"
                        />
                        <p class="mt-1 text-[11px] text-gray-500">
                            Generate = AI proposal। Apply = draft আপডেট। তারপর আলাদা Publish।
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button label="Close" size="small" severity="secondary" text @click="regenOpen = false" />
                        <Button
                            label="Apply to draft"
                            icon="pi pi-save"
                            size="small"
                            :loading="applyingAnswer"
                            :disabled="!can_edit || !proposedAnswer.trim()"
                            @click="applyProposedAnswer"
                        />
                    </div>
                </div>
            </Dialog>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import axios from "axios";
import { router } from "@inertiajs/vue3";
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
import BanglaField from "@/components/BanglaField.vue";

const howToSteps = [
    {
        title: "Audience বেছে নিন — গ্লোবাল বা এক দোকান",
        detail: "গ্লোবাল (Platform) = shared training, API Key লাগে না। এক দোকান হলে Merchant + সেই দোকানের API Key।",
    },
    {
        title: "Title + Answer লিখে Save as draft",
        detail: "Question ও Keywords দিলে ম্যাচ নির্ভুল হয়। Save = draft — এখনো লাইভ উত্তর নয়।",
    },
    {
        title: "Seeded review → AI fix (optional) → Publish",
        detail: "Platform/regional seed সবসময় draft আসে। চাইলে AI উত্তর ঠিক করে Apply, তারপর Publish।",
    },
    {
        title: "Playground-এ একই প্রশ্ন টেস্ট করুন",
        detail: "Source = knowledge ও suggested reply = আপনার Answer দেখতে হবে। ভুল হলে Unpublish করে ঠিক করুন।",
    },
];

const howToTips = [
    "গ্লোবাল ডেটা ট্রেনিং করলে Audience = Platform রাখুন — API Key সিলেক্ট করতে হবে না।",
    "Create/Update কখনোই সরাসরি published হয় না — শুধু Publish বাটন দিয়ে।",
    "Seeded bulk Publish শুধু platform/regional seed drafts-এ কাজ করে।",
    "AI fix শুধু proposed answer দেয় — Apply + Publish দুটোই মানুষের কাজ।",
    "Playground chat / LLM ON brain-এ auto-learn করে না।",
];

type KeyOption = { id: number; name: string; key_prefix: string };
type KnowledgeRow = {
    id: number;
    wise_api_key_id: number | null;
    key_name: string | null;
    type: string;
    scope?: string;
    title: string;
    question: string | null;
    answer: string;
    keywords: string[];
    external_id: string | null;
    platform: string | null;
    offer_kind: string | null;
    sku: string | null;
    region?: string | null;
    pricing_menu: boolean;
    seeded_from?: string | null;
    is_seeded?: boolean;
    bulk_eligible?: boolean;
    status: string;
    version: number;
    updated_at: string | null;
};

const props = defineProps<{
    apiKeys: KeyOption[];
    items: KnowledgeRow[];
    filter?: string;
    seeded_draft_count?: number;
    can_edit: boolean;
    can_publish: boolean;
}>();

const can_edit = props.can_edit !== false;
const can_publish = props.can_publish !== false;

const toast = useToast();
const items = ref<KnowledgeRow[]>([...props.items]);
const filter = ref(props.filter || "all");
const seededDraftCount = ref(props.seeded_draft_count ?? 0);
const saving = ref(false);
const keywordsText = ref("");
const selectedRows = ref<KnowledgeRow[]>([]);
const confirmBulkOpen = ref(false);
const bulkPublishing = ref(false);
const regenOpen = ref(false);
const regenItem = ref<KnowledgeRow | null>(null);
const proposedAnswer = ref("");
const regenLoadingId = ref<number | null>(null);
const applyingAnswer = ref(false);

watch(
    () => props.items,
    (next) => {
        items.value = [...next];
        selectedRows.value = [];
    },
);
watch(
    () => props.seeded_draft_count,
    (n) => {
        seededDraftCount.value = n ?? 0;
    },
);
watch(
    () => props.filter,
    (f) => {
        filter.value = f || "all";
        selectedRows.value = [];
    },
);

const filterOptions = computed(() => [
    { label: "All", value: "all" },
    { label: `Seeded review (${seededDraftCount.value})`, value: "seeded_drafts" },
    { label: "Drafts", value: "draft" },
    { label: "Published", value: "published" },
]);

const selectedEligibleIds = computed(() =>
    selectedRows.value.filter((r) => r.bulk_eligible).map((r) => r.id),
);

const apiKeys = computed(() =>
    props.apiKeys.map((k) => ({
        id: k.id,
        label: `${k.name} (${k.key_prefix}…)`,
    })),
);

const types = [
    { label: "FAQ (Q→A)", value: "faq" },
    { label: "Offer / Product", value: "product" },
    { label: "Policy", value: "policy" },
    { label: "Fact", value: "fact" },
    { label: "Script", value: "script" },
    { label: "Campaign", value: "campaign" },
    { label: "Voice (tone only)", value: "voice" },
];

const scopes = [
    { label: "Merchant", value: "merchant" },
    { label: "Offer", value: "offer" },
    { label: "Region", value: "region" },
    { label: "Platform (shared)", value: "platform" },
];

const advancedScopes = scopes;

const offerKinds = [
    { label: "Physical product", value: "physical" },
    { label: "Digital product", value: "digital" },
    { label: "Service", value: "service" },
    { label: "Subscription", value: "subscription" },
    { label: "Other", value: "other" },
];

const showAdvancedFields = ref(false);
const audience = ref<"platform" | "merchant">("platform");

const form = reactive({
    wise_api_key_id: null as number | null,
    type: "faq",
    scope: "platform",
    title: "",
    question: "",
    answer: "",
    external_id: "",
    platform: "",
    offer_kind: "physical",
    sku: "",
    region: "",
    pricing_menu: false,
});

const setAudience = (next: "platform" | "merchant") => {
    audience.value = next;
    if (next === "platform") {
        form.scope = "platform";
        form.wise_api_key_id = null;
    } else {
        if (form.scope === "platform") {
            form.scope = "merchant";
        }
        form.wise_api_key_id = form.wise_api_key_id ?? props.apiKeys[0]?.id ?? null;
    }
};

const onAdvancedScope = (scope: string) => {
    if (scope === "platform") {
        audience.value = "platform";
        form.wise_api_key_id = null;
    } else {
        audience.value = "merchant";
        form.wise_api_key_id = form.wise_api_key_id ?? props.apiKeys[0]?.id ?? null;
    }
};

const canSave = computed(() => {
    if (form.title.trim() === "" || form.answer.trim() === "") return false;
    if (form.scope !== "platform" && !form.wise_api_key_id) return false;
    if ((form.type === "product" || form.scope === "offer") && !form.external_id.trim()) return false;
    if (form.scope === "region" && !form.region.trim()) return false;
    return true;
});

const setFilter = (value: string) => {
    router.get(route("wiseAi.knowledge"), { filter: value }, { preserveState: true, preserveScroll: true });
};

const createItem = async () => {
    if (!canSave.value || saving.value) return;
    saving.value = true;
    try {
        const keywords = keywordsText.value
            .split(",")
            .map((k) => k.trim())
            .filter(Boolean);
        const needsOffer = form.type === "product" || form.scope === "offer";
        const { data } = await axios.post(route("wiseAi.knowledge.store"), {
            ...form,
            wise_api_key_id: form.scope === "platform" ? null : form.wise_api_key_id,
            external_id: needsOffer || form.external_id ? form.external_id || null : null,
            platform: needsOffer ? form.platform || null : null,
            offer_kind: form.type === "product" ? form.offer_kind || null : null,
            sku: form.type === "product" ? form.sku || null : null,
            region: form.scope === "region" ? form.region || null : null,
            pricing_menu: !["product", "voice", "campaign"].includes(form.type) ? form.pricing_menu : false,
            keywords,
            status: "draft",
        });
        items.value.unshift(data.item);
        form.title = "";
        form.question = "";
        form.answer = "";
        form.external_id = "";
        form.platform = "";
        form.offer_kind = "physical";
        form.sku = "";
        form.region = "";
        form.scope = "platform";
        form.wise_api_key_id = null;
        audience.value = "platform";
        form.pricing_menu = false;
        keywordsText.value = "";
        toast.add({ severity: "success", summary: "Saved as draft", life: 2500, group: "br" });
    } catch {
        toast.add({ severity: "error", summary: "Could not save knowledge", life: 3500, group: "br" });
    } finally {
        saving.value = false;
    }
};

const publishItem = async (item: KnowledgeRow) => {
    try {
        const { data } = await axios.post(route("wiseAi.knowledge.publish", { item: item.id }));
        Object.assign(item, data.item);
        if (item.is_seeded) {
            seededDraftCount.value = Math.max(0, seededDraftCount.value - 1);
        }
        toast.add({
            severity: "success",
            summary: "Published",
            detail: "এখন brain এই উত্তর ব্যবহার করতে পারবে",
            life: 2500,
            group: "br",
        });
    } catch {
        toast.add({ severity: "error", summary: "Publish failed", life: 3500, group: "br" });
    }
};

const unpublishItem = async (item: KnowledgeRow) => {
    try {
        const { data } = await axios.post(route("wiseAi.knowledge.unpublish", { item: item.id }));
        Object.assign(item, data.item);
        if (item.is_seeded && item.status === "draft") {
            seededDraftCount.value += 1;
        }
        toast.add({
            severity: "success",
            summary: "Unpublished",
            detail: "এখন draft — ব্যবসায়িক উত্তরে আর ব্যবহার হবে না",
            life: 2500,
            group: "br",
        });
    } catch {
        toast.add({ severity: "error", summary: "Unpublish failed", life: 3500, group: "br" });
    }
};

const bulkPublishSelected = async () => {
    if (selectedEligibleIds.value.length === 0 || bulkPublishing.value) return;
    bulkPublishing.value = true;
    try {
        const { data } = await axios.post(route("wiseAi.knowledge.bulkPublish"), {
            ids: selectedEligibleIds.value,
        });
        const publishedMap = new Map<number, KnowledgeRow>(
            (data.items as KnowledgeRow[]).map((row) => [row.id, row]),
        );
        items.value = items.value.map((row) => publishedMap.get(row.id) ?? row);
        selectedRows.value = [];
        seededDraftCount.value = data.seeded_draft_count ?? seededDraftCount.value;
        confirmBulkOpen.value = false;
        toast.add({
            severity: "success",
            summary: `Published ${data.published_count}`,
            detail: data.skipped_count ? `${data.skipped_count} skipped (not seeded drafts)` : undefined,
            life: 3000,
            group: "br",
        });
        if (filter.value === "seeded_drafts") {
            router.reload({ only: ["items", "seeded_draft_count", "filter"] });
        }
    } catch (e: any) {
        toast.add({
            severity: "error",
            summary: "Bulk publish failed",
            detail: e?.response?.data?.message,
            life: 4000,
            group: "br",
        });
    } finally {
        bulkPublishing.value = false;
    }
};

const openRegenerate = (item: KnowledgeRow) => {
    regenItem.value = item;
    proposedAnswer.value = "";
    regenOpen.value = true;
};

const runRegenerate = async () => {
    if (!regenItem.value || !can_edit) return;
    regenLoadingId.value = regenItem.value.id;
    try {
        const { data } = await axios.post(
            route("wiseAi.knowledge.regenerateAnswer", { item: regenItem.value.id }),
        );
        proposedAnswer.value = data.proposed_answer || "";
        toast.add({
            severity: "success",
            summary: "AI proposal ready",
            detail: "Apply করলে draft আপডেট হবে — Publish আলাদা",
            life: 2500,
            group: "br",
        });
    } catch (e: any) {
        toast.add({
            severity: "error",
            summary: "Regenerate failed",
            detail: e?.response?.data?.message || "LLM key / fact guard",
            life: 4500,
            group: "br",
        });
    } finally {
        regenLoadingId.value = null;
    }
};

const applyProposedAnswer = async () => {
    if (!regenItem.value || !proposedAnswer.value.trim() || applyingAnswer.value) return;
    applyingAnswer.value = true;
    try {
        const { data } = await axios.post(route("wiseAi.knowledge.update", { item: regenItem.value.id }), {
            answer: proposedAnswer.value.trim(),
        });
        const idx = items.value.findIndex((r) => r.id === regenItem.value!.id);
        if (idx >= 0) {
            items.value[idx] = data.item;
        }
        Object.assign(regenItem.value, data.item);
        toast.add({
            severity: "success",
            summary: "Applied to draft",
            detail: "এখন Publish চাপলে লাইভ হবে",
            life: 3000,
            group: "br",
        });
        regenOpen.value = false;
    } catch {
        toast.add({ severity: "error", summary: "Could not apply answer", life: 3500, group: "br" });
    } finally {
        applyingAnswer.value = false;
    }
};

const resetRegen = () => {
    regenItem.value = null;
    proposedAnswer.value = "";
    regenLoadingId.value = null;
};
</script>
