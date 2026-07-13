<template>
    <Dialog
        v-model:visible="visibleProxy"
        modal
        :header="title"
        :style="{ width: 'min(96vw, 960px)' }"
        :breakpoints="{ '960px': '96vw' }"
    >
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <InputText
                    v-model="search"
                    class="min-w-[12rem] flex-1"
                    placeholder="Search media…"
                    @keyup.enter="load"
                />
                <Button label="Search" icon="pi pi-search" outlined @click="load" />
                <Button label="Upload new" icon="pi pi-upload" @click="showUpload = true" />
            </div>

            <p v-if="loading" class="text-sm text-slate-500">Loading…</p>
            <p v-else-if="!items.length" class="text-sm text-slate-500">No media yet. Upload an image to get started.</p>

            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                <button
                    v-for="item in items"
                    :key="item.id"
                    type="button"
                    class="group overflow-hidden rounded-xl border text-left transition"
                    :class="selectedId === item.id
                        ? 'border-amber-500 ring-2 ring-amber-400/40'
                        : 'border-slate-200 hover:border-amber-400 dark:border-slate-700'"
                    @click="selectedId = item.id"
                    @dblclick="confirmSelect"
                >
                    <div class="aspect-square bg-slate-100 dark:bg-slate-800">
                        <img :src="item.url" :alt="item.alt || item.title" class="h-full w-full object-cover">
                    </div>
                    <div class="p-2">
                        <p class="truncate text-xs font-medium text-slate-800 dark:text-slate-100">
                            {{ item.title || item.filename }}
                        </p>
                        <p class="text-[11px] text-slate-500">
                            {{ item.width }}×{{ item.height }} · {{ item.human_size }}
                        </p>
                    </div>
                </button>
            </div>
        </div>

        <template #footer>
            <div class="flex flex-wrap justify-end gap-2">
                <Button label="Cancel" severity="secondary" outlined @click="visibleProxy = false" />
                <Button
                    label="Use selected"
                    icon="pi pi-check"
                    :disabled="!selected"
                    @click="confirmSelect"
                />
            </div>
        </template>
    </Dialog>

    <MediaUploadDialog v-model:visible="showUpload" @uploaded="onUploaded" />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import MediaUploadDialog from '@/components/media/MediaUploadDialog.vue';

const props = defineProps({
    visible: { type: Boolean, default: false },
    title: { type: String, default: 'Media library' },
});

const emit = defineEmits(['update:visible', 'select']);

const visibleProxy = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const items = ref([]);
const loading = ref(false);
const search = ref('');
const selectedId = ref(null);
const showUpload = ref(false);

const selected = computed(() => items.value.find((item) => item.id === selectedId.value) || null);

const load = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('mediaLibrary.list'), {
            params: { q: search.value || undefined },
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        items.value = data.data || [];
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
};

const onUploaded = (media) => {
    items.value = [media, ...items.value.filter((item) => item.id !== media.id)];
    selectedId.value = media.id;
};

const confirmSelect = () => {
    if (!selected.value) {
        return;
    }

    emit('select', selected.value);
    visibleProxy.value = false;
};

watch(
    () => props.visible,
    (open) => {
        if (open) {
            selectedId.value = null;
            load();
        }
    },
);
</script>
