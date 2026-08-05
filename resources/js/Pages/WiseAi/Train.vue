<template>
    <AuthenticatedLayout title="Wise AI — Train">
        <div class="space-y-5">
            <PageHeader
                title="ট্রেইন"
                description="৩ ধাপে শেখান — Publish/Promote না করা পর্যন্ত কাস্টমারের কাছে যায় না"
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

            <WiseAiHowTo
                title="ভালো ট্রেইনিংয়ের নির্দেশনা"
                subtitle="কত ডেটা লাগবে + ৩ ধাপে Import (ড্রাফট) → তারপর Publish/Promote"
                badge="ডেটা গাইড"
                storage-key="wise-ai-train"
                :steps="howToSteps"
                :tips="first_learning_bn"
            />

            <nav class="flex flex-wrap gap-2" aria-label="ট্রেইন ধাপ">
                <button
                    v-for="s in stepMeta"
                    :key="s.id"
                    type="button"
                    class="flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        step === s.id
                            ? 'bg-fuchsia-600 text-white'
                            : step > s.id
                              ? 'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-500/20 dark:text-fuchsia-200'
                              : 'bg-gray-100 text-gray-500 dark:bg-slate-800 dark:text-gray-400'
                    "
                    @click="goStep(s.id)"
                >
                    <span
                        class="flex h-5 w-5 items-center justify-center rounded-full text-[10px]"
                        :class="step === s.id ? 'bg-white/20' : 'bg-black/5 dark:bg-white/10'"
                    >
                        {{ s.id }}
                    </span>
                    {{ s.label }}
                </button>
            </nav>

            <!-- Step 1 -->
            <PageCard
                v-show="step === 1"
                title="১ · আজ কী শেখাবেন?"
                description="একটা টাইপ বেছে নিন — কোথায় যাবে (প্ল্যাটফর্ম/মার্চেন্ট) নিজে থেকে ঠিক হবে"
            >
                <div v-if="recommended_types_bn?.length" class="mb-4">
                    <p class="mb-2 text-xs font-semibold text-gray-500">আজকের জন্য সাজেস্টেড (দ্রুত ফল)</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="rec in recommended_types_bn"
                            :key="rec.value"
                            type="button"
                            class="rounded-full border border-fuchsia-200 bg-fuchsia-50 px-3 py-1.5 text-left text-xs dark:border-fuchsia-500/30 dark:bg-fuchsia-500/10"
                            @click="selectType(rec.value as PromptType)"
                        >
                            <span class="font-semibold text-fuchsia-800 dark:text-fuchsia-200">
                                {{ labelFor(rec.value) }}
                            </span>
                            <span class="mt-0.5 block text-[11px] text-gray-600 dark:text-gray-300">{{ rec.why }}</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="opt in promptTypeOptions"
                        :key="opt.value"
                        type="button"
                        class="rounded-xl border px-4 py-3 text-left transition"
                        :class="
                            promptType === opt.value
                                ? 'border-fuchsia-500 bg-fuchsia-50 ring-2 ring-fuchsia-400/40 dark:border-fuchsia-400 dark:bg-fuchsia-500/15'
                                : 'border-gray-200 bg-white hover:border-fuchsia-300 dark:border-gray-700 dark:bg-slate-900'
                        "
                        @click="selectType(opt.value)"
                    >
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ opt.label_bn || opt.label }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ opt.hint_bn || opt.hint }}
                        </p>
                        <p
                            v-if="opt.volume_bn"
                            class="mt-2 text-[11px] font-medium text-fuchsia-700 dark:text-fuchsia-300"
                        >
                            {{ opt.volume_bn }}
                        </p>
                    </button>
                </div>

                <div
                    v-if="activeVolume"
                    class="mt-4 rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10"
                >
                    <p class="text-xs font-semibold text-amber-900 dark:text-amber-100">
                        এই টাইপে কত ডেটা লাগবে (প্রপার ট্রেইনিং)
                    </p>
                    <p class="mt-1 text-sm text-amber-950 dark:text-amber-50">
                        মিনিমাম
                        <strong>{{ activeVolume.min }}</strong>
                        · টার্গেট
                        <strong>{{ activeVolume.target }}</strong>
                        · শক্তিশালী
                        <strong>{{ activeVolume.strong }}+</strong>
                        আইটেম / প্যাক
                    </p>
                    <p class="mt-1 text-xs text-amber-900/90 dark:text-amber-100/90">{{ activeVolume.lanes_bn }}</p>
                    <p class="mt-1 text-xs font-medium text-amber-950 dark:text-amber-50">
                        সোর্স: {{ activeVolume.source_bn }}
                    </p>
                </div>

                <div
                    v-if="activeCoach.length"
                    class="mt-4 rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-3 dark:border-sky-500/30 dark:bg-sky-500/10"
                >
                    <p class="text-xs font-semibold text-sky-900 dark:text-sky-100">
                        এই টাইপের জন্য মনে রাখুন
                    </p>
                    <ul class="mt-2 space-y-1.5 text-sm text-sky-950/90 dark:text-sky-50/90">
                        <li v-for="(line, i) in activeCoach" :key="i" class="flex gap-2">
                            <span class="font-semibold text-sky-600">{{ i + 1 }}.</span>
                            <span>{{ line }}</span>
                        </li>
                    </ul>
                    <p v-if="activeNextBn" class="mt-2 text-xs font-medium text-sky-800 dark:text-sky-200">
                        পরে: {{ activeNextBn }}
                    </p>
                </div>

                <p v-if="typeKeyWarning" class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                    {{ typeKeyWarning }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        label="পরের ধাপ · প্যাক তৈরি"
                        icon="pi pi-arrow-right"
                        size="small"
                        @click="goStep(2)"
                    />
                </div>
            </PageCard>

            <!-- Step 2 -->
            <PageCard
                v-show="step === 2"
                title="২ · প্যাক তৈরি করুন"
                :description="step2Description"
            >
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">নিরাপদ স্টার্টার</p>
                        <p class="mt-1 text-xs text-gray-500">
                            এই টাইপের জন্য রেডি উদাহরণ — আগে রিভিউ করে Import করুন
                        </p>
                        <Button
                            class="mt-3"
                            label="স্টার্টার লোড"
                            icon="pi pi-file"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="loadStarter"
                        />
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 lg:col-span-2 dark:border-gray-700">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            Wise LLM দিয়ে জেনারেট
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{
                                llm.key_set && llm.enabled
                                    ? `মডেল: ${llm.model} — জেনারেট = শুধু খসড়া JSON, অটো-পাবলিশ নয়`
                                    : "আগে Config → LLM Language-এ API key সেভ করুন"
                            }}
                        </p>
                        <label class="mt-3 block text-xs font-medium text-gray-500">
                            {{ briefLabel }}
                            <BanglaField
                                v-model="brief"
                                multiline
                                :rows="5"
                                class="mt-1 rounded-lg"
                                :placeholder="briefPlaceholder"
                            />
                        </label>
                        <p class="mt-1 text-[11px] text-gray-400">
                            টিপ: Messenger থেকে আসল লাইন পেস্ট করুন। দাম/চার্জ না জানলে লিখবেন না।
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <Button
                                label="JSON তৈরি করুন"
                                icon="pi pi-sparkles"
                                size="small"
                                :loading="generating"
                                :disabled="!can_edit || brief.trim().length < 20"
                                @click="generate"
                            />
                            <Button
                                label="বাইরের প্রম্পট কপি"
                                icon="pi pi-copy"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="copyPrompt"
                            />
                            <Button
                                label="JSON পেস্ট…"
                                icon="pi pi-code"
                                size="small"
                                severity="secondary"
                                text
                                @click="openAdvancedPaste"
                            />
                        </div>
                        <p v-if="!can_edit" class="mt-2 text-[11px] text-amber-600">
                            জেনারেট/Import করতে Editor রোল লাগবে।
                        </p>
                    </div>
                </div>

                <details class="mt-4 rounded-xl border border-gray-200 dark:border-gray-700">
                    <summary
                        class="cursor-pointer select-none px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300"
                    >
                        ChatGPT / Claude-এর জন্য পূর্ণ প্রম্পট দেখুন
                    </summary>
                    <pre
                        class="max-h-56 overflow-auto border-t border-gray-100 bg-slate-950 p-4 font-mono text-[11px] leading-relaxed text-slate-200 whitespace-pre-wrap dark:border-gray-800"
                    >{{ activePrompt }}</pre>
                </details>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        label="পেছনে"
                        icon="pi pi-arrow-left"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="goStep(1)"
                    />
                    <Button
                        label="পরের ধাপ · রিভিউ"
                        icon="pi pi-arrow-right"
                        size="small"
                        :disabled="!packSummary.ok"
                        @click="goStep(3)"
                    />
                    <span v-if="!packSummary.ok" class="self-center text-[11px] text-gray-400">
                        আগে স্টার্টার লোড, জেনারেট, বা JSON পেস্ট করুন
                    </span>
                </div>
            </PageCard>

            <!-- Step 3 -->
            <PageCard
                v-show="step === 3"
                title="৩ · রিভিউ ও Import"
                description="সংখ্যা মিলিয়ে দেখুন → টার্গেট নিশ্চিত → শুধু ড্রাফট হিসেবে Import"
            >
                <div
                    v-if="packSummary.ok"
                    class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50/70 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"
                >
                    {{ reviewCoachBn }}
                </div>
                <p
                    v-if="packSummary.ok && volumeGapBn"
                    class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
                >
                    {{ volumeGapBn }}
                </p>

                <div v-if="packSummary.ok" class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-900">
                        <p class="text-[10px] font-medium text-gray-400">মোট আইটেম</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ packSummary.total }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-900">
                        <p class="text-[10px] font-medium text-gray-400">নলেজ</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ packSummary.knowledge }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-900">
                        <p class="text-[10px] font-medium text-gray-400">ভাষা</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ packSummary.language }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-900">
                        <p class="text-[10px] font-medium text-gray-400">এক্সপেরিয়েন্স</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ packSummary.experience }}</p>
                    </div>
                </div>
                <p v-if="packSummary.unknown > 0" class="mb-3 text-sm text-rose-600">
                    {{ packSummary.unknown }}টি আইটেমের লেন অজানা — Import-এ স্কিপ হবে।
                </p>
                <p v-if="!packSummary.ok" class="mb-3 text-sm text-rose-600">
                    {{ packSummary.error || "প্যাক JSON সঠিক নয়" }}
                </p>

                <p
                    v-if="importBlockReason"
                    class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    {{ importBlockReason }}
                </p>
                <p
                    v-else-if="isPlatformTarget"
                    class="mb-3 rounded-lg border border-fuchsia-200 bg-fuchsia-50 px-3 py-2 text-sm text-fuchsia-900 dark:border-fuchsia-500/30 dark:bg-fuchsia-500/10 dark:text-fuchsia-100"
                >
                    টার্গেট: প্ল্যাটফর্ম — সব API key-এ শেয়ার হবে। এক্সপেরিয়েন্স এখানে যাবে না।
                </p>
                <p
                    v-else
                    class="mb-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-200"
                >
                    টার্গেট: মার্চেন্ট কী — শুধু এই স্টোরের ব্রেইনে যাবে।
                </p>

                <div class="mb-3 flex flex-wrap items-end gap-3">
                    <label class="block text-xs font-medium text-gray-500">
                        কোথায় Import হবে
                        <Select
                            v-model="targetValue"
                            :options="targetOptions"
                            option-label="label"
                            option-value="value"
                            class="mt-1 w-72"
                            :disabled="promptType === 'platform'"
                        />
                    </label>
                    <label
                        v-if="showExperienceToggle"
                        class="flex items-center gap-2 text-sm"
                        :class="isPlatformTarget ? 'text-gray-400' : 'text-gray-600 dark:text-gray-300'"
                    >
                        <input
                            v-model="importExperience"
                            type="checkbox"
                            class="rounded"
                            :disabled="isPlatformTarget"
                        />
                        এক্সপেরিয়েন্স সিগন্যালও নিন
                    </label>
                    <Button
                        label="ড্রাফট হিসেবে Import"
                        icon="pi pi-upload"
                        size="small"
                        :loading="importing"
                        :disabled="!can_edit || !canImport || !packSummary.ok"
                        @click="importPack"
                    />
                </div>

                <div class="flex flex-wrap gap-3 text-sm">
                    <Link
                        :href="route('wiseAi.knowledge')"
                        class="font-medium text-fuchsia-700 underline-offset-2 hover:underline dark:text-fuchsia-300"
                    >
                        নলেজ → Publish
                    </Link>
                    <Link
                        :href="route('wiseAi.language', { review: 'open', channel: 'train' })"
                        class="font-medium text-fuchsia-700 underline-offset-2 hover:underline dark:text-fuchsia-300"
                    >
                        ভাষা → Train কিউ
                    </Link>
                </div>

                <p v-if="message" class="mt-3 text-sm" :class="error ? 'text-rose-600' : 'text-emerald-600'">
                    {{ message }}
                </p>
                <div
                    v-if="nextSteps.length && !error"
                    class="mt-3 rounded-xl border border-violet-200 bg-violet-50/80 px-3 py-2 dark:border-violet-500/30 dark:bg-violet-500/10"
                >
                    <p class="text-xs font-semibold text-violet-900 dark:text-violet-100">এখন কী করবেন</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-violet-950/90 dark:text-violet-50/90">
                        <li v-for="(n, i) in nextSteps" :key="i">{{ n }}</li>
                        <li v-if="activeNextBn">{{ activeNextBn }}</li>
                    </ul>
                </div>
                <ul
                    v-if="importErrors.length"
                    class="mt-2 list-disc space-y-1 pl-5 text-xs text-rose-600 dark:text-rose-300"
                >
                    <li v-for="(err, i) in importErrors" :key="i">{{ err }}</li>
                </ul>

                <details
                    class="mt-5 rounded-xl border border-gray-200 dark:border-gray-700"
                    :open="advancedOpen || undefined"
                    @toggle="advancedOpen = ($event.target as HTMLDetailsElement).open"
                >
                    <summary
                        class="cursor-pointer select-none px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300"
                    >
                        অ্যাডভান্সড — JSON এডিট / পেস্ট
                    </summary>
                    <div class="border-t border-gray-100 p-3 dark:border-gray-800">
                        <textarea
                            v-model="jsonText"
                            rows="14"
                            class="w-full rounded-xl border border-gray-200 bg-slate-50 px-3 py-2 font-mono text-xs leading-relaxed text-gray-800 dark:border-gray-700 dark:bg-slate-950 dark:text-gray-100"
                            spellcheck="false"
                        />
                    </div>
                </details>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        label="পেছনে"
                        icon="pi pi-arrow-left"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="goStep(2)"
                    />
                </div>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
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
import WiseAiHowTo from "./fragments/WiseAiHowTo.vue";
import BanglaField from "@/components/BanglaField.vue";

type ApiKey = { id: number; name: string; key_prefix: string };
type PromptType = "merchant" | "platform" | "knowledge" | "language" | "experience";
type PromptTypeOption = {
    value: PromptType;
    label: string;
    hint: string;
    label_bn?: string;
    hint_bn?: string;
    volume_bn?: string;
    coach_bn?: string[];
    next_bn?: string;
};
type VolumeSpec = { min: number; target: number; strong: number; lanes_bn: string; source_bn: string };

const props = defineProps<{
    brain_version: string;
    schema_version: string;
    example_pack: Record<string, unknown>;
    example_platform_pack: Record<string, unknown>;
    starter_packs?: Partial<Record<PromptType, Record<string, unknown>>>;
    prompts: Record<PromptType, string>;
    prompt_types: PromptTypeOption[];
    apiKeys: ApiKey[];
    llm: { enabled: boolean; key_set: boolean; model: string };
    can_edit: boolean;
    first_learning_bn: string[];
    recommended_types_bn?: { value: string; why: string }[];
    volume_by_type?: Record<string, VolumeSpec>;
}>();

const toast = useToast();
const step = ref(1);
const targetValue = ref<string | number>("platform");
const promptType = ref<PromptType>("language");
const jsonText = ref("");
const brief = ref("");
const importExperience = ref(false);
const generating = ref(false);
const importing = ref(false);
const message = ref("");
const error = ref(false);
const nextSteps = ref<string[]>([]);
const importErrors = ref<string[]>([]);
const advancedOpen = ref(false);

const stepMeta = [
    { id: 1, label: "টাইপ" },
    { id: 2, label: "তৈরি" },
    { id: 3, label: "রিভিউ" },
];

const howToSteps = [
    {
        title: "যথেষ্ট ডেটা জোগাড় করুন",
        detail: "ভাষা ~২৫ সারফেস · নলেজ ~২০ FAQ · ফুল প্যাক ~৩০ — কম হলে খেলনা মোড",
    },
    {
        title: "টাইপ বেছে স্টার্টার/জেনারেট/পেস্ট",
        detail: "Brief-এ Messenger কপি দিন; AI আন্দাজে দাম বানালে বাদ",
    },
    {
        title: "সংখ্যা ≥ মিনিমাম হলে Import → Publish/Promote",
        detail: "Import শুধু ড্রাফট — লাইভ করতে আলাদা অনুমোদন",
    },
];

const isPlatformTarget = computed(() => targetValue.value === "platform");
const needsMerchantKey = computed(() =>
    promptType.value === "merchant" || promptType.value === "knowledge" || promptType.value === "experience",
);
const hasMerchantKey = computed(() => typeof targetValue.value === "number");
const showExperienceToggle = computed(
    () => promptType.value === "merchant" || promptType.value === "experience",
);
const importBlockReason = computed(() => {
    if (needsMerchantKey.value && !hasMerchantKey.value) {
        if (!props.apiKeys.length) {
            return "এই টাইপের জন্য মার্চেন্ট API key লাগবে — আগে Config-এ একটি অ্যাক্টিভ কী বানান।";
        }
        return "এই টাইপ স্টোর-স্পেসিফিক — Import টার্গেট থেকে একটি মার্চেন্ট কী বেছে নিন (প্ল্যাটফর্ম নয়)।";
    }
    return "";
});
const canImport = computed(
    () => (isPlatformTarget.value || hasMerchantKey.value) && !importBlockReason.value,
);
const typeKeyWarning = computed(() => {
    if (needsMerchantKey.value && !props.apiKeys.length) {
        return "সতর্কতা: কোনো অ্যাক্টিভ API key নেই — এই টাইপ Import করা যাবে না যতক্ষণ Config-এ কী না থাকে।";
    }
    return "";
});

const promptTypeOptions = computed(() => props.prompt_types);
const activeOption = computed(() => props.prompt_types.find((t) => t.value === promptType.value));
const activePrompt = computed(() => props.prompts[promptType.value] || props.prompts.merchant);
const activeCoach = computed(() => activeOption.value?.coach_bn || []);
const activeNextBn = computed(() => activeOption.value?.next_bn || "");
const activeVolume = computed(() => props.volume_by_type?.[promptType.value] || null);
const step2Description = computed(() => {
    const vol = activeVolume.value;
    const base = activeOption.value?.hint_bn || activeOption.value?.hint || "";
    return vol
        ? `${base} · টার্গেট ${vol.target} আইটেম (মিনিমাম ${vol.min})`
        : `${base} · ${activeOption.value?.label_bn || promptType.value}`;
});
const volumeGapBn = computed(() => {
    const vol = activeVolume.value;
    if (!vol || !packSummary.value.ok) return "";
    const n = packSummary.value.total;
    if (n < vol.min) {
        return `সতর্কতা: এখন ${n}টি আইটেম — প্রপার ট্রেইনিংয়ের মিনিমাম ${vol.min}. আরও যোগ করে Import করুন (বা আলাদা প্যাক পরে)।`;
    }
    if (n < vol.target) {
        return `ভালো শুরু (${n}/${vol.target} টার্গেট)। আরও ${vol.target - n}টি যোগ করলে কভারেজ মজবুত হবে।`;
    }
    if (n >= vol.strong) {
        return `শক্তিশালী প্যাক (${n}টি) — Import করে Publish/Promote সম্পন্ন করুন।`;
    }
    return `টার্গেট পূরণ (${n}/${vol.target}) — Import করে পরের ধাপে যান।`;
});

const briefLabel = computed(() => {
    if (promptType.value === "platform") return "প্ল্যাটফর্ম ব্রিফ (বাংলা/ইংরেজি)";
    if (promptType.value === "language") return "ভাষা ব্রিফ — কাস্টমার কী টাইপ করে";
    if (promptType.value === "experience") return "এক্সপেরিয়েন্স ব্রিফ — কোন সিচুয়েশনে কী কাজ করেছে";
    if (promptType.value === "knowledge") return "নলেজ ব্রিফ — পলিসি/FAQ (সঠিক তথ্য)";
    return "মার্চেন্ট ব্রিফ (বাংলা/ইংরেজি)";
});
const briefPlaceholder = computed(() => {
    switch (promptType.value) {
        case "platform":
            return "উদাহরণ: সালাম রিপ্লাই, dam koto ক্ল্যারিফাই, রাগান্বিত কাস্টমার হ্যান্ডঅফ — দাম লিখবেন না…";
        case "language":
            return "উদাহরণ: plz, tnx, tmr, tumar, apnar, dam koto, ase — আসল চ্যাট থেকে…";
        case "experience":
            return "উদাহরণ: শুধু ‘দাম?’ বললে আগে প্রোডাক্ট নাম চাই — এতে ভুল কোট কমে…";
        case "knowledge":
            return "উদাহরণ: ঢাকা ডেলিভারি ? টাকা, COD চলে, ৩ দিনে রিটার্ন — শুধু সত্য সংখ্যা…";
        default:
            return "স্টোর নাম, slang, ডেলিভারি, পেমেন্ট, রিটার্ন — আসল তথ্য…";
    }
});

const reviewCoachBn = computed(() => {
    const s = packSummary.value;
    if (!s.ok) return "";
    const bits: string[] = [];
    if (s.knowledge) bits.push(`${s.knowledge}টি নলেজ → পরে Publish`);
    if (s.language) bits.push(`${s.language}টি ভাষা → পরে Promote`);
    if (s.experience) bits.push(`${s.experience}টি এক্সপেরিয়েন্স → soft-hint (Publish লাগে না)`);
    return bits.length
        ? `প্যাকে আছে: ${bits.join(" · ")}। Import চাপলেই লাইভ হবে না — আপনি কন্ট্রোলে।`
        : "প্যাক খালি মনে হচ্ছে — আবার স্টার্টার/জেনারেট চেষ্টা করুন।";
});

const targetOptions = computed(() => [
    { label: "প্ল্যাটফর্ম (সব কী)", value: "platform" as const },
    ...props.apiKeys.map((k) => ({
        label: `মার্চেন্ট: ${k.name} (${k.key_prefix}…)`,
        value: k.id,
    })),
]);

const packSummary = computed(() => {
    try {
        const pack = JSON.parse(jsonText.value || "null");
        if (!pack || typeof pack !== "object" || !Array.isArray(pack.items)) {
            return {
                ok: false,
                error: "প্যাকে items অ্যারে থাকতে হবে",
                total: 0,
                knowledge: 0,
                language: 0,
                experience: 0,
                unknown: 0,
            };
        }
        let knowledge = 0;
        let language = 0;
        let experience = 0;
        let unknown = 0;
        for (const item of pack.items) {
            if (!item || typeof item !== "object") {
                unknown++;
                continue;
            }
            const lane = String((item as { lane?: string }).lane || "knowledge").toLowerCase();
            if (lane === "knowledge") knowledge++;
            else if (lane === "language") language++;
            else if (lane === "experience") experience++;
            else unknown++;
        }
        return {
            ok: pack.items.length > 0,
            error: pack.items.length === 0 ? "items অ্যারে খালি" : "",
            total: pack.items.length,
            knowledge,
            language,
            experience,
            unknown,
        };
    } catch {
        return {
            ok: false,
            error: "JSON সিনট্যাক্স ভুল",
            total: 0,
            knowledge: 0,
            language: 0,
            experience: 0,
            unknown: 0,
        };
    }
});

watch(targetValue, () => {
    if (isPlatformTarget.value) {
        importExperience.value = false;
    }
});

const labelFor = (value: string) =>
    props.prompt_types.find((t) => t.value === value)?.label_bn ||
    props.prompt_types.find((t) => t.value === value)?.label ||
    value;

const applyTypeTarget = (type: PromptType) => {
    if (type === "platform") {
        targetValue.value = "platform";
        importExperience.value = false;
        return;
    }
    if (type === "language") {
        if (!hasMerchantKey.value) {
            targetValue.value = "platform";
        }
        importExperience.value = false;
        return;
    }
    const firstKey = props.apiKeys[0]?.id;
    if (firstKey != null) {
        targetValue.value = firstKey;
    }
    importExperience.value = type === "experience" || type === "merchant";
};

const selectType = (type: PromptType) => {
    promptType.value = type;
    applyTypeTarget(type);
};

const goStep = (n: number) => {
    if (n === 3 && !packSummary.value.ok) {
        toast.add({
            severity: "warn",
            summary: "আগে প্যাক লাগবে",
            detail: "স্টার্টার লোড, জেনারেট, বা JSON পেস্ট করুন",
            life: 2800,
            group: "br",
        });
        return;
    }
    step.value = n;
};

const openAdvancedPaste = () => {
    advancedOpen.value = true;
    step.value = 3;
    toast.add({
        severity: "info",
        summary: "JSON পেস্ট",
        detail: "নিচে পেস্ট করে সংখ্যা চেক করুন, তারপর Import",
        life: 2800,
        group: "br",
    });
};

const loadStarter = () => {
    applyTypeTarget(promptType.value);
    const pack = props.starter_packs?.[promptType.value];
    if (!pack || !Array.isArray((pack as { items?: unknown }).items)) {
        // Fallback: compact examples (toy-sized) if starter_packs missing after deploy lag.
        jsonText.value = JSON.stringify(
            promptType.value === "platform" ? props.example_platform_pack : props.example_pack,
            null,
            2,
        );
        message.value = "কম্প্যাক্ট উদাহরণ লোড — টার্গেট পূরণ করতে আইটেম যোগ করুন";
    } else {
        jsonText.value = JSON.stringify(pack, null, 2);
        const n = ((pack as { items: unknown[] }).items || []).length;
        const target = activeVolume.value?.target;
        message.value =
            target && n >= target
                ? `প্রপার স্টার্টার লোড (${n}/${target} টার্গেট) — ফ্যাক্ট এডিট করে Import`
                : `স্টার্টার লোড (${n}টি) — প্রয়োজনে আরও যোগ করুন`;
    }
    error.value = false;
    nextSteps.value = [];
    importErrors.value = [];
    step.value = 3;
};

const copyPrompt = async () => {
    try {
        await navigator.clipboard.writeText(activePrompt.value);
        toast.add({
            severity: "success",
            summary: "প্রম্পট কপি হয়েছে",
            detail: labelFor(promptType.value),
            life: 2000,
            group: "br",
        });
    } catch {
        toast.add({
            severity: "warn",
            summary: "কপি হয়নি",
            detail: "প্রম্পট খুলে ম্যানুয়ালি সিলেক্ট করুন",
            life: 2500,
            group: "br",
        });
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
            target_items: activeVolume.value?.target || 24,
            prompt_type: promptType.value,
        });
        jsonText.value = JSON.stringify(data.pack, null, 2);
        applyTypeTarget(promptType.value);
        const dropped = Number(data.lanes_dropped || 0);
        message.value =
            dropped > 0
                ? `জেনারেট হয়েছে — ${dropped}টি অফ-টাইপ সারি বাদ (নিরাপত্তা ফিল্টার)`
                : data.message || "জেনারেট হয়েছে — এখন রিভিউ করুন";
        toast.add({
            severity: dropped > 0 ? "warn" : "success",
            summary: "প্যাক তৈরি",
            detail: `${labelFor(String(data.prompt_type || promptType.value))} · ${data.latency_ms}ms`,
            life: 2800,
            group: "br",
        });
        step.value = 3;
    } catch (e: unknown) {
        error.value = true;
        const err = e as { response?: { data?: { message?: string } } };
        message.value = err.response?.data?.message || "জেনারেট ব্যর্থ";
    } finally {
        generating.value = false;
    }
};

const importPack = async () => {
    if (!canImport.value) {
        error.value = true;
        message.value = importBlockReason.value || "সঠিক Import টার্গেট বেছে নিন";
        return;
    }
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
            message.value = "JSON ভুল — অ্যাডভান্সড এডিটরে ঠিক করুন";
            advancedOpen.value = true;
            return;
        }
        if (!pack || typeof pack !== "object" || !Array.isArray(pack.items) || pack.items.length === 0) {
            error.value = true;
            message.value = "প্যাকে কমপক্ষে একটি item লাগবে";
            return;
        }
        if (needsMerchantKey.value && isPlatformTarget.value) {
            error.value = true;
            message.value = importBlockReason.value || "মার্চেন্ট টাইপ প্ল্যাটফর্মে Import করা যাবে না";
            return;
        }
        const platform = isPlatformTarget.value;
        const { data } = await axios.post(route("wiseAi.train.import"), {
            target: platform ? "platform" : "merchant",
            wise_api_key_id: platform ? null : targetValue.value,
            pack,
            import_experience: platform ? false : importExperience.value,
            prompt_type: promptType.value,
        });
        message.value = data.message || "Import হয়েছে (ড্রাফট)";
        nextSteps.value = Array.isArray(data.next_steps) ? data.next_steps : [];
        importErrors.value = Array.isArray(data.stats?.errors) ? data.stats.errors.slice(0, 8) : [];
        const skipped = Number(data.stats?.skipped || 0);
        toast.add({
            severity: skipped > 0 ? "warn" : "success",
            summary: skipped > 0 ? "Import হয়েছে (কিছু স্কিপ)" : "ড্রাফট Import হয়েছে",
            detail: activeNextBn.value || nextSteps.value[0] || "এখন Publish/Promote করুন",
            life: 4500,
            group: "br",
        });
    } catch (e: unknown) {
        error.value = true;
        const err = e as { response?: { data?: { message?: string; stats?: { errors?: string[] } } } };
        message.value = err.response?.data?.message || "Import ব্যর্থ";
        importErrors.value = Array.isArray(err.response?.data?.stats?.errors)
            ? err.response.data.stats.errors.slice(0, 8)
            : [];
    } finally {
        importing.value = false;
    }
};

applyTypeTarget(promptType.value);
{
    const initial =
        props.starter_packs?.[promptType.value] ||
        (promptType.value === "platform" ? props.example_platform_pack : props.example_pack);
    jsonText.value = JSON.stringify(initial, null, 2);
}
</script>
