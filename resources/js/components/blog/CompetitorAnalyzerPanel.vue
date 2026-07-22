<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Competitor analyzer
                </p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Find rival articles for a keyword (or paste URLs). AI scores gaps vs your posts and remembers open angles for Smart Post.
                </p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="space-y-1.5 sm:col-span-1">
                <label class="text-xs font-medium text-slate-700 dark:text-slate-200" for="comp-keyword">
                    Target keyword
                </label>
                <InputText
                    id="comp-keyword"
                    v-model="keyword"
                    class="w-full"
                    placeholder="e.g. ফেক অর্ডার কমাতে"
                    :disabled="busy"
                />
            </div>
            <div class="space-y-1.5 sm:col-span-1">
                <label class="text-xs font-medium text-slate-700 dark:text-slate-200" for="comp-cluster">
                    Cluster (optional)
                </label>
                <Select
                    id="comp-cluster"
                    v-model="cluster"
                    :options="clusterOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Auto / general"
                    class="w-full"
                    showClear
                    :disabled="busy"
                />
            </div>
            <div class="space-y-1.5 sm:col-span-2">
                <label class="text-xs font-medium text-slate-700 dark:text-slate-200" for="comp-urls">
                    Competitor URLs (up to {{ maxUrls }}, one per line — optional if you Find rivals)
                </label>
                <Textarea
                    id="comp-urls"
                    v-model="urlsText"
                    rows="4"
                    class="w-full font-mono text-xs"
                    placeholder="https://competitor.com/blog/fake-order-guide&#10;https://another.com/cod-fraud-tips"
                    :disabled="busy"
                />
            </div>
        </div>

        <div
            v-if="discovered.length"
            class="rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-600"
        >
            <p class="text-xs font-medium text-slate-700 dark:text-slate-200">
                Discovered rivals — uncheck any to skip
            </p>
            <ul class="mt-2 max-h-40 space-y-1.5 overflow-auto">
                <li
                    v-for="row in discovered"
                    :key="row.url"
                    class="flex items-start gap-2 text-xs"
                >
                    <input
                        :id="'disc-'+row.rank"
                        v-model="selectedUrls"
                        type="checkbox"
                        class="mt-0.5"
                        :value="row.url"
                        :disabled="busy"
                    >
                    <label
                        :for="'disc-'+row.rank"
                        class="min-w-0 flex-1 cursor-pointer"
                    >
                        <span class="font-medium text-slate-800 dark:text-slate-100">
                            {{ row.title || row.url }}
                        </span>
                        <span class="mt-0.5 block truncate text-[11px] text-slate-500 dark:text-slate-400">
                            {{ row.url }}
                        </span>
                    </label>
                </li>
            </ul>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                v-if="discoveryEnabled"
                label="Find rivals"
                icon="pi pi-globe"
                size="small"
                severity="secondary"
                outlined
                :loading="discovering"
                :disabled="busy || !keyword.trim()"
                @click="runDiscover"
            />
            <Button
                label="Analyze competitors"
                icon="pi pi-search"
                size="small"
                :loading="analyzing"
                :disabled="busy || !canSubmit"
                @click="runAnalyze"
            />
            <Button
                label="Refresh"
                icon="pi pi-refresh"
                size="small"
                severity="secondary"
                outlined
                :loading="loading"
                :disabled="busy"
                @click="load"
            />
        </div>

        <div
            v-if="latest"
            class="rounded-xl border border-sky-200/80 bg-sky-50/70 px-3 py-3 text-sm dark:border-sky-500/30 dark:bg-sky-500/10"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="font-semibold text-sky-950 dark:text-sky-100">
                        {{ latest.keyword }}
                    </p>
                    <p class="mt-1 text-xs text-sky-900/80 dark:text-sky-200/90">
                        {{ latest.summary_bn || 'Analysis ready.' }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-if="latest.beat_score != null"
                        class="shrink-0 rounded-full bg-sky-600/15 px-2.5 py-0.5 text-[11px] font-semibold text-sky-800 dark:text-sky-200"
                    >
                        Beat score {{ latest.beat_score }}
                    </span>
                    <span
                        v-if="latest.open_gaps != null"
                        class="shrink-0 rounded-full bg-amber-500/15 px-2.5 py-0.5 text-[11px] font-semibold text-amber-900 dark:text-amber-100"
                    >
                        {{ latest.open_gaps }} open gaps
                    </span>
                </div>
            </div>

            <ul
                v-if="(latest.gap_checklist || []).length"
                class="mt-3 space-y-1.5"
            >
                <li
                    v-for="(gap, idx) in latest.gap_checklist.slice(0, 8)"
                    :key="gap.id || idx"
                    class="flex items-start gap-2 text-xs text-sky-950/90 dark:text-sky-100/90"
                >
                    <span
                        class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                        :class="gapStatusClass(gap.status)"
                    >
                        {{ gap.status || 'open' }}
                    </span>
                    <span>{{ gap.gap }}</span>
                </li>
            </ul>
            <ul
                v-else-if="(latest.content_gaps || []).length"
                class="mt-2 list-disc space-y-0.5 pl-5 text-xs text-sky-950/90 dark:text-sky-100/90"
            >
                <li
                    v-for="(gap, idx) in latest.content_gaps.slice(0, 4)"
                    :key="idx"
                >
                    {{ gap }}
                </li>
            </ul>
        </div>

        <ul
            v-if="items.length"
            class="max-h-56 space-y-2 overflow-auto"
        >
            <li
                v-for="item in items"
                :key="item.id"
                class="rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-600"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                        {{ item.keyword }}
                    </p>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ formatDate(item.created_at) }}
                        <template v-if="item.open_gaps != null">
                            · {{ item.open_gaps }} open
                        </template>
                    </span>
                </div>
                <p
                    v-if="item.summary_bn"
                    class="mt-1 line-clamp-2 text-xs text-slate-600 dark:text-slate-300"
                >
                    {{ item.summary_bn }}
                </p>
            </li>
        </ul>
        <p
            v-else-if="!loading"
            class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-slate-600 dark:text-slate-400"
        >
            No competitor analyses yet. Find rivals or paste URLs above to start.
        </p>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    initialItems: { type: Array, default: () => [] },
    clusters: { type: Object, default: () => ({}) },
    discoveryEnabled: { type: Boolean, default: true },
});

const emit = defineEmits(['updated', 'intelligence']);

const toast = useToast();
const keyword = ref('');
const cluster = ref(null);
const urlsText = ref('');
const items = ref([...(props.initialItems || [])]);
const discovered = ref([]);
const selectedUrls = ref([]);
const loading = ref(false);
const analyzing = ref(false);
const discovering = ref(false);
const discoveryEnabled = ref(props.discoveryEnabled !== false);

const busy = computed(() => loading.value || analyzing.value || discovering.value);
const maxUrls = 5;
const canSubmit = computed(() => {
    const kw = keyword.value.trim();
    if (!kw) return false;
    const pasted = urlsText.value.trim();
    const selected = selectedUrls.value.length > 0;
    // Allow analyze with pasted URLs, selected discoveries, or discovery enabled (auto-fill on server).
    return pasted.length > 0 || selected || discoveryEnabled.value;
});

const latest = computed(() => items.value[0] || null);

const clusterOptions = computed(() =>
    Object.entries(props.clusters || {}).map(([value, label]) => ({
        value,
        label: `${label} (${value})`,
    })),
);

watch(
    () => props.initialItems,
    (next) => {
        if (Array.isArray(next) && next.length) {
            items.value = [...next];
        }
    },
);

watch(selectedUrls, (next) => {
    if (Array.isArray(next) && next.length) {
        urlsText.value = next.join('\n');
    }
});

const gapStatusClass = (status) => {
    const s = String(status || 'open').toLowerCase();
    if (s === 'covered') {
        return 'bg-emerald-500/20 text-emerald-800 dark:text-emerald-100';
    }
    if (s === 'partial') {
        return 'bg-amber-500/20 text-amber-900 dark:text-amber-100';
    }
    return 'bg-rose-500/15 text-rose-800 dark:text-rose-100';
};

const formatDate = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const load = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('blogAi.competitors.index'));
        items.value = data?.items || [];
        if (typeof data?.discovery_enabled === 'boolean') {
            discoveryEnabled.value = data.discovery_enabled;
        }
        if (data?.intelligence) {
            emit('intelligence', data.intelligence);
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Competitors',
            detail: error?.response?.data?.message || 'Could not load analyses.',
            life: 4000,
            group: 'br',
        });
    } finally {
        loading.value = false;
    }
};

const runDiscover = async () => {
    const kw = keyword.value.trim();
    if (!kw) return;
    discovering.value = true;
    try {
        const { data } = await axios.post(
            route('blogAi.competitors.discover'),
            { keyword: kw },
            { timeout: 60000 },
        );
        discovered.value = data?.results || [];
        selectedUrls.value = discovered.value.map((r) => r.url).slice(0, maxUrls);
        if (data?.urls_text) {
            urlsText.value = data.urls_text;
        }
        if (!discovered.value.length) {
            toast.add({
                severity: 'warn',
                summary: 'Find rivals',
                detail: 'No public results found. Paste competitor URLs manually.',
                life: 5000,
                group: 'br',
            });
        } else {
            toast.add({
                severity: 'success',
                summary: 'Find rivals',
                detail: `Found ${discovered.value.length} pages — review then Analyze.`,
                life: 4000,
                group: 'br',
            });
        }
    } catch (error) {
        const payload = error?.response?.data;
        toast.add({
            severity: 'error',
            summary: 'Find rivals',
            detail: payload?.message || payload?.errors?.keyword?.[0] || payload?.errors?.ai?.[0] || 'Discovery failed.',
            life: 6000,
            group: 'br',
        });
    } finally {
        discovering.value = false;
    }
};

const runAnalyze = async () => {
    if (!canSubmit.value) return;
    analyzing.value = true;
    try {
        const urls = (selectedUrls.value.length ? selectedUrls.value.join('\n') : urlsText.value).trim();
        const { data } = await axios.post(
            route('blogAi.competitors.analyze'),
            {
                keyword: keyword.value.trim(),
                cluster: cluster.value || null,
                urls_text: urls,
                allow_discover: !urls,
            },
            { timeout: 180000 },
        );
        items.value = data?.items || (data?.item ? [data.item, ...items.value] : items.value);
        if (data?.intelligence) {
            emit('intelligence', data.intelligence);
        }
        emit('updated', data);
        toast.add({
            severity: 'success',
            summary: 'Competitor analysis',
            detail: 'Gaps saved — AI drafts will use open checklist items on matching keywords.',
            life: 5000,
            group: 'br',
        });
    } catch (error) {
        const payload = error?.response?.data;
        const msg =
            payload?.message
            || payload?.errors?.urls?.[0]
            || payload?.errors?.keyword?.[0]
            || payload?.errors?.ai?.[0]
            || 'Analysis failed.';
        toast.add({
            severity: 'error',
            summary: 'Competitor analysis',
            detail: msg,
            life: 6000,
            group: 'br',
        });
    } finally {
        analyzing.value = false;
    }
};

onMounted(() => {
    if (!items.value.length) {
        load();
    }
});
</script>
