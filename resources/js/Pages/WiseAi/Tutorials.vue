<template>
    <AuthenticatedLayout title="Wise AI — Help">
        <div class="space-y-5">
            <PageHeader
                title="Help"
                description="Daily path first — then deeper cards for Knowledge, Language, Experience, LLM"
                icon="PhGraduationCap"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            />

            <WiseAiSubNav />

            <PageCard title="৫ ধাপে শুরু" description="প্রতিদিনের প্রাইমারি পথ — Advanced টুল পরে">
                <ol class="space-y-3">
                    <li v-for="(step, i) in checklist" :key="i" class="flex gap-3 text-sm">
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-fuchsia-600 text-xs font-bold text-white"
                        >
                            {{ i + 1 }}
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white">{{ step.title }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ step.detail }}</p>
                            <Link
                                :href="route(step.route, step.params || {})"
                                class="mt-1 inline-block text-xs font-semibold text-fuchsia-700 hover:underline dark:text-fuchsia-300"
                            >
                                {{ step.linkLabel }} →
                            </Link>
                        </div>
                    </li>
                </ol>
            </PageCard>

            <button
                type="button"
                class="text-sm font-semibold text-fuchsia-700 hover:underline dark:text-fuchsia-300"
                @click="showMore = !showMore"
            >
                {{ showMore ? "Hide detailed guides" : "More guides (Knowledge, Language, Experience…)" }}
            </button>

            <div v-if="showMore" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <PageCard
                    v-for="section in sections"
                    :key="section.id"
                    :title="section.title"
                    :description="section.bn"
                >
                    <ol class="list-decimal space-y-2 pl-5 text-sm text-gray-700 dark:text-gray-200">
                        <li v-for="(step, i) in section.steps" :key="i">{{ step }}</li>
                    </ol>
                    <ul v-if="section.tips?.length" class="mt-3 space-y-1 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <li
                            v-for="(tip, i) in section.tips"
                            :key="'t' + i"
                            class="text-xs text-gray-500 dark:text-gray-400"
                        >
                            • {{ tip }}
                        </li>
                    </ul>
                    <div v-if="section.link" class="mt-3">
                        <Link
                            :href="route(section.link.name)"
                            class="text-sm font-medium text-fuchsia-700 underline-offset-2 hover:underline dark:text-fuchsia-300"
                        >
                            {{ section.link.label }} →
                        </Link>
                    </div>
                </PageCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";

defineProps<{
    brain_version: string;
}>();

const showMore = ref(false);

const checklist = [
    {
        title: "Config — API key",
        detail: "Generate a Wise key and paste into WEL or Playground.",
        route: "wiseAi.config",
        linkLabel: "Open Config",
    },
    {
        title: "Knowledge — Publish facts",
        detail: "Add FAQ/policy drafts, then Publish so decide can answer.",
        route: "wiseAi.knowledge",
        linkLabel: "Open Knowledge",
    },
    {
        title: "Language — Promote slang",
        detail: "Approve plz / tumar / Train queue rows (never auto-learn).",
        route: "wiseAi.language",
        params: { review: "open" },
        linkLabel: "Open Language",
    },
    {
        title: "Playground — Test decide",
        detail: "Send a real customer-style message through the public API.",
        route: "wiseAi.playground",
        linkLabel: "Open Playground",
    },
    {
        title: "Learning — Review inbox",
        detail: "Approve suggestions, draft missed answers, ignore noise.",
        route: "wiseAi.learning",
        linkLabel: "Open Learning",
    },
];

const sections = [
    {
        id: "use",
        title: "How to use (live flywheel)",
        bn: "কীভাবে চালাবেন — decide → Learning → feedback",
        steps: [
            "Config → generate a Wise API key and paste it into the WEL adapter (or Playground).",
            "Publish Knowledge (FAQ / catalog drafts) — unpublished rows never answer business facts.",
            "Send a real Messenger inbound (or Playground message) → open Learning and review the suggestion.",
            "👍 / 👎 / edit feeds Learning + Experience. Never enable Auto at launch.",
        ],
        tips: ["Dashboard “আজ করুন” shows reviews, language, and drafts."],
        link: { name: "wiseAi.dashboard", label: "Open Dashboard" },
    },
    {
        id: "train-knowledge",
        title: "How to train Knowledge (facts)",
        bn: "সত্য শেখানো — draft → human publish",
        steps: [
            "Add FAQ / policy in Knowledge, or sync WooCommerce catalog (draft upsert).",
            "Or use Advanced → Train JSON packs → Import as drafts.",
            "Review drafts → Publish. Only published items ground decide.",
            "Missed answers in Learning → Draft FAQ → Publish.",
        ],
        tips: [
            "External fact training = POST /knowledge/upsert (still draft) or hub Knowledge UI.",
            "Experience never becomes Knowledge automatically.",
        ],
        link: { name: "wiseAi.knowledge", label: "Open Knowledge" },
    },
    {
        id: "train-language",
        title: "How to train Language (abbrev + Banglish)",
        bn: "লোকালাইজেশন — plz / tumar → Promote",
        steps: [
            "Advanced → Train: lane=language items, or Generate from a slang brief.",
            "Import → open rows appear in Language (channel=train).",
            "Approve/Promote each mapping — never auto-published from Train.",
            "Next decide/normalize uses published merchant entries.",
        ],
        tips: [
            "Skip bare ambiguous tokens like “pp” unless meaning is clear.",
            "Train seeds the queue only — Promote still requires an editor.",
        ],
        link: { name: "wiseAi.language", label: "Open Language" },
    },
    {
        id: "experience",
        title: "How Experience learns (what worked)",
        bn: "কী কাজ করেছে — Knowledge নয়",
        steps: [
            "System: Assist / API feedback (accept / edit / reject) writes experience signals.",
            "System: attributed order_paid can add a soft commerce_assist signal.",
            "Outside: POST /api/wise/v1/experience with your API key (adapters / trainers).",
            "Runtime uses experience for soft confidence / style hints only — never invents prices.",
        ],
        tips: ["Conversation memory (last turns) is not training — it is short-thread context."],
        link: { name: "wiseAi.learning", label: "Open Learning" },
    },
    {
        id: "llm",
        title: "LLM Language layer (wording only)",
        bn: "উপস্থাপনা — সিদ্ধান্ত নয়",
        steps: [
            "Config → LLM Language (below API keys): default ON; turn OFF anytime.",
            "Save a Wise OpenAI key (or set WISE_OPENAI_API_KEY). Missing key = fail-open.",
            "LLM may rewrite suggested_reply tone after Judge — digit guard blocks invented numbers.",
            "Judge + Knowledge still own meaning. LLM never auto-sends.",
        ],
        tips: ["Per-key feature flag llm_language can also disable wording for a sandbox key."],
        link: { name: "wiseAi.config", label: "Open Config" },
    },
    {
        id: "never",
        title: "What never auto-learns",
        bn: "যা কখনোই নিজে থেকে publish হয় না",
        steps: [
            "Knowledge publish / unpublish — human only.",
            "Language pack surfaces from Discovery — human promote only.",
            "Auto-send to customers — off; Assist only.",
            "Experience → Knowledge promotion — not allowed; keep lanes separate.",
        ],
        tips: ["Trust first. Intelligence second."],
        link: { name: "wiseAi.dashboard", label: "Open Dashboard" },
    },
];
</script>
