<template>
    <Dialog
        v-model:visible="visibleProxy"
        modal
        :header="title"
        :style="{ width: 'min(92vw, 720px)' }"
        :breakpoints="{ '960px': '95vw' }"
        @hide="reset"
    >
        <div class="space-y-4">
            <input
                ref="fileInput"
                type="file"
                accept="image/*"
                class="hidden"
                @change="onFilePicked"
            >

            <div v-if="!previewUrl" class="space-y-4">
                <div
                    class="rounded-xl border-2 border-dashed p-8 text-center transition"
                    :class="dragging
                        ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/10'
                        : 'border-slate-300 dark:border-slate-600'"
                    @dragenter.prevent="onDragEnter"
                    @dragover.prevent="onDragOver"
                    @dragleave.prevent="onDragLeave"
                    @drop.prevent="onDrop"
                >
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                        Drag & drop an image here
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        or paste from clipboard (Ctrl/⌘+V) · max 8MB
                    </p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <Button
                            label="Select image"
                            icon="pi pi-upload"
                            @click="fileInput?.click()"
                        />
                        <Button
                            label="Paste"
                            icon="pi pi-clipboard"
                            severity="secondary"
                            outlined
                            :loading="pasting"
                            @click="pasteFromClipboard"
                        />
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <label class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300">
                        Upload from URL
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <InputText
                            v-model="urlField"
                            class="min-w-[12rem] flex-1"
                            placeholder="https://example.com/image.jpg"
                            @keydown.enter.prevent="loadFromUrl"
                        />
                        <Button
                            label="Fetch"
                            icon="pi pi-link"
                            severity="secondary"
                            outlined
                            :loading="fetchingUrl"
                            :disabled="!urlField.trim() || fetchingUrl"
                            @click="loadFromUrl"
                        />
                    </div>
                </div>
            </div>

            <div v-else class="space-y-3">
                <div
                    class="max-h-[50vh] overflow-hidden rounded-xl bg-slate-900"
                    @dragenter.prevent="onDragEnter"
                    @dragover.prevent="onDragOver"
                    @dragleave.prevent="onDragLeave"
                    @drop.prevent="onDrop"
                >
                    <img
                        ref="imageEl"
                        :src="previewUrl"
                        alt="Crop preview"
                        class="block max-w-full"
                    >
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Title</label>
                        <InputText v-model="titleField" class="w-full" placeholder="Optional title" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Alt text</label>
                        <InputText v-model="altField" class="w-full" placeholder="Optional alt" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        label="Replace image"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        :disabled="uploading"
                        @click="fileInput?.click()"
                    />
                    <Button
                        label="Paste"
                        icon="pi pi-clipboard"
                        severity="secondary"
                        outlined
                        :disabled="uploading"
                        :loading="pasting"
                        @click="pasteFromClipboard"
                    />
                    <Button
                        label="Upload WebP"
                        icon="pi pi-check"
                        :loading="uploading"
                        :disabled="uploading"
                        @click="uploadCropped"
                    />
                </div>
            </div>

            <p v-if="error" class="text-sm text-rose-500">{{ error }}</p>
        </div>
    </Dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const props = defineProps({
    visible: { type: Boolean, default: false },
    title: { type: String, default: 'Upload media' },
});

const emit = defineEmits(['update:visible', 'uploaded']);

const visibleProxy = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const fileInput = ref(null);
const imageEl = ref(null);
const previewUrl = ref('');
const titleField = ref('');
const altField = ref('');
const urlField = ref('');
const uploading = ref(false);
const fetchingUrl = ref(false);
const pasting = ref(false);
const dragging = ref(false);
const error = ref('');
let cropper = null;
let dragDepth = 0;

const destroyCropper = () => {
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
};

const reset = () => {
    destroyCropper();
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = '';
    titleField.value = '';
    altField.value = '';
    urlField.value = '';
    error.value = '';
    uploading.value = false;
    fetchingUrl.value = false;
    pasting.value = false;
    dragging.value = false;
    dragDepth = 0;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const initCropper = async () => {
    await nextTick();
    destroyCropper();
    if (!imageEl.value) {
        return;
    }

    cropper = new Cropper(imageEl.value, {
        viewMode: 1,
        autoCropArea: 1,
        responsive: true,
        background: false,
        movable: true,
        zoomable: true,
        scalable: false,
        rotatable: false,
    });
};

const loadImageSource = async (source, suggestedName = '') => {
    error.value = '';
    destroyCropper();
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    if (source instanceof Blob) {
        previewUrl.value = URL.createObjectURL(source);
        if (!titleField.value && suggestedName) {
            titleField.value = suggestedName.replace(/\.[^.]+$/, '');
        }
    } else if (typeof source === 'string') {
        previewUrl.value = source;
        if (!titleField.value && suggestedName) {
            titleField.value = suggestedName.replace(/\.[^.]+$/, '');
        }
    } else {
        throw new Error('Unsupported image source');
    }

    await initCropper();
};

const acceptImageFile = async (file) => {
    if (!file || !String(file.type || '').startsWith('image/')) {
        error.value = 'Please provide an image file.';
        return;
    }

    if (file.size > 8 * 1024 * 1024) {
        error.value = 'Image must be 8MB or smaller.';
        return;
    }

    await loadImageSource(file, file.name || 'clipboard-image');
};

const onFilePicked = async (event) => {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }
    await acceptImageFile(file);
};

const onDragEnter = () => {
    dragDepth += 1;
    dragging.value = true;
};

const onDragOver = () => {
    dragging.value = true;
};

const onDragLeave = () => {
    dragDepth = Math.max(0, dragDepth - 1);
    if (dragDepth === 0) {
        dragging.value = false;
    }
};

const onDrop = async (event) => {
    dragDepth = 0;
    dragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (!file) {
        error.value = 'No image found in the drop.';
        return;
    }
    await acceptImageFile(file);
};

const pasteFromClipboard = async () => {
    pasting.value = true;
    error.value = '';
    try {
        if (!navigator.clipboard?.read) {
            error.value = 'Clipboard image paste is not supported in this browser. Use Ctrl/⌘+V instead.';
            return;
        }

        const items = await navigator.clipboard.read();
        for (const item of items) {
            const type = item.types.find((t) => t.startsWith('image/'));
            if (!type) {
                continue;
            }
            const blob = await item.getType(type);
            await acceptImageFile(new File([blob], `clipboard.${type.split('/')[1] || 'png'}`, { type }));
            return;
        }
        error.value = 'No image found on the clipboard.';
    } catch (e) {
        error.value = e?.message || 'Could not read clipboard. Try Ctrl/⌘+V, or grant clipboard permission.';
    } finally {
        pasting.value = false;
    }
};

const onWindowPaste = async (event) => {
    if (!props.visible || uploading.value) {
        return;
    }

    const items = event.clipboardData?.items;
    if (!items?.length) {
        return;
    }

    for (const item of items) {
        if (!item.type.startsWith('image/')) {
            continue;
        }
        event.preventDefault();
        const file = item.getAsFile();
        if (file) {
            await acceptImageFile(file);
        }
        return;
    }
};

const loadFromUrl = async () => {
    const url = urlField.value.trim();
    if (!url) {
        error.value = 'Enter an image URL.';
        return;
    }

    fetchingUrl.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(route('mediaLibrary.fetchUrl'), { url }, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const binary = atob(data.data);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i += 1) {
            bytes[i] = binary.charCodeAt(i);
        }
        const blob = new Blob([bytes], { type: data.mime || 'image/jpeg' });
        await loadImageSource(blob, data.filename || 'remote-image');
    } catch (e) {
        error.value = e?.response?.data?.message
            || e?.response?.data?.errors?.url?.[0]
            || e?.message
            || 'Could not fetch that URL.';
    } finally {
        fetchingUrl.value = false;
    }
};

const canvasToWebpBlob = (canvas) => new Promise((resolve, reject) => {
    canvas.toBlob(
        (blob) => {
            if (!blob) {
                reject(new Error('Failed to encode WebP'));
                return;
            }
            resolve(blob);
        },
        'image/webp',
        0.88,
    );
});

const uploadCropped = async () => {
    if (!cropper) {
        error.value = 'Select and crop an image first.';
        return;
    }

    uploading.value = true;
    error.value = '';

    try {
        const canvas = cropper.getCroppedCanvas({
            maxWidth: 1920,
            maxHeight: 1920,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        if (!canvas) {
            throw new Error('Could not crop image');
        }

        const blob = await canvasToWebpBlob(canvas);
        const formData = new FormData();
        formData.append('file', blob, `${(titleField.value || 'media').replace(/[^\w.-]+/g, '-')}.webp`);
        if (altField.value) {
            formData.append('alt', altField.value);
        }
        if (titleField.value) {
            formData.append('title', titleField.value);
        }

        const { data } = await axios.post(route('mediaLibrary.store'), formData, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        emit('uploaded', data.media);
        visibleProxy.value = false;
        reset();
    } catch (e) {
        error.value = e?.response?.data?.message
            || e?.response?.data?.errors?.file?.[0]
            || e?.message
            || 'Upload failed.';
    } finally {
        uploading.value = false;
    }
};

watch(
    () => props.visible,
    (open) => {
        if (open) {
            window.addEventListener('paste', onWindowPaste);
        } else {
            window.removeEventListener('paste', onWindowPaste);
            reset();
        }
    },
);

onBeforeUnmount(() => {
    window.removeEventListener('paste', onWindowPaste);
    reset();
});
</script>
