<template>
    <AuthenticatedLayout title="Wise AI — Knowledge">
        <div class="space-y-5">
            <PageHeader
                title="Knowledge"
                description="দোকানের সত্য — FAQ/policy Draft সেভ → Publish করলেই brain উত্তর দেয়"
                icon="PhBooks"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            />

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Knowledge কীভাবে যোগ করবেন"
                subtitle="Draft = এখনো লাইভ নয় · Publish = কাস্টমার উত্তরে ব্যবহার"
                badge="অনুমোদন দরকার"
                storage-key="knowledge"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <PageCard title="Add FAQ / policy" description="সাধারণ প্রশ্ন-উত্তর — আগে Draft, পরে নিচে Publish">
                <form class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="createItem">
                    <div>
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
                    <div>
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
                        <input
                            v-model="form.title"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="Delivery charge"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">Question (কাস্টমার যা জিজ্ঞাসা করে)</label>
                        <input
                            v-model="form.question"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="ডেলিভারি চার্জ কত?"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">Answer</label>
                        <textarea
                            v-model="form.answer"
                            rows="3"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="ঢাকায় ৬০ টাকা, ঢাকার বাইরে ১২০ টাকা।"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">Keywords (comma)</label>
                        <input
                            v-model="keywordsText"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="delivery, চার্জ, courier"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <button
                            type="button"
                            class="text-xs font-semibold text-fuchsia-700 hover:underline dark:text-fuchsia-300"
                            @click="showAdvancedFields = !showAdvancedFields"
                        >
                            {{ showAdvancedFields ? "Hide advanced fields" : "Advanced fields (scope / product / S9)" }}
                        </button>
                    </div>
                    <template v-if="showAdvancedFields">
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Scope</label>
                            <Select
                                v-model="form.scope"
                                :options="scopes"
                                option-label="label"
                                option-value="value"
                                class="w-full"
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
                <DataTable v-if="items.length" :value="items" size="small" striped-rows class="professional-table">
                    <Column header="Title">
                        <template #body="{ data }">
                            <div class="text-sm font-medium">{{ data.title }}</div>
                            <div class="text-[11px] text-gray-400">
                                {{ data.key_name || "platform" }} · {{ data.type }}/{{ data.scope || "merchant" }} · v{{ data.version }}
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
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import axios from "axios";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import Select from "primevue/select";
import { useToast } from "primevue/usetoast";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";
import WiseAiHowTo from "./fragments/WiseAiHowTo.vue";

const howToSteps = [
    {
        title: "উপরে API Key বেছে নিন",
        detail: "যে key দিয়ে Playground/প্রোডাক্ট decide করবে, সেই tenant-এর অধীনে knowledge যোগ করুন।",
    },
    {
        title: "Title + Answer লিখে Save as draft",
        detail: "Question ও Keywords দিলে ম্যাচ নির্ভুল হয়। Save = draft — এখনো লাইভ উত্তর নয়।",
    },
    {
        title: "নিচের তালিকায় Publish চাপুন",
        detail: "এটাই মানুষের অনুমোদন। Publish ছাড়া ব্যবসায়িক প্রশ্নে needs_human আসবে।",
    },
    {
        title: "Playground-এ একই প্রশ্ন টেস্ট করুন",
        detail: "Source = knowledge ও suggested reply = আপনার Answer দেখতে হবে। ভুল হলে Unpublish করে ঠিক করুন।",
    },
];

const howToTips = [
    "Create/Update কখনোই সরাসরি published হয় না — শুধু Publish বাটন দিয়ে।",
    "দাম/প্যাকেজ = Offer/Product + offer kind + external id। SaaS প্ল্যান মেনু = FAQ + Pricing menu checkbox।",
    "Scope: merchant / offer / region / platform — Intent Contract row-এ duplicate করবেন না।",
    "Voice kind = tone guide only; customer reply ground করে না।",
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
    status: string;
    version: number;
    updated_at: string | null;
};

const props = defineProps<{
    apiKeys: KeyOption[];
    items: KnowledgeRow[];
    can_edit: boolean;
    can_publish: boolean;
}>();

const can_edit = props.can_edit !== false;
const can_publish = props.can_publish !== false;

const toast = useToast();
const items = ref<KnowledgeRow[]>([...props.items]);
const saving = ref(false);
const keywordsText = ref("");

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

const offerKinds = [
    { label: "Physical product", value: "physical" },
    { label: "Digital product", value: "digital" },
    { label: "Service", value: "service" },
    { label: "Subscription", value: "subscription" },
    { label: "Other", value: "other" },
];

const showAdvancedFields = ref(false);

const form = reactive({
    wise_api_key_id: props.apiKeys[0]?.id ?? null as number | null,
    type: "faq",
    scope: "merchant",
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

const canSave = computed(() => {
    if (form.title.trim() === "" || form.answer.trim() === "") return false;
    if (form.scope !== "platform" && !form.wise_api_key_id) return false;
    if ((form.type === "product" || form.scope === "offer") && !form.external_id.trim()) return false;
    if (form.scope === "region" && !form.region.trim()) return false;
    return true;
});

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
        form.scope = "merchant";
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
</script>
