<template>
    <Dialog
        :visible="visible"
        modal
        header="Turn Replay"
        :style="{ width: 'min(44rem, 96vw)' }"
        dismissable-mask
        @update:visible="emit('update:visible', $event)"
    >
        <div v-if="loading" class="py-6 text-sm text-gray-500">Loading sealed turn…</div>
        <div v-else-if="error" class="py-4 text-sm text-rose-600">{{ error }}</div>
        <div v-else-if="explain" class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge label="replay-safe" variant="success" format="none" />
                <span v-if="explain.turn_id" class="font-mono text-[11px] text-gray-400">#{{ explain.turn_id }}</span>
                <span v-if="replay?.latency_ms != null" class="text-[11px] text-gray-400">{{ replay.latency_ms }} ms</span>
            </div>

            <p class="text-sm text-gray-700 dark:text-gray-200">{{ explain.summary }}</p>

            <section v-if="replay" class="space-y-2 rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Question → Reply</h3>
                <dl class="space-y-1.5 text-xs">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Raw</dt>
                        <dd class="max-w-[70%] text-right font-medium text-gray-800 dark:text-gray-100">“{{ replay.question }}”</dd>
                    </div>
                    <div v-if="replay.canonical && replay.canonical !== replay.question" class="flex justify-between gap-3">
                        <dt class="text-gray-500">Canonical</dt>
                        <dd class="max-w-[70%] text-right font-mono text-[11px]">“{{ replay.canonical }}”</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Intent / action</dt>
                        <dd class="font-medium">{{ replay.intent }} → {{ replay.action }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Suggested reply</dt>
                        <dd class="max-w-[70%] text-right text-gray-700 dark:text-gray-200">
                            {{ replay.suggested_reply || "—" }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="space-y-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Timeline</h3>
                <ol class="space-y-2">
                    <li
                        v-for="(step, idx) in explain.timeline || []"
                        :key="idx"
                        class="rounded-lg border border-gray-100 px-3 py-2 text-xs dark:border-gray-800"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ step.title || step.step || idx }}</span>
                            <StatusBadge
                                v-if="step.status"
                                :label="String(step.status)"
                                variant="neutral"
                                format="none"
                                class="ml-auto"
                            />
                        </div>
                        <p class="mt-0.5 text-gray-500">{{ step.detail || "" }}</p>
                    </li>
                </ol>
            </section>

            <section v-if="corpusPacks.length || overlayHash" class="space-y-2 rounded-xl border border-fuchsia-100 bg-fuchsia-50/40 p-3 dark:border-fuchsia-900/40 dark:bg-fuchsia-950/20">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-fuchsia-700 dark:text-fuchsia-300">
                    Language corpus (sealed)
                </h3>
                <ul v-if="corpusPacks.length" class="space-y-1 font-mono text-[11px] text-gray-700 dark:text-gray-200">
                    <li v-for="(pack, i) in corpusPacks" :key="i">
                        {{ pack.slug || "pack" }}@{{ pack.version || "?" }}
                        <span class="text-gray-400">{{ shortHash(pack.artifact_hash) }}</span>
                        <span v-if="pack.target_type" class="text-gray-400">· {{ pack.target_type }}</span>
                    </li>
                </ul>
                <p v-else class="text-xs text-gray-500">No pack artifacts on this turn.</p>
                <p v-if="overlayHash" class="text-[11px] text-gray-600 dark:text-gray-300">
                    Overlays: {{ overlayCount }} · hash {{ shortHash(overlayHash) }}
                </p>
                <p v-if="answers?.why_corpus" class="text-[11px] text-gray-500">{{ answers.why_corpus }}</p>
            </section>

            <section v-if="hasEvidence" class="space-y-2 rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Evidence</h3>
                <dl class="space-y-1 text-xs">
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">Knowledge</dt>
                        <dd class="font-mono">#{{ evidence.knowledge_id }} v{{ evidence.knowledge_version }}</dd>
                    </div>
                    <div v-if="evidence.title" class="flex justify-between gap-2">
                        <dt class="text-gray-500">Title</dt>
                        <dd class="max-w-[70%] text-right">{{ evidence.title }}</dd>
                    </div>
                    <div v-if="evidence.answer_hash" class="flex justify-between gap-2">
                        <dt class="text-gray-500">Answer hash</dt>
                        <dd class="font-mono text-[11px]">{{ shortHash(evidence.answer_hash) }}</dd>
                    </div>
                    <div v-if="evidence.match_score != null" class="flex justify-between gap-2">
                        <dt class="text-gray-500">Match</dt>
                        <dd>{{ evidence.match_score }}</dd>
                    </div>
                </dl>
            </section>

            <section v-if="sealedVersions.length" class="space-y-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sealed versions</h3>
                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-for="row in sealedVersions"
                        :key="row.label"
                        class="rounded-full bg-gray-100 px-2 py-0.5 font-mono text-[10px] text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{ row.label }} {{ row.value }}
                    </span>
                </div>
            </section>
        </div>
    </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import axios from "axios";
import Dialog from "primevue/dialog";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";

type CorpusPack = {
    slug?: string;
    version?: string;
    artifact_hash?: string;
    target_type?: string;
};

type ExplainPayload = {
    turn_id?: number;
    summary?: string;
    timeline?: { title?: string; step?: string; detail?: string; status?: string }[];
    answers?: Record<string, string>;
    sealed?: Record<string, unknown>;
    replay?: {
        question?: string;
        canonical?: string;
        intent?: string;
        action?: string;
        suggested_reply?: string | null;
        evidence?: Record<string, unknown>;
        latency_ms?: number | null;
    };
};

const props = defineProps<{
    visible: boolean;
    turnId: number | null;
}>();

const emit = defineEmits<{
    "update:visible": [boolean];
}>();

const loading = ref(false);
const error = ref("");
const explain = ref<ExplainPayload | null>(null);
/** Monotonic load id — ignore stale responses when switching turns quickly. */
let loadSeq = 0;

const replay = computed(() => explain.value?.replay ?? null);
const answers = computed(() => explain.value?.answers ?? null);
const evidence = computed(() => (replay.value?.evidence ?? {}) as Record<string, unknown>);
const hasEvidence = computed(() => evidence.value.knowledge_id != null);

const corpus = computed(() => {
    const sealed = explain.value?.sealed ?? {};
    return (sealed.language_corpus_snapshot ?? {}) as {
        packs?: CorpusPack[];
        overlays?: { content_hash?: string; entry_count?: number };
    };
});
const corpusPacks = computed(() => corpus.value.packs ?? []);
const overlayHash = computed(() => corpus.value.overlays?.content_hash ?? "");
const overlayCount = computed(() => corpus.value.overlays?.entry_count ?? 0);

const sealedVersions = computed(() => {
    const s = explain.value?.sealed ?? {};
    const keys: [string, string][] = [
        ["brain", "brain_version"],
        ["dict", "dict_version"],
        ["bclc", "bclc_protocol_version"],
        ["compiler", "bclc_compiler_version"],
        ["knowledge", "knowledge_schema_version"],
        ["mode", "mode"],
        ["at", "sealed_at"],
    ];
    return keys
        .map(([label, key]) => ({ label, value: s[key] != null && s[key] !== "" ? String(s[key]) : null }))
        .filter((row): row is { label: string; value: string } => row.value != null);
});

const shortHash = (value: unknown) => {
    const s = String(value ?? "");
    return s ? s.slice(0, 8) : "—";
};

const load = async (turnId: number) => {
    const seq = ++loadSeq;
    loading.value = true;
    error.value = "";
    explain.value = null;
    try {
        const { data } = await axios.get(route("wiseAi.turns.replay", turnId));
        if (seq !== loadSeq) {
            return;
        }
        explain.value = (data.explain as ExplainPayload) || null;
        if (!explain.value) {
            error.value = "Empty replay payload";
        }
    } catch {
        if (seq !== loadSeq) {
            return;
        }
        error.value = "Failed to load sealed Replay";
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
};

watch(
    () => [props.visible, props.turnId] as const,
    ([visible, turnId]) => {
        if (visible && turnId) {
            void load(turnId);
        }
    },
);
</script>
