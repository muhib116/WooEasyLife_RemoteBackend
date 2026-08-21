<template>
    <form class="space-y-5" @submit.prevent="$emit('submit')">
        <div
            v-if="errorBanner"
            class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200"
        >
            <p class="font-semibold">Could not save this version</p>
            <p class="mt-1 whitespace-pre-wrap">{{ errorBanner }}</p>
        </div>

        <div>
            <label
                for="version"
                class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
            >
                Version <span class="text-rose-500">*</span>
            </label>
            <InputText
                v-model="form.version"
                id="version"
                placeholder="e.g. 1.2.0"
                class="!w-full"
                autocomplete="off"
                @update:model-value="form.errors.version = ''"
            />
            <p
                v-if="form.errors.version"
                class="mt-1 text-sm text-rose-500"
            >
                {{ form.errors.version }}
            </p>
        </div>

        <div>
            <label
                for="settings"
                class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
            >
                Settings (JSON) <span class="text-rose-500">*</span>
            </label>
            <textarea
                id="settings"
                v-model="form.settings"
                rows="10"
                placeholder='{"name": "WooEasyLife", "requires": "5.8"}'
                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 font-mono text-sm text-gray-800 outline-none ring-primary-500 focus:ring-2 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-100"
                @input="form.errors.settings = ''"
            />
            <p
                v-if="form.errors.settings"
                class="mt-1 text-sm text-rose-500"
            >
                {{ form.errors.settings }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Must be valid JSON. This metadata is served to the plugin update API.
            </p>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                Plugin ZIP
                <span v-if="!form.id" class="text-rose-500">*</span>
                <span
                    v-else
                    class="text-xs font-normal text-gray-500 dark:text-gray-400"
                >
                    (optional — leave empty to keep current file)
                </span>
            </label>
            <div
                class="flex flex-col gap-3 rounded-xl border border-dashed border-gray-200 p-4 dark:border-gray-700 sm:flex-row sm:items-center"
            >
                <label
                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-primary-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-600"
                >
                    <i class="pi pi-upload" />
                    Choose ZIP file
                    <input
                        type="file"
                        class="hidden"
                        accept=".zip,application/zip"
                        @change="$emit('file-select', $event)"
                    />
                </label>
                <div class="min-w-0 text-sm text-gray-600 dark:text-gray-400">
                    <span v-if="fileName" class="font-medium text-gray-800 dark:text-gray-200">
                        {{ fileName }}
                    </span>
                    <span v-else>No file selected</span>
                </div>
            </div>
            <p v-if="form.errors.file" class="mt-1 text-sm text-rose-500">
                {{ form.errors.file }}
            </p>
        </div>

        <div class="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                outlined
                @click="$emit('cancel')"
            />
            <Button
                type="submit"
                :label="form.id ? 'Save Changes' : 'Publish Version'"
                icon="pi pi-save"
                :loading="form.processing"
            />
        </div>
    </form>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{
    form: any;
    fileName?: string | null;
    submitError?: string | null;
}>();

defineEmits<{
    submit: [];
    cancel: [];
    "file-select": [event: Event];
}>();

const errorBanner = computed(() => {
    if (props.submitError) {
        return props.submitError;
    }

    const errors = props.form?.errors || {};
    const messages = Object.values(errors).filter(
        (message): message is string =>
            typeof message === "string" && message.trim().length > 0,
    );

    return messages.length ? messages.join("\n") : "";
});
</script>
