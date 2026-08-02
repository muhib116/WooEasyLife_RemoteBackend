<template>
    <AuthenticatedLayout title="Wise AI — Train">
        <div class="space-y-5">
            <PageHeader
                title="Train"
                description="Advanced — JSON packs for bulk Knowledge / Language / Experience (never auto-publish)"
                icon="PhUploadSimple"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            >
                <template #actions>
                    <StatusBadge label="Advanced" variant="neutral" format="none" />
                    <StatusBadge :label="schema_version" variant="neutral" format="none" />
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <PageCard title="প্রথম শেখার আইডিয়া (বাংলা)" description="ম্যানুয়াল ট্রেনিং দিয়ে শুরু — এগুলোতেই সবচেয়ে দ্রুত উন্নতি দেখা যায়">
                <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                    <li v-for="(tip, i) in first_learning_bn" :key="i" class="flex gap-2">
                        <span class="font-semibold text-fuchsia-600">{{ i + 1 }}.</span>
                        <span>{{ tip }}</span>
                    </li>
                </ul>
            </PageCard>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                <PageCard
                    title="Professional AI prompt"
                    description="ChatGPT / Claude / Gemini-এ পেস্ট করুন → JSON প্যাক জেনারেট → নিচে Import"
                >
                    <pre
                        class="max-h-72 overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-[11px] leading-relaxed text-slate-200 whitespace-pre-wrap"
                    >{{ professional_prompt }}</pre>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button
                            label="Copy prompt"
                            icon="pi pi-copy"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="copyPrompt"
                        />
                        <Button
                            label="Load example JSON"
                            icon="pi pi-file"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="loadExample"
                        />
                        <Button
                            label="Load platform example"
                            icon="pi pi-globe"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="loadPlatformExample"
                        />
                    </div>
                </PageCard>

                <PageCard
                    title="Generate with Wise LLM"
                    :description="llm.key_set && llm.enabled ? `Model ${llm.model}` : 'Config → LLM Language-এ key লাগবে'"
                >
                    <label class="block text-xs font-medium text-gray-500">
                        Merchant brief (বাংলা/ইংরেজি)
                        <textarea
                            v-model="brief"
                            rows="8"
                            class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-slate-900"
                            placeholder="স্টোর নাম, slang (plz/tnx/tumar), ডেলিভারি, পেমেন্ট, রিটার্ন…"
                        />
                    </label>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <Button
                            label="Generate JSON"
                            icon="pi pi-sparkles"
                            size="small"
                            :loading="generating"
                            :disabled="!can_edit || !brief.trim() || brief.trim().length < 20"
                            @click="generate"
                        />
                        <span class="text-[11px] text-gray-400">
                            {{ can_edit ? "Review before Import — drafts / Discovery only" : "Editor role required to generate" }}
                        </span>
                    </div>
                </PageCard>
            </div>

            <PageCard
                title="Training JSON"
                description="Paste / edit pack → Import: Knowledge drafts + Language Discovery (abbrev/Banglish) + Experience"
            >
                <p
                    v-if="!apiKeys.length && !isPlatformTarget"
                    class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    No active API keys — pick Platform (all keys), or create a key in Config for merchant training.
                </p>
                <p
                    v-if="isPlatformTarget"
                    class="mb-3 rounded-lg border border-fuchsia-200 bg-fuchsia-50 px-3 py-2 text-sm text-fuchsia-900 dark:border-fuchsia-500/30 dark:bg-fuchsia-500/10 dark:text-fuchsia-100"
                >
                    Platform target — Knowledge drafts + Language reviews are shared across all keys. Experience lane is skipped (merchant-only).
                </p>
                <div class="mb-3 flex flex-wrap items-end gap-3">
                    <label class="block text-xs font-medium text-gray-500">
                        Target
                        <Select
                            v-model="targetValue"
                            :options="targetOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Select target"
                            class="mt-1 w-72"
                            @update:model-value="onTargetChange"
                        />
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm"
                        :class="isPlatformTarget ? 'text-gray-400' : 'text-gray-600 dark:text-gray-300'"
                    >
                        <input
                            v-model="importExperience"
                            type="checkbox"
                            class="rounded"
                            :disabled="isPlatformTarget"
                        />
                        Also import experience signals
                    </label>
                    <Button
                        label="Import pack"
                        icon="pi pi-upload"
                        size="small"
                        :loading="importing"
                        :disabled="!can_edit || !canImport"
                        @click="importPack"
                    />
                    <Link
                        :href="route('wiseAi.knowledge')"
                        class="text-sm font-medium text-fuchsia-700 underline-offset-2 hover:underline dark:text-fuchsia-300"
                    >
                        Knowledge → Publish
                    </Link>
                    <Link
                        :href="route('wiseAi.language', { review: 'open', channel: 'train' })"
                        class="text-sm font-medium text-fuchsia-700 underline-offset-2 hover:underline dark:text-fuchsia-300"
                    >
                        Language → Train queue
                    </Link>
                </div>
                <textarea
                    v-model="jsonText"
                    rows="18"
                    class="w-full rounded-xl border border-gray-200 bg-slate-50 px-3 py-2 font-mono text-xs leading-relaxed text-gray-800 dark:border-gray-700 dark:bg-slate-950 dark:text-gray-100"
                    spellcheck="false"
                />
                <p v-if="message" class="mt-2 text-sm" :class="error ? 'text-rose-600' : 'text-emerald-600'">
                    {{ message }}
                </p>
                <ul v-if="nextSteps.length && !error" class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                    <li v-for="(step, i) in nextSteps" :key="i">{{ step }}</li>
                </ul>
                <ul v-if="importErrors.length" class="mt-2 list-disc space-y-1 pl-5 text-xs text-rose-600 dark:text-rose-300">
                    <li v-for="(err, i) in importErrors" :key="i">{{ err }}</li>
                </ul>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import axios from "axios";
import Button from "primevue/button";
import Select from "primevue/select";
import { useToast } from "primevue/usetoast";
import { Link } from "@inertiajs/vue3";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";

type ApiKey = { id: number; name: string; key_prefix: string };

const props = defineProps<{
    brain_version: string;
    schema_version: string;
    example_pack: Record<string, unknown>;
    example_platform_pack: Record<string, unknown>;
    professional_prompt: string;
    apiKeys: ApiKey[];
    llm: { enabled: boolean; key_set: boolean; model: string };
    can_edit: boolean;
    first_learning_bn: string[];
}>();

const toast = useToast();
/** 'platform' or numeric API key id */
const targetValue = ref<string | number>("platform");
const jsonText = ref(JSON.stringify(props.example_platform_pack, null, 2));
const brief = ref("");
const importExperience = ref(false);
const generating = ref(false);
const importing = ref(false);
const message = ref("");
const error = ref(false);
const nextSteps = ref<string[]>([]);
const importErrors = ref<string[]>([]);

const isPlatformTarget = computed(() => targetValue.value === "platform");
const canImport = computed(() => isPlatformTarget.value || typeof targetValue.value === "number");

const targetOptions = computed(() => [
    { label: "Platform (all keys)", value: "platform" as const },
    ...props.apiKeys.map((k) => ({
        label: `${k.name} (${k.key_prefix}…)`,
        value: k.id,
    })),
]);

const onTargetChange = () => {
    if (isPlatformTarget.value) {
        importExperience.value = false;
    }
};

const loadExample = () => {
    const firstKey = props.apiKeys[0]?.id;
    if (firstKey != null) {
        targetValue.value = firstKey;
        importExperience.value = true;
    }
    jsonText.value = JSON.stringify(props.example_pack, null, 2);
    message.value = firstKey != null
        ? "Merchant example pack loaded — target set to first API key"
        : "Merchant example loaded — create an API key in Config before importing";
    error.value = false;
    nextSteps.value = [];
    importErrors.value = [];
};

const loadPlatformExample = () => {
    targetValue.value = "platform";
    importExperience.value = false;
    jsonText.value = JSON.stringify(props.example_platform_pack, null, 2);
    message.value = "Platform example pack loaded";
    error.value = false;
    nextSteps.value = [];
    importErrors.value = [];
};

const copyPrompt = async () => {
    try {
        await navigator.clipboard.writeText(props.professional_prompt);
        toast.add({ severity: "success", summary: "Prompt copied", life: 2000, group: "br" });
    } catch {
        toast.add({ severity: "warn", summary: "Copy failed — select text manually", life: 2500, group: "br" });
    }
};

const generate = async () => {
    generating.value = true;
    message.value = "";
    error.value = false;
    nextSteps.value = [];
    importErrors.value = [];
    try {
        const { data } = await axios.post(route("wiseAi.train.generate"), {
            brief: brief.value.trim(),
            target_items: 16,
        });
        jsonText.value = JSON.stringify(data.pack, null, 2);
        message.value = data.message || "Generated";
        toast.add({
            severity: "success",
            summary: "Pack generated",
            detail: `${data.model} · ${data.latency_ms}ms — review then Import`,
            life: 2500,
            group: "br",
        });
    } catch (e: unknown) {
        error.value = true;
        const err = e as { response?: { data?: { message?: string } } };
        message.value = err.response?.data?.message || "Generate failed";
    } finally {
        generating.value = false;
    }
};

const importPack = async () => {
    if (!canImport.value) return;
    importing.value = true;
    message.value = "";
    error.value = false;
    nextSteps.value = [];
    importErrors.value = [];
    try {
        let pack: Record<string, unknown>;
        try {
            pack = JSON.parse(jsonText.value);
        } catch {
            error.value = true;
            message.value = "Invalid JSON — fix syntax before import";
            return;
        }
        if (!pack || typeof pack !== "object" || !Array.isArray(pack.items) || pack.items.length === 0) {
            error.value = true;
            message.value = "Pack must include a non-empty items array";
            return;
        }
        const platform = isPlatformTarget.value;
        const { data } = await axios.post(route("wiseAi.train.import"), {
            target: platform ? "platform" : "merchant",
            wise_api_key_id: platform ? null : targetValue.value,
            pack,
            import_experience: platform ? false : importExperience.value,
        });
        message.value = data.message || "Imported";
        nextSteps.value = Array.isArray(data.next_steps) ? data.next_steps : [];
        importErrors.value = Array.isArray(data.stats?.errors) ? data.stats.errors.slice(0, 8) : [];
        const skipped = Number(data.stats?.skipped || 0);
        toast.add({
            severity: skipped > 0 ? "warn" : "success",
            summary: skipped > 0 ? "Imported with skips" : "Imported",
            detail: nextSteps.value[0] || "Review drafts / promote language",
            life: 4000,
            group: "br",
        });
    } catch (e: unknown) {
        error.value = true;
        const err = e as { response?: { data?: { message?: string; stats?: { errors?: string[] } } } };
        message.value = err.response?.data?.message || "Import failed";
        importErrors.value = Array.isArray(err.response?.data?.stats?.errors)
            ? err.response.data.stats.errors.slice(0, 8)
            : [];
    } finally {
        importing.value = false;
    }
};
</script>
