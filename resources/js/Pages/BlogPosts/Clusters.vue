<template>
    <AuthenticatedLayout title="Topic Clusters">
        <div class="space-y-5">
            <PageHeader
                title="Topic Clusters"
                description="Organize Smart Post seeds into clusters. Add topics here — AI picks from these when writing."
                icon="PhTreeStructure"
                icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                icon-class="text-emerald-600 dark:text-emerald-400"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            label="Blog AI"
                            icon="pi pi-sparkles"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('blogPosts.ai'))"
                        />
                        <Button
                            label="Add cluster"
                            icon="pi pi-plus"
                            size="small"
                            @click="openCreate"
                        />
                    </div>
                </template>
            </PageHeader>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard title="Clusters" :value="clusters.length" icon="PhFolders" />
                <StatCard
                    title="Active"
                    :value="activeCount"
                    icon="PhCheckCircle"
                    accent-class="bg-emerald-500"
                    icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                    icon-class="text-emerald-600 dark:text-emerald-400"
                />
                <StatCard
                    title="Seed topics"
                    :value="totalSeeds"
                    icon="PhListBullets"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
                <StatCard
                    title="Selected"
                    :value="selected?.seed_count || 0"
                    icon="PhTarget"
                    subtitle="Topics in active cluster"
                    accent-class="bg-amber-500"
                    icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                    icon-class="text-amber-600 dark:text-amber-400"
                />
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <PageCard title="Clusters" :description="`${clusters.length} keys`" no-padding>
                    <ul class="max-h-[32rem] divide-y divide-gray-100 overflow-auto dark:divide-gray-800">
                        <li v-for="cluster in clusters" :key="cluster.key">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm transition"
                                :class="
                                    selectedId === cluster.id
                                        ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800/60'
                                "
                                @click="selectedId = cluster.id"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ cluster.label }}</span>
                                    <span class="block truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                                        {{ cluster.key }}
                                    </span>
                                </span>
                                <Tag
                                    :value="String(cluster.seed_count)"
                                    :severity="cluster.is_active ? 'success' : 'secondary'"
                                />
                            </button>
                        </li>
                    </ul>
                </PageCard>

                <PageCard
                    v-if="selected"
                    :title="selected.label"
                    :description="`Key: ${selected.key}`"
                >
                    <template #actions>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                label="Edit"
                                icon="pi pi-pencil"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="openEdit(selected)"
                            />
                            <Button
                                v-if="selected.key !== 'general'"
                                label="Delete"
                                icon="pi pi-trash"
                                size="small"
                                severity="danger"
                                outlined
                                @click="confirmDelete(selected)"
                            />
                        </div>
                    </template>

                    <div class="space-y-3">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Sort {{ selected.sort_order }}
                            · {{ selected.is_active ? 'Active' : 'Inactive' }}
                            · {{ selected.seed_count }} seed topics
                        </p>
                        <div v-if="selected.landing?.primary_path || selected.landing?.angle_hint" class="rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-slate-800/60">
                            <p v-if="selected.landing.primary_path">
                                <span class="font-medium">Landing:</span>
                                <code class="ml-1">{{ selected.landing.primary_path }}</code>
                            </p>
                            <p v-if="selected.landing.angle_hint" class="mt-1 text-slate-600 dark:text-slate-300">
                                {{ selected.landing.angle_hint }}
                            </p>
                        </div>
                        <ul
                            v-if="selected.seed_queries?.length"
                            class="flex flex-wrap gap-2"
                        >
                            <li
                                v-for="(seed, idx) in selected.seed_queries"
                                :key="idx"
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700 dark:bg-slate-700 dark:text-slate-100"
                            >
                                {{ seed }}
                            </li>
                        </ul>
                        <p v-else class="text-sm text-slate-500">
                            No seed topics yet. Edit this cluster and paste one topic per line.
                        </p>
                        <div v-if="selected.detect_needles?.length" class="pt-1">
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Detect needles</p>
                            <ul class="flex flex-wrap gap-1.5">
                                <li
                                    v-for="(needle, idx) in selected.detect_needles"
                                    :key="'n'+idx"
                                    class="rounded bg-emerald-500/10 px-2 py-0.5 text-[11px] text-emerald-800 dark:text-emerald-200"
                                >
                                    {{ needle }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </PageCard>
            </div>
        </div>

        <Dialog
            v-model:visible="dialogVisible"
            modal
            :header="editing ? 'Edit cluster' : 'Add cluster'"
            :style="{ width: '40rem' }"
            :breakpoints="{ '640px': '95vw' }"
        >
            <div class="max-h-[70vh] space-y-3 overflow-auto pr-1">
                <div v-if="!editing" class="space-y-1.5">
                    <label class="text-xs font-medium">Key (slug)</label>
                    <InputText
                        v-model="form.key"
                        class="w-full font-mono text-sm"
                        placeholder="e.g. fraud_checker"
                    />
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">Label</label>
                    <InputText
                        v-model="form.label"
                        class="w-full"
                        placeholder="Display name"
                    />
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">Seed topics (one per line)</label>
                    <Textarea
                        v-model="form.seed_queries_text"
                        rows="6"
                        class="w-full text-sm"
                        autoResize
                        placeholder="Stop Fake Orders&#10;OTP Verification"
                    />
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">Detect needles (auto-cluster keywords)</label>
                    <Textarea
                        v-model="form.detect_needles_text"
                        rows="4"
                        class="w-full text-sm"
                        autoResize
                        placeholder="fraud checker&#10;ফ্রড চেকার"
                    />
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">Primary landing path</label>
                    <InputText
                        v-model="form.primary_path"
                        class="w-full font-mono text-sm"
                        placeholder="/bd-fraud-checker"
                    />
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">Related paths (one per line)</label>
                    <Textarea v-model="form.related_paths_text" rows="3" class="w-full font-mono text-xs" autoResize />
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">Must-link paths (one per line)</label>
                    <Textarea v-model="form.must_link_paths_text" rows="2" class="w-full font-mono text-xs" autoResize />
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">Claims (one per line)</label>
                    <Textarea v-model="form.claims_text" rows="3" class="w-full text-sm" autoResize />
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">Angle hint</label>
                    <InputText v-model="form.angle_hint" class="w-full text-sm" />
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-medium">SEO page keys (config/seo.php, one per line)</label>
                    <Textarea v-model="form.seo_pages_text" rows="2" class="w-full font-mono text-xs" autoResize />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium">Sort order</label>
                        <InputNumber v-model="form.sort_order" class="w-full" :min="0" :max="9999" />
                    </div>
                    <div class="flex items-end gap-2 pb-1">
                        <Checkbox v-model="form.is_active" :binary="true" inputId="cluster-active" />
                        <label for="cluster-active" class="text-sm">Active</label>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" text @click="dialogVisible = false" />
                <Button
                    :label="editing ? 'Save' : 'Create'"
                    :loading="saving"
                    @click="save"
                />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Pages/Users/fragments/PageHeader.vue';
import PageCard from '@/Pages/Users/fragments/PageCard.vue';
import StatCard from '@/Pages/Users/fragments/StatCard.vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';

const props = defineProps({
    clusters: { type: Array, default: () => [] },
});

const selectedId = ref(props.clusters[0]?.id ?? null);
const dialogVisible = ref(false);
const editing = ref(null);
const saving = ref(false);
const emptyForm = () => ({
    key: '',
    label: '',
    seed_queries_text: '',
    detect_needles_text: '',
    primary_path: '',
    related_paths_text: '',
    must_link_paths_text: '',
    claims_text: '',
    angle_hint: '',
    seo_pages_text: '',
    sort_order: 100,
    is_active: true,
});

const form = ref(emptyForm());

watch(
    () => props.clusters,
    (next) => {
        if (!next?.length) {
            selectedId.value = null;
            return;
        }
        if (!next.some((c) => c.id === selectedId.value)) {
            selectedId.value = next[0].id;
        }
    },
);

const selected = computed(() => props.clusters.find((c) => c.id === selectedId.value) || null);
const activeCount = computed(() => props.clusters.filter((c) => c.is_active).length);
const totalSeeds = computed(() => props.clusters.reduce((sum, c) => sum + (c.seed_count || 0), 0));

const openCreate = () => {
    editing.value = null;
    form.value = emptyForm();
    dialogVisible.value = true;
};

const openEdit = (cluster) => {
    editing.value = cluster;
    form.value = {
        key: cluster.key,
        label: cluster.label,
        seed_queries_text: (cluster.seed_queries || []).join('\n'),
        detect_needles_text: cluster.detect_needles_text || (cluster.detect_needles || []).join('\n'),
        primary_path: cluster.landing?.primary_path || '',
        related_paths_text: cluster.landing?.related_paths_text || '',
        must_link_paths_text: cluster.landing?.must_link_paths_text || '',
        claims_text: cluster.landing?.claims_text || '',
        angle_hint: cluster.landing?.angle_hint || '',
        seo_pages_text: cluster.landing?.seo_pages_text || '',
        sort_order: cluster.sort_order ?? 100,
        is_active: cluster.is_active !== false,
    };
    dialogVisible.value = true;
};

const save = () => {
    saving.value = true;
    const payload = {
        label: form.value.label,
        seed_queries_text: form.value.seed_queries_text,
        detect_needles_text: form.value.detect_needles_text,
        primary_path: form.value.primary_path,
        related_paths_text: form.value.related_paths_text,
        must_link_paths_text: form.value.must_link_paths_text,
        claims_text: form.value.claims_text,
        angle_hint: form.value.angle_hint,
        seo_pages_text: form.value.seo_pages_text,
        sort_order: form.value.sort_order,
        is_active: form.value.is_active,
    };

    if (editing.value) {
        router.put(route('blogPosts.clusters.update', editing.value.id), payload, {
            preserveScroll: true,
            onSuccess: () => {
                dialogVisible.value = false;
            },
            onFinish: () => {
                saving.value = false;
            },
        });
        return;
    }

    router.post(
        route('blogPosts.clusters.store'),
        { ...payload, key: form.value.key },
        {
            preserveScroll: true,
            onSuccess: () => {
                dialogVisible.value = false;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
};

const confirmDelete = (cluster) => {
    if (!window.confirm(`Delete cluster “${cluster.label}” (${cluster.key})?`)) return;
    router.delete(route('blogPosts.clusters.destroy', cluster.id), { preserveScroll: true });
};
</script>
