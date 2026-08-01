<template>
    <AuthenticatedLayout title="Wise AI — Knowledge">
        <div class="space-y-5">
            <PageHeader
                title="Knowledge"
                description="Publish merchant FAQ and policies — business answers only come from published knowledge"
                icon="PhBooks"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            />

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Knowledge কীভাবে যোগ করবেন"
                subtitle="Brain ব্যবসায়িক উত্তর শুধু Published আইটেম থেকে নেয় — Draft মানে এখনো লাইভ নয়"
                badge="অনুমোদন দরকার"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <PageCard title="Add knowledge" description="আগে Draft সেভ → নিচে Publish — মানুষ অনুমোদন না দিলে brain ব্যবহার করবে না">
                <form class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="createItem">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">API Key (কোন tenant / product)</label>
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
                        <label class="mb-1 block text-xs text-gray-500">Type</label>
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
                        <label class="mb-1 block text-xs text-gray-500">
                            Question / match text (ঐচ্ছিক — কাস্টমার যা জিজ্ঞাসা করে তার কাছাকাছি লিখুন)
                        </label>
                        <input
                            v-model="form.question"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="ডেলিভারি চার্জ কত?"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">Answer (এই টেক্সটই কাস্টমারকে সাজেস্ট হবে)</label>
                        <textarea
                            v-model="form.answer"
                            rows="3"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="ঢাকায় ৬০ টাকা, ঢাকার বাইরে ১২০ টাকা।"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">Keywords (কমা দিয়ে — ম্যাচ শক্তিশালী করে)</label>
                        <input
                            v-model="keywordsText"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="delivery, চার্জ, courier"
                        />
                    </div>
                    <div class="md:col-span-2 flex justify-end gap-2">
                        <Button
                            type="submit"
                            label="Save as draft"
                            icon="pi pi-save"
                            size="small"
                            :loading="saving"
                            :disabled="!canSave"
                        />
                    </div>
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
                            <div class="text-[11px] text-gray-400">{{ data.key_name }} · {{ data.type }} · v{{ data.version }}</div>
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
                                    v-if="data.status !== 'published'"
                                    label="Publish"
                                    icon="pi pi-check"
                                    size="small"
                                    text
                                    @click="publishItem(data)"
                                />
                                <Button
                                    v-else
                                    label="Unpublish"
                                    icon="pi pi-eye-slash"
                                    size="small"
                                    severity="secondary"
                                    text
                                    @click="unpublishItem(data)"
                                />
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
    "দাম vs ডেলিভারি আলাদা FAQ রাখুন; এক আইটেমে মিশিয়ে দিলে ভুল ম্যাচ হতে পারে।",
    "Content এডিট করলে (ভবিষ্যতে) আবার draft হয়ে যায় — পুনরায় Publish করতে হবে।",
];

type KeyOption = { id: number; name: string; key_prefix: string };
type KnowledgeRow = {
    id: number;
    wise_api_key_id: number;
    key_name: string | null;
    type: string;
    title: string;
    question: string | null;
    answer: string;
    keywords: string[];
    status: string;
    version: number;
    updated_at: string | null;
};

const props = defineProps<{
    apiKeys: KeyOption[];
    items: KnowledgeRow[];
}>();

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
    { label: "FAQ", value: "faq" },
    { label: "Product", value: "product" },
    { label: "Policy", value: "policy" },
    { label: "Other", value: "other" },
];

const form = reactive({
    wise_api_key_id: props.apiKeys[0]?.id ?? null as number | null,
    type: "faq",
    title: "",
    question: "",
    answer: "",
});

const canSave = computed(
    () => !!form.wise_api_key_id && form.title.trim() !== "" && form.answer.trim() !== "",
);

const createItem = async () => {
    if (!canSave.value || saving.value) return;
    saving.value = true;
    try {
        const keywords = keywordsText.value
            .split(",")
            .map((k) => k.trim())
            .filter(Boolean);
        const { data } = await axios.post(route("wiseAi.knowledge.store"), {
            ...form,
            keywords,
            status: "draft",
        });
        items.value.unshift(data.item);
        form.title = "";
        form.question = "";
        form.answer = "";
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
