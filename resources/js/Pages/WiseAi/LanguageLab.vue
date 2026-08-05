<template>
    <AuthenticatedLayout title="Wise AI — Language Lab">
        <div class="space-y-5">
            <PageHeader
                title="Language Lab"
                description="Advanced — pack/surface browser + normalize sandbox (promote still on Language)"
            >
                <template #actions>
                    <StatusBadge label="Advanced" variant="neutral" format="none" />
                </template>
            </PageHeader>
            <WiseAiSubNav />

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <PageCard title="Packs">
                    <p class="text-2xl font-semibold">{{ stats.packs }}</p>
                </PageCard>
                <PageCard title="Surfaces">
                    <p class="text-2xl font-semibold">{{ stats.surfaces }}</p>
                </PageCard>
                <PageCard title="Discovery open">
                    <p class="text-2xl font-semibold text-rose-600 dark:text-rose-300">{{ stats.discovery_open }}</p>
                </PageCard>
                <PageCard title="Brain">
                    <p class="font-mono text-sm">{{ brain_version }}</p>
                </PageCard>
            </div>

            <div class="grid gap-5 lg:grid-cols-5">
                <PageCard class="lg:col-span-2" title="Packs" description="Regional packs apply only when context.region is set">
                    <div class="space-y-1">
                        <Link
                            v-for="p in packs"
                            :key="p.slug"
                            :href="route('wiseAi.lab', { pack: p.slug })"
                            class="flex items-center justify-between rounded-xl px-3 py-2 text-sm transition"
                            :class="
                                selected_pack === p.slug
                                    ? 'bg-fuchsia-600 text-white'
                                    : 'hover:bg-gray-50 dark:hover:bg-slate-800'
                            "
                        >
                            <span>
                                <code class="font-mono text-xs">{{ p.slug }}</code>
                                <span class="ml-2 text-[11px] opacity-80">{{ p.kind }} · {{ p.surface_count }}</span>
                            </span>
                            <StatusBadge :label="p.status" variant="neutral" format="none" />
                        </Link>
                    </div>
                    <p class="mt-3 text-[11px] text-gray-500">
                        Promote unknowns from
                        <Link :href="route('wiseAi.language')" class="text-fuchsia-600 underline">Discovery Queue</Link>.
                    </p>
                </PageCard>

                <PageCard class="lg:col-span-3" title="Surfaces" :description="selected_pack || 'Select a pack'">
                    <DataTable
                        v-if="surfaces.length"
                        :value="surfaces"
                        size="small"
                        striped-rows
                        class="professional-table"
                        paginator
                        :rows="12"
                    >
                        <Column field="surface" header="Surface">
                            <template #body="{ data }">
                                <code class="font-mono text-xs">{{ data.surface }}</code>
                            </template>
                        </Column>
                        <Column field="to" header="To" />
                        <Column field="category" header="Cat" />
                        <Column field="evidence" header="Src" />
                    </DataTable>
                    <EmptyState
                        v-else
                        icon="PhTranslate"
                        title="No surfaces"
                        description="Run php artisan wise:bclc-bootstrap if packs are empty"
                    />
                </PageCard>
            </div>

            <PageCard title="Try normalize" description="Same LanguageNormalizer path as decide (admin preview)">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-xs text-gray-500">Raw</label>
                        <input
                            v-model="sample"
                            type="text"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                            placeholder="aitta dam koto"
                            @keyup.enter="runNormalize"
                        />
                    </div>
                    <div class="w-full sm:w-44">
                        <label class="mb-1 block text-xs text-gray-500">Region</label>
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
                    <p v-if="preview.dict_version" class="font-mono text-[11px] text-gray-400">
                        {{ preview.dict_version }}
                        <span v-if="preview.corpus_snapshot?.region"> · region {{ preview.corpus_snapshot.region }}</span>
                    </p>
                    <p v-if="preview.rules_applied?.length">
                        Rules:
                        {{ preview.rules_applied.map((r: any) => `${r.from}→${r.to}`).join(" · ") }}
                    </p>
                </div>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import Select from "primevue/select";
import { AuthenticatedLayout } from "@/layouts";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";

const props = defineProps<{
    brain_version: string;
    packs: Array<{
        id: number;
        slug: string;
        kind: string;
        name: string;
        status: string;
        semver: string;
        region?: string | null;
        surface_count: number;
        artifact_hash?: string | null;
    }>;
    selected_pack: string | null;
    surfaces: Array<{
        id: number;
        surface: string;
        to: string | null;
        category?: string | null;
        evidence?: string | null;
        approval?: string | null;
    }>;
    stats: { packs: number; surfaces: number; discovery_open: number };
}>();

const sample = ref("aitta dam koto");
const sampleRegion = ref<string | null>(null);
const running = ref(false);
const preview = ref<any>(null);

const regionOptions = computed(() => {
    const fromPacks = props.packs
        .filter((p) => p.kind === "region" && p.region)
        .map((p) => ({ label: p.name, value: String(p.region) }));
    return fromPacks.length
        ? fromPacks
        : [
              { label: "Chattogram", value: "chattogram" },
              { label: "Sylhet", value: "sylhet" },
              { label: "Noakhali", value: "noakhali" },
              { label: "Barisal", value: "barisal" },
              { label: "Rajshahi", value: "rajshahi" },
              { label: "Khulna", value: "khulna" },
              { label: "Rangpur", value: "rangpur" },
              { label: "Mymensingh", value: "mymensingh" },
          ];
});

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
</script>
