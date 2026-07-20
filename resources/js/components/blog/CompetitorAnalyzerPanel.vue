<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Competitor analyzer
                </p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Paste rival article URLs for a target keyword. AI finds gaps your next post should cover.
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
                    Competitor URLs (1–{{ maxUrls }}, one per line)
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

        <div class="flex flex-wrap items-center gap-2">
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
                <span
                    v-if="latest.beat_score != null"
                    class="shrink-0 rounded-full bg-sky-600/15 px-2.5 py-0.5 text-[11px] font-semibold text-sky-800 dark:text-sky-200"
                >
                    Beat score {{ latest.beat_score }}
                </span>
            </div>
            <ul
                v-if="(latest.content_gaps || []).length"
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
            No competitor analyses yet. Paste URLs above to start.
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
});

const emit = defineEmits(['updated', 'intelligence']);

const toast = useToast();
const keyword = ref('');
const cluster = ref(null);
const urlsText = ref('');
const items = ref([...(props.initialItems || [])]);
const loading = ref(false);
const analyzing = ref(false);

const busy = computed(() => loading.value || analyzing.value);
const maxUrls = 5;
const canSubmit = computed(() => {
    const kw = keyword.value.trim();
    const urls = urlsText.value.trim();
    return kw.length > 0 && urls.length > 0;
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

const runAnalyze = async () => {
    if (!canSubmit.value) return;
    analyzing.value = true;
    try {
        const { data } = await axios.post(
            route('blogAi.competitors.analyze'),
            {
                keyword: keyword.value.trim(),
                cluster: cluster.value || null,
                urls_text: urlsText.value.trim(),
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
            detail: 'Gaps saved — AI drafts will use this on matching keywords.',
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
