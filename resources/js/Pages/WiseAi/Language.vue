<template>
    <AuthenticatedLayout title="Wise AI — Language">
        <div class="space-y-5">
            <PageHeader
                title="Language"
                description="Abbrev / Banglish Approve করুন — plz, tumar… কাস্টমার লেখা বোঝার জন্য"
                icon="PhTranslate"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <StatusBadge :label="`${localStats.open} open`" :variant="localStats.open > 0 ? 'warning' : 'success'" format="none" />
                </template>
            </PageHeader>
            <WiseAiSubNav />

            <WiseAiHowTo
                title="Language কীভাবে ব্যবহার করবেন"
                subtitle="Open queue → Approve → পরের মেসেজে normalize কাজ করে"
                badge="Promote"
                storage-key="language"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <PageCard
                title="BCLC packs"
                description="Compiled packs (technical) — regional opt-in when context.region set"
            >
                <button
                    type="button"
                    class="mb-2 text-xs font-semibold text-fuchsia-700 hover:underline dark:text-fuchsia-300"
                    @click="showPacks = !showPacks"
                >
                    {{ showPacks ? "Hide pack list" : "Show pack list" }}
                </button>
                <div v-show="showPacks">
                <DataTable v-if="bclc_packs.length" :value="bclc_packs" size="small" striped-rows class="professional-table">
                    <Column field="slug" header="Pack">
                        <template #body="{ data }">
                            <code class="font-mono text-sm">{{ data.slug }}</code>
                            <div class="text-[11px] text-gray-400">{{ data.name }} · {{ data.kind }}</div>
                        </template>
                    </Column>
                    <Column header="Status">
                        <template #body="{ data }">
                            <StatusBadge :label="data.status" variant="neutral" format="none" />
                            <div class="text-[10px] text-gray-400">v{{ data.semver }}</div>
                        </template>
                    </Column>
                    <Column header="Assign">
                        <template #body="{ data }">
                            <div v-for="(a, i) in data.assignments" :key="i" class="text-[11px] text-gray-600 dark:text-gray-300">
                                {{ a.target_type }}{{ a.target_id ? `:${a.target_id}` : "" }} · p{{ a.priority }}
                            </div>
                            <span v-if="!data.assignments?.length" class="text-[11px] text-gray-400">—</span>
                        </template>
                    </Column>
                    <Column header="Artifact">
                        <template #body="{ data }">
                            <code class="font-mono text-[11px]">{{ data.artifact_hash || "—" }}</code>
                        </template>
                    </Column>
                </DataTable>
                <p v-else class="text-sm text-gray-500">No packs yet — run <code>php artisan wise:bclc-bootstrap</code>.</p>
                </div>
            </PageCard>

            <PageCard title="Approve queue" description="Train packs + unknown slang — Approve করে mapping লাইভ করুন (auto-learn নেই)">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <Link
                        v-for="tab in reviewTabs"
                        :key="tab.value"
                        :href="route('wiseAi.language', reviewQuery(tab.value))"
                        class="rounded-full px-3 py-1 text-xs font-semibold transition"
                        :class="
                            review_filter === tab.value
                                ? 'bg-fuchsia-600 text-white'
                                : 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-gray-300'
                        "
                    >
                        {{ tab.label }} ({{ tab.count }})
                    </Link>
                    <Link
                        :href="route('wiseAi.language', { review: review_filter, channel: 'train' })"
                        class="rounded-full px-3 py-1 text-xs font-semibold transition"
                        :class="
                            channel_filter === 'train'
                                ? 'bg-violet-600 text-white'
                                : 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-gray-300'
                        "
                    >
                        Train only
                    </Link>
                    <Link
                        v-if="channel_filter === 'train'"
                        :href="route('wiseAi.language', { review: review_filter })"
                        class="text-xs text-gray-500 underline-offset-2 hover:underline"
                    >
                        Clear Train filter
                    </Link>
                </div>

                <DataTable v-if="reviews.length" :value="reviews" size="small" striped-rows class="professional-table">
                    <Column field="token" header="Surface">
                        <template #body="{ data }">
                            <code class="font-mono text-sm">{{ data.token }}</code>
                            <div class="text-[11px] text-gray-400">
                                {{ data.key_name }} · hits {{ data.hit_count }}
                                <span v-if="data.rank_score != null"> · rank {{ Number(data.rank_score).toFixed(1) }}</span>
                                <span v-if="data.key_breadth"> · keys {{ data.key_breadth }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Suggest">
                        <template #body="{ data }">
                            <div class="text-xs text-gray-600 dark:text-gray-300">
                                <span class="font-medium">{{ data.suggested_pack_slug || "—" }}</span>
                                <span v-if="data.suggested_category"> / {{ data.suggested_category }}</span>
                            </div>
                            <div class="text-[10px] text-gray-400">
                                {{ data.kind || "token" }}{{ data.channel ? ` · ${data.channel}` : "" }}
                                <span
                                    v-if="data.channel === 'train'"
                                    class="ml-1 rounded bg-fuchsia-50 px-1 py-0.5 text-[9px] font-medium text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300"
                                >Train</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Sample / proposed">
                        <template #body="{ data }">
                            <span
                                class="block max-w-[220px] truncate text-xs"
                                :title="data.sample_text || ''"
                            >
                                <template v-if="data.channel === 'train' && data.sample_text">
                                    → {{ data.sample_text }}
                                </template>
                                <template v-else>{{ data.sample_text || "—" }}</template>
                            </span>
                        </template>
                    </Column>
                    <Column header="Status">
                        <template #body="{ data }">
                            <StatusBadge :label="data.status" variant="neutral" format="none" />
                            <div v-if="data.entry_type" class="mt-0.5 text-[10px] text-gray-400">
                                {{ data.entry_type }}: {{ data.entry_from }} → {{ data.entry_to || "(strip)" }}
                            </div>
                        </template>
                    </Column>
                    <Column header="Action">
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
                                <template v-if="data.status === 'open' && can_edit">
                                    <Button
                                        label="Approve"
                                        icon="pi pi-plus"
                                        size="small"
                                        text
                                        @click="openPromote(data)"
                                    />
                                    <Button
                                        label="Ignore"
                                        icon="pi pi-eye-slash"
                                        size="small"
                                        severity="secondary"
                                        text
                                        :loading="ignoringId === data.id"
                                        @click="ignoreReview(data)"
                                    />
                                </template>
                                <span v-else-if="data.status === 'open'" class="text-[11px] text-gray-400">Editor only</span>
                            </div>
                        </template>
                    </Column>
                </DataTable>
                <EmptyState
                    v-else
                    icon="PhTranslate"
                    title="এই ফিল্টারে review নেই"
                    description="Playground-এ অজানা শব্দ পাঠালে এখানে open হিসেবে জমা হবে"
                />
            </PageCard>

            <PageCard title="Try normalize" description="Admin-only preview — production still goes through decide">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-xs text-gray-500">Raw message</label>
                        <input
                            v-model="sample"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="vai dam koto / tnx / 👍"
                            @keyup.enter="runNormalize"
                        />
                    </div>
                    <div class="w-full sm:w-44">
                        <label class="mb-1 block text-xs text-gray-500">Region (opt-in)</label>
                        <Select
                            v-model="sampleRegion"
                            :options="regionOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="None"
                            show-clear
                            class="w-full"
                        />
                    </div>
                    <Button label="Normalize" icon="pi pi-sparkles" :loading="running" @click="runNormalize" />
                </div>
                <div
                    v-if="preview"
                    class="mt-4 space-y-2 rounded-xl border border-gray-100 bg-gray-50/80 p-3 text-sm dark:border-gray-800 dark:bg-slate-800/40"
                >
                    <p>
                        <span class="text-gray-500">Canonical:</span>
                        <span class="font-medium">{{ preview.canonical }}</span>
                    </p>
                    <p v-if="preview.corpus_snapshot?.region || preview.dict_version">
                        <span class="text-gray-500">Corpus:</span>
                        <span class="font-mono text-xs">{{ preview.corpus_snapshot?.region || "platform" }}</span>
                        <span v-if="preview.dict_version" class="ml-2 font-mono text-[11px] text-gray-400">{{ preview.dict_version }}</span>
                    </p>
                    <p v-if="preview.rules_applied?.length">
                        <span class="text-gray-500">Rules:</span>
                        {{ preview.rules_applied.map((r: any) => `${r.from}→${r.to}`).join(" · ") }}
                    </p>
                    <p v-if="preview.unknown_tokens?.length">
                        <span class="text-rose-600">Unknown:</span> {{ preview.unknown_tokens.join(", ") }}
                    </p>
                </div>
            </PageCard>

            <PageCard title="Approved human entries" description="Published from Review — merged into normalize">
                <DataTable
                    v-if="approved_entries.length"
                    :value="approved_entries"
                    size="small"
                    striped-rows
                    class="professional-table"
                >
                    <Column field="type" header="Type" />
                    <Column field="from" header="From" />
                    <Column field="to" header="To" />
                    <Column field="key_name" header="Scope" />
                </DataTable>
                <p v-else class="text-sm text-gray-500">এখনো কোনো promoted entry নেই।</p>
            </PageCard>

            <PageCard title="Platform pack entries" :no-padding="true">
                <DataTable :value="filtered" size="small" striped-rows class="professional-table" paginator :rows="15">
                    <Column field="type" header="Type" style="width: 8rem" />
                    <Column field="from" header="From" />
                    <Column field="to" header="To" />
                </DataTable>
                <div class="border-t border-gray-100 p-3 dark:border-gray-800">
                    <input
                        v-model="filter"
                        type="text"
                        placeholder="Search pack…"
                        class="h-10 w-full max-w-sm rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none dark:border-gray-700 dark:bg-slate-900"
                    />
                </div>
            </PageCard>

            <Dialog
                v-model:visible="showPromote"
                header="Approve into corpus"
                modal
                :style="{ width: '28rem' }"
                dismissable-mask
            >
                <div v-if="activeReview" class="space-y-3">
                    <p class="text-sm">
                        Surface: <code class="font-mono font-semibold">{{ activeReview.token }}</code>
                        <span
                            v-if="activeReview.channel === 'train'"
                            class="ml-2 rounded bg-fuchsia-50 px-1.5 py-0.5 text-[10px] font-medium text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300"
                        >
                            From Train pack
                        </span>
                    </p>
                    <p v-if="activeReview.suggested_pack_slug" class="text-[11px] text-gray-500">
                        Suggested: {{ activeReview.suggested_pack_slug }} / {{ activeReview.suggested_category }}
                        · Platform scope also writes BCLC surface + recompile
                    </p>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Type</label>
                        <Select
                            v-model="promoteForm.type"
                            :options="typeOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </div>
                    <div v-if="promoteForm.type !== 'filler'">
                        <label class="mb-1 block text-xs text-gray-500">Canonical / expand to</label>
                        <BanglaField v-model="promoteForm.to_text" placeholder="thank you / দাম" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Pack slug</label>
                        <input
                            v-model="promoteForm.pack_slug"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none dark:border-gray-700 dark:bg-slate-900"
                            placeholder="core-bd"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Scope</label>
                        <Select
                            v-model="promoteForm.scope"
                            :options="scopeOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </div>
                </div>
                <template #footer>
                    <Button label="Cancel" severity="secondary" outlined size="small" @click="showPromote = false" />
                    <Button
                        label="Publish entry"
                        size="small"
                        :loading="promoting"
                        :disabled="promoteForm.type !== 'filler' && !promoteForm.to_text.trim()"
                        @click="savePromote"
                    />
                </template>
            </Dialog>

            <TurnReplayDialog v-model:visible="replayOpen" :turn-id="replayTurnId" />
        </div>
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

type Entry = { type: string; from: string; to: string };
type ReviewRow = {
    id: number;
    token: string;
    kind?: string;
    channel?: string | null;
    sample_text: string | null;
    hit_count: number;
    rank_score?: number | null;
    key_breadth?: number;
    suggested_pack_slug?: string | null;
    suggested_category?: string | null;
    turn_id?: number | null;
    status: string;
    key_name: string | null;
    wise_api_key_id: number | null;
    entry_from?: string | null;
    entry_to?: string | null;
    entry_type?: string | null;
    last_seen_at: string | null;
};

const props = defineProps<{
    dict_version: string;
    brain_version: string;
    ambiguous: string[];
    entries: Entry[];
    bclc_packs?: Array<{
        slug: string;
        kind: string;
        name: string;
        status: string;
        semver: string;
        locale_scope?: string | null;
        region?: string | null;
        artifact_hash?: string | null;
        assignments: Array<{ target_type: string; target_id: string | null; priority: number }>;
    }>;
    review_filter: string;
    channel_filter?: string | null;
    review_stats: { open: number; ignored: number; promoted: number; all: number };
    reviews: ReviewRow[];
    approved_entries: Array<{ id: number; type: string; from: string; to: string | null; key_name: string; enabled: boolean }>;
    can_edit?: boolean;
    region_ui_options?: Array<{ value: string; label: string }>;
    region_place_coverage?: Record<string, string[]>;
}>();

const reviewQuery = (review: string) => {
    const q: Record<string, string> = { review };
    if (props.channel_filter === "train") q.channel = "train";
    return q;
};

const bclc_packs = computed(() => props.bclc_packs ?? []);

const toast = useToast();
const showPacks = ref(false);
const filter = ref("");
const sample = ref("bjyulok");
const sampleRegion = ref<string | null>(null);
const running = ref(false);
const preview = ref<any>(null);
const showPromote = ref(false);
const promoting = ref(false);
const ignoringId = ref<number | null>(null);
const activeReview = ref<ReviewRow | null>(null);
const localStats = ref({ ...props.review_stats });
const can_edit = props.can_edit !== false;

const replayOpen = ref(false);
const replayTurnId = ref<number | null>(null);
const openReplay = (turnId: number) => {
    replayTurnId.value = turnId;
    replayOpen.value = true;
};

const promoteForm = reactive({
    type: "abbrev",
    to_text: "",
    scope: "merchant",
    pack_slug: "core-bd",
    category: "abbrev",
});

const typeOptions = [
    { label: "Abbreviation", value: "abbrev" },
    { label: "SMS", value: "sms" },
    { label: "Banglish", value: "banglish" },
    { label: "Phonetic", value: "phonetic" },
    { label: "Commerce", value: "commerce" },
    { label: "Messenger", value: "messenger" },
    { label: "Filler (strip)", value: "filler" },
];

const scopeOptions = [
    { label: "This API key (merchant)", value: "merchant" },
    { label: "Platform (all keys)", value: "platform" },
];

const regionOptions = computed(() => {
    if (props.region_ui_options?.length) {
        return props.region_ui_options.map((o) => ({ label: o.label, value: o.value }));
    }
    const fromPacks = (props.bclc_packs ?? [])
        .filter((p) => p.kind === "region" && p.region)
        .map((p) => ({ label: p.name || String(p.region), value: String(p.region) }));
    if (fromPacks.length) return fromPacks;
    return [
        { label: "Chattogram", value: "chattogram" },
        { label: "Sylhet", value: "sylhet" },
        { label: "Noakhali", value: "noakhali" },
        { label: "Barisal", value: "barisal" },
        { label: "Rajshahi", value: "rajshahi" },
        { label: "Bogura", value: "bogura" },
        { label: "Khulna", value: "khulna" },
        { label: "Rangpur", value: "rangpur" },
        { label: "Mymensingh · Kishoreganj, Haluaghat…", value: "mymensingh" },
    ];
});

const reviewTabs = computed(() => [
    { value: "open", label: "Open", count: localStats.value.open },
    { value: "ignored", label: "Ignored", count: localStats.value.ignored },
    { value: "promoted", label: "Promoted", count: localStats.value.promoted },
    { value: "all", label: "All", count: localStats.value.all },
]);

const filtered = computed(() => {
    const q = filter.value.trim().toLowerCase();
    if (!q) return props.entries;
    return props.entries.filter(
        (e) => e.type.includes(q) || e.from.toLowerCase().includes(q) || e.to.toLowerCase().includes(q),
    );
});

const howToSteps = [
    { title: "Decide চলে", detail: "Unknown tokens/phrases → Discovery Queue (ranked)। Auto-learn নেই।" },
    { title: "Approve", detail: "Type + to + pack। Platform scope → BCLC surface + recompile।" },
    { title: "পরের message", detail: "Compiled pack / published entry দিয়ে normalize।" },
    { title: "Ignore", detail: "Spam/noise — Ignore; আবার দেখা দিলে open হতে পারে।" },
];

const howToTips = [
    "pp-এর মতো ambiguous token এখান থেকে map করা যাবে না।",
    "Train pack থেকে আসা rows-এ → proposed expansion দেখাবে; Approve করলে prefill হবে।",
    "Rank = hits × merchant breadth × recency। Editor role লাগে promote/ignore-এর জন্য।",
];

const runNormalize = async () => {
    if (!sample.value.trim() || running.value) return;
    running.value = true;
    try {
        const payload: { text: string; region?: string } = { text: sample.value };
        if (sampleRegion.value) payload.region = sampleRegion.value;
        const { data } = await axios.post(route("wiseAi.language.normalize"), payload);
        preview.value = data.language;
    } finally {
        running.value = false;
    }
};

const openPromote = (row: ReviewRow) => {
    activeReview.value = row;
    const cat = row.suggested_category || "banglish";
    promoteForm.type = ["abbrev", "sms", "banglish", "phonetic", "commerce", "filler", "messenger"].includes(cat)
        ? cat
        : "banglish";
    // Train imports store proposed expansion in sample_text for one-click promote.
    promoteForm.to_text =
        row.channel === "train" && row.sample_text ? String(row.sample_text) : "";
    // Platform Train reviews have null key — default to Platform promote (BCLC).
    promoteForm.scope = row.wise_api_key_id == null ? "platform" : "merchant";
    promoteForm.pack_slug = row.suggested_pack_slug || "core-bd";
    promoteForm.category = cat;
    showPromote.value = true;
};

const savePromote = async () => {
    if (!activeReview.value || promoting.value) return;
    promoting.value = true;
    try {
        const { data } = await axios.post(
            route("wiseAi.language.reviews.promote", { review: activeReview.value.id }),
            {
                type: promoteForm.type,
                to_text: promoteForm.to_text,
                scope: promoteForm.scope,
                pack_slug: promoteForm.pack_slug,
                category: promoteForm.category || promoteForm.type,
            },
        );
        if (data.stats) localStats.value = data.stats;
        showPromote.value = false;
        toast.add({
            severity: "success",
            summary: "Approved",
            detail: data.bclc?.artifact_hash
                ? `${data.entry.from} · pack ${data.bclc.pack_slug} · ${data.bclc.artifact_hash}`
                : `${data.entry.from} → ${data.entry.to || "(filler)"}`,
            life: 3000,
            group: "br",
        });
        router.reload({ only: ["reviews", "approved_entries", "review_stats", "bclc_packs"] });
    } catch {
        toast.add({ severity: "error", summary: "Promote failed", life: 3500, group: "br" });
    } finally {
        promoting.value = false;
    }
};

const ignoreReview = async (row: ReviewRow) => {
    if (ignoringId.value === row.id) return;
    ignoringId.value = row.id;
    try {
        const { data } = await axios.post(route("wiseAi.language.reviews.ignore", { review: row.id }));
        if (data.stats) localStats.value = data.stats;
        toast.add({ severity: "success", summary: "Ignored", life: 2000, group: "br" });
        router.reload({ only: ["reviews", "review_stats"] });
    } catch {
        toast.add({ severity: "error", summary: "Ignore failed", life: 3500, group: "br" });
    } finally {
        ignoringId.value = null;
    }
};
</script>
