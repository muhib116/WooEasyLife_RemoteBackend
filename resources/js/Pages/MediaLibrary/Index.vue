<template>
    <AuthenticatedLayout title="Media Library">
        <div class="space-y-5">
            <PageHeader
                title="Media Library"
                description="Upload, crop, and store optimized WebP images. Copy the URL anywhere (blog, OG image, pages)."
                icon="PhImages"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <Button
                        label="Upload image"
                        icon="pi pi-upload"
                        size="small"
                        @click="showUpload = true"
                    />
                </template>
            </PageHeader>

            <PageCard title="Library" :description="`${total} file${total === 1 ? '' : 's'}`">
                <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="search">
                    <InputText
                        v-model="query"
                        class="min-w-[14rem] flex-1"
                        placeholder="Search by title, filename, alt…"
                    />
                    <Button type="submit" label="Search" icon="pi pi-search" outlined />
                </form>

                <div v-if="!rows.length" class="rounded-xl border border-dashed border-slate-300 p-10 text-center dark:border-slate-600">
                    <p class="text-sm text-slate-600 dark:text-slate-300">No media yet.</p>
                    <Button class="mt-3" label="Upload first image" icon="pi pi-upload" @click="showUpload = true" />
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <article
                        v-for="item in rows"
                        :key="item.id"
                        class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600"
                    >
                        <div class="relative aspect-square overflow-hidden bg-slate-100 dark:bg-slate-800">
                            <img
                                :src="item.url"
                                :alt="item.alt || item.title"
                                class="h-full w-full object-cover"
                            >
                            <a
                                :href="item.url"
                                target="_blank"
                                rel="noopener"
                                class="absolute right-2.5 top-2.5 inline-flex h-8 w-8 items-center justify-center rounded-lg bg-black/50 text-white opacity-0 backdrop-blur-sm transition group-hover:opacity-100 hover:bg-black/70 focus:opacity-100"
                                aria-label="Open image"
                                v-tooltip.top="'Open in new tab'"
                                @click.stop
                            >
                                <i class="pi pi-external-link text-[11px]" />
                            </a>
                        </div>

                        <!-- Compact bar: always visible, toggles full details -->
                        <div class="flex h-11 items-center gap-1 border-t border-slate-200 px-1.5 dark:border-slate-700">
                            <button
                                type="button"
                                class="flex h-9 min-w-0 flex-1 items-center gap-2 rounded-lg px-2 text-left transition hover:bg-slate-100 dark:hover:bg-slate-800"
                                :aria-expanded="isDetailsOpen(item.id)"
                                :aria-controls="`media-details-${item.id}`"
                                @click="toggleDetails(item.id)"
                            >
                                <i
                                    class="pi shrink-0 text-[10px] text-slate-400"
                                    :class="isDetailsOpen(item.id) ? 'pi-chevron-down' : 'pi-chevron-right'"
                                />
                                <span class="min-w-0 flex-1 truncate text-[13px] font-medium leading-none text-slate-800 dark:text-slate-100">
                                    {{ drafts[item.id]?.title || item.title || item.filename }}
                                </span>
                                <span
                                    v-if="isDirty(item)"
                                    class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"
                                    title="Unsaved changes"
                                />
                                <span class="hidden shrink-0 text-[10px] tabular-nums text-slate-400 sm:inline">
                                    {{ item.human_size }}
                                </span>
                            </button>
                            <Button
                                :icon="copiedId === item.id ? 'pi pi-check' : 'pi pi-link'"
                                size="small"
                                text
                                rounded
                                class="!h-8 !w-8 shrink-0"
                                :severity="copiedId === item.id ? 'success' : 'secondary'"
                                :aria-label="copiedId === item.id ? 'Copied' : 'Copy link'"
                                v-tooltip.top="copiedId === item.id ? 'Copied' : 'Copy link'"
                                @click="copyUrl(item)"
                            />
                        </div>

                        <!-- Full details panel -->
                        <div
                            v-show="isDetailsOpen(item.id)"
                            :id="`media-details-${item.id}`"
                            class="border-t border-slate-200 dark:border-slate-700"
                        >
                            <div class="space-y-3 px-3 py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] tabular-nums text-slate-500 dark:text-slate-400">
                                        {{ item.width }}×{{ item.height }}
                                        <span class="mx-1.5 text-slate-300 dark:text-slate-600">·</span>
                                        {{ item.human_size }}
                                    </p>
                                    <span class="rounded-md bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                                        WebP
                                    </span>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-[11px] font-medium text-slate-500 dark:text-slate-400"
                                        :for="`media-title-${item.id}`"
                                    >
                                        Title
                                    </label>
                                    <InputText
                                        v-if="drafts[item.id]"
                                        :id="`media-title-${item.id}`"
                                        v-model="drafts[item.id].title"
                                        class="w-full !text-sm"
                                        placeholder="Descriptive title"
                                    />
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-[11px] font-medium text-slate-500 dark:text-slate-400"
                                        :for="`media-alt-${item.id}`"
                                    >
                                        Alt text
                                    </label>
                                    <InputText
                                        v-if="drafts[item.id]"
                                        :id="`media-alt-${item.id}`"
                                        v-model="drafts[item.id].alt"
                                        class="w-full !text-sm"
                                        placeholder="Describe the image"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 border-t border-slate-200 px-3 py-3 dark:border-slate-700">
                                <Button
                                    :label="isDirty(item) ? 'Save' : 'Saved'"
                                    :icon="isDirty(item) ? 'pi pi-check' : 'pi pi-check-circle'"
                                    size="small"
                                    class="!h-9 !justify-center"
                                    :outlined="!isDirty(item)"
                                    :severity="isDirty(item) ? undefined : 'secondary'"
                                    :disabled="!isDirty(item) || savingId === item.id"
                                    :loading="savingId === item.id"
                                    @click="saveMeta(item)"
                                />
                                <Button
                                    label="Delete"
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="danger"
                                    outlined
                                    class="!h-9 !justify-center"
                                    :loading="deletingId === item.id"
                                    @click="destroy(item)"
                                />
                            </div>
                        </div>
                    </article>
                </div>

                <div v-if="items?.links?.length > 3" class="mt-5 flex flex-wrap gap-2">
                    <Link
                        v-for="link in items.links"
                        :key="link.label + link.url"
                        :href="link.url || '#'"
                        class="rounded-lg px-3 py-1.5 text-sm"
                        :class="link.active
                            ? 'bg-amber-500 text-black'
                            : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'"
                        v-html="link.label"
                        :preserve-scroll="true"
                    />
                </div>
            </PageCard>
        </div>

        <MediaUploadDialog v-model:visible="showUpload" @uploaded="onUploaded" />
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Pages/Users/fragments/PageHeader.vue';
import PageCard from '@/Pages/Users/fragments/PageCard.vue';
import MediaUploadDialog from '@/components/media/MediaUploadDialog.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    items: { type: Object, required: true },
    filters: { type: Object, default: () => ({ q: '' }) },
});

const toast = useToast();
const showUpload = ref(false);
const query = ref(props.filters.q || '');
const savingId = ref(null);
const deletingId = ref(null);
const copiedId = ref(null);
const openDetailIds = ref(new Set());
let copiedTimer = null;

const rows = computed(() => props.items?.data || []);
const total = computed(() => props.items?.total || rows.value.length);

const drafts = reactive({});

const syncDrafts = () => {
    rows.value.forEach((item) => {
        drafts[item.id] = {
            title: item.title || '',
            alt: item.alt || '',
        };
    });
};

watch(rows, syncDrafts, { immediate: true });

const isDirty = (item) => {
    const draft = drafts[item.id];
    if (!draft) {
        return false;
    }
    return (draft.title || '') !== (item.title || '')
        || (draft.alt || '') !== (item.alt || '');
};

const isDetailsOpen = (id) => openDetailIds.value.has(id);

const toggleDetails = (id) => {
    const next = new Set(openDetailIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    openDetailIds.value = next;
};

const search = () => {
    router.get(route('mediaLibrary.index'), { q: query.value || undefined }, {
        preserveState: true,
        replace: true,
    });
};

const copyUrl = async (item) => {
    try {
        await navigator.clipboard.writeText(item.url);
        copiedId.value = item.id;
        if (copiedTimer) {
            clearTimeout(copiedTimer);
        }
        copiedTimer = setTimeout(() => {
            if (copiedId.value === item.id) {
                copiedId.value = null;
            }
        }, 1800);
        toast.add({ severity: 'success', summary: 'Copied', detail: 'Media URL copied.', life: 2500 });
    } catch {
        toast.add({ severity: 'error', summary: 'Copy failed', detail: item.url, life: 4000 });
    }
};

const saveMeta = async (item) => {
    savingId.value = item.id;
    try {
        await axios.put(route('mediaLibrary.update', item.id), {
            title: drafts[item.id]?.title || null,
            alt: drafts[item.id]?.alt || null,
        }, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Media details updated.', life: 2500 });
        router.reload({ only: ['items'] });
    } catch (e) {
        toast.add({
            severity: 'error',
            summary: 'Save failed',
            detail: e?.response?.data?.message || 'Could not update media.',
            life: 4000,
        });
    } finally {
        savingId.value = null;
    }
};

const destroy = async (item) => {
    if (!confirm(`Delete “${item.title || item.filename}”? This cannot be undone.`)) {
        return;
    }

    deletingId.value = item.id;
    try {
        await axios.delete(route('mediaLibrary.destroy', item.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        toast.add({ severity: 'success', summary: 'Deleted', life: 2500 });
        router.reload({ only: ['items'] });
    } catch (e) {
        toast.add({
            severity: 'error',
            summary: 'Delete failed',
            detail: e?.response?.data?.message || 'Could not delete media.',
            life: 4000,
        });
    } finally {
        deletingId.value = null;
    }
};

const onUploaded = () => {
    toast.add({ severity: 'success', summary: 'Uploaded', detail: 'Image saved as WebP.', life: 2500 });
    router.reload({ only: ['items'] });
};
</script>
