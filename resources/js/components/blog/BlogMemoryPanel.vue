<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Standing memory
                </p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Keywords, topics, and instructions the AI remembers every day — manual + auto from learning.
                    <span v-if="stats.active != null"> · {{ stats.active }} active</span>
                </p>
            </div>
            <Button
                label="Absorb learning"
                icon="pi pi-download"
                size="small"
                severity="secondary"
                outlined
                :loading="absorbing"
                :disabled="busy"
                @click="absorb"
            />
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="space-y-1.5">
                <label class="text-xs font-medium text-slate-700 dark:text-slate-200">Type</label>
                <Select
                    v-model="form.type"
                    :options="typeOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                    :disabled="busy"
                />
            </div>
            <div class="space-y-1.5">
                <label class="text-xs font-medium text-slate-700 dark:text-slate-200">Cluster (optional)</label>
                <Select
                    v-model="form.cluster"
                    :options="clusterOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Any"
                    class="w-full"
                    showClear
                    :disabled="busy"
                />
            </div>
            <div class="space-y-1.5 sm:col-span-2">
                <label class="text-xs font-medium text-slate-700 dark:text-slate-200">Memory</label>
                <Textarea
                    v-model="form.content"
                    rows="2"
                    class="w-full text-sm"
                    placeholder="e.g. Prefer “ফ্রড চেকার” as focus · Avoid US ecom jargon · Always link /bd-fraud-checker"
                    :disabled="busy"
                />
            </div>
        </div>

        <Button
            label="Save memory"
            icon="pi pi-bookmark"
            size="small"
            :loading="saving"
            :disabled="busy || !canSave"
            @click="save"
        />

        <ul
            v-if="items.length"
            class="max-h-64 space-y-2 overflow-auto"
        >
            <li
                v-for="item in items"
                :key="item.id"
                class="rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-600"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                {{ item.type }}
                            </span>
                            <span class="text-[10px] text-slate-500 dark:text-slate-400">
                                {{ item.source }} · hits {{ item.hits }}
                            </span>
                            <span
                                v-if="!item.is_active"
                                class="text-[10px] font-semibold text-rose-600 dark:text-rose-300"
                            >
                                inactive
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                            {{ item.content }}
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <Button
                            :icon="item.is_active ? 'pi pi-eye-slash' : 'pi pi-eye'"
                            size="small"
                            text
                            rounded
                            :disabled="busy"
                            @click="toggleActive(item)"
                        />
                        <Button
                            icon="pi pi-trash"
                            size="small"
                            text
                            rounded
                            severity="danger"
                            :disabled="busy"
                            @click="remove(item)"
                        />
                    </div>
                </div>
            </li>
        </ul>
        <p
            v-else-if="!loading"
            class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-slate-600 dark:text-slate-400"
        >
            No memories yet. Save instructions above, or click Absorb learning after GSC sync.
        </p>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    initialItems: { type: Array, default: () => [] },
    initialStats: { type: Object, default: () => ({}) },
    clusters: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['updated', 'intelligence']);

const toast = useToast();
const items = ref([...(props.initialItems || [])]);
const stats = ref({ ...(props.initialStats || {}) });
const loading = ref(false);
const saving = ref(false);
const absorbing = ref(false);
const form = ref({
    type: 'instruction',
    content: '',
    cluster: null,
});

const busy = computed(() => loading.value || saving.value || absorbing.value);
const canSave = computed(() => form.value.content.trim().length > 0);

const typeOptions = [
    { value: 'instruction', label: 'Instruction' },
    { value: 'keyword_prefer', label: 'Prefer keyword' },
    { value: 'keyword_avoid', label: 'Avoid keyword' },
    { value: 'topic', label: 'Topic' },
    { value: 'brand_note', label: 'Brand note' },
    { value: 'lesson', label: 'Lesson' },
];

const clusterOptions = computed(() =>
    Object.entries(props.clusters || {}).map(([value, label]) => ({
        value,
        label: `${label} (${value})`,
    })),
);

watch(
    () => props.initialItems,
    (next) => {
        if (Array.isArray(next)) items.value = [...next];
    },
);

watch(
    () => props.initialStats,
    (next) => {
        if (next) stats.value = { ...next };
    },
);

const applyPayload = (data) => {
    if (Array.isArray(data?.items)) items.value = data.items;
    if (data?.stats) stats.value = data.stats;
    if (data?.intelligence) emit('intelligence', data.intelligence);
};

const load = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('blogAi.memories.index'));
        applyPayload(data);
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Memory',
            detail: error?.response?.data?.message || 'Could not load memories.',
            life: 4000,
            group: 'br',
        });
    } finally {
        loading.value = false;
    }
};

const save = async () => {
    if (!canSave.value) return;
    saving.value = true;
    try {
        const { data } = await axios.post(route('blogAi.memories.store'), {
            type: form.value.type,
            content: form.value.content.trim(),
            cluster: form.value.cluster || null,
            priority: 80,
        });
        applyPayload(data);
        form.value.content = '';
        emit('updated', data);
        toast.add({
            severity: 'success',
            summary: 'Memory saved',
            detail: 'AI will use this on future drafts.',
            life: 3500,
            group: 'br',
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Memory',
            detail: error?.response?.data?.errors?.content?.[0]
                || error?.response?.data?.message
                || 'Save failed.',
            life: 5000,
            group: 'br',
        });
    } finally {
        saving.value = false;
    }
};

const toggleActive = async (item) => {
    try {
        const { data } = await axios.put(route('blogAi.memories.update', item.id), {
            is_active: !item.is_active,
        });
        applyPayload(data);
        emit('updated', data);
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Memory',
            detail: error?.response?.data?.message || 'Update failed.',
            life: 4000,
            group: 'br',
        });
    }
};

const remove = async (item) => {
    if (!window.confirm(`Delete memory “${item.content}”?`)) return;
    try {
        const { data } = await axios.delete(route('blogAi.memories.destroy', item.id));
        applyPayload(data);
        emit('updated', data);
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Memory',
            detail: error?.response?.data?.message || 'Delete failed.',
            life: 4000,
            group: 'br',
        });
    }
};

const absorb = async () => {
    absorbing.value = true;
    try {
        const { data } = await axios.post(route('blogAi.memories.absorb'));
        applyPayload(data);
        emit('updated', data);
        toast.add({
            severity: 'success',
            summary: 'Learning absorbed',
            detail: `+${data?.absorbed?.created || 0} new, ${data?.absorbed?.reinforced || 0} reinforced`,
            life: 4500,
            group: 'br',
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Absorb failed',
            detail: error?.response?.data?.message || 'Run learning insights first.',
            life: 5000,
            group: 'br',
        });
    } finally {
        absorbing.value = false;
    }
};

onMounted(() => {
    if (!items.value.length) load();
});
</script>
