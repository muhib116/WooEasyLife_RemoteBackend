<template>
    <div>
        <div class="mb-4 flex flex-col gap-1">
            <label for="title" class="text-sm font-semibold">License title</label>
            <div class="relative flex-auto">
                <InputText
                    v-model="tokenForm.title"
                    id="title"
                    placeholder="e.g. Main store license"
                    class="!w-full"
                />
                <span
                    v-if="tokenForm.errors.title"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ tokenForm.errors.title }}</span
                >
            </div>
        </div>
        <div class="mb-4 flex flex-col gap-1">
            <label for="description" class="text-sm font-semibold">Description</label>
            <div class="relative flex-auto">
                <Textarea
                    v-model="tokenForm.description"
                    id="description"
                    placeholder="Optional notes about this license"
                    class="w-full"
                    autoResize
                    rows="3"
                />
                <span
                    v-if="tokenForm.errors.description"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ tokenForm.errors.description }}</span
                >
            </div>
        </div>
        <div class="mb-4 flex flex-col gap-1">
            <label for="user_package_id" class="text-sm font-semibold">
                Website plan
            </label>
            <div class="relative flex-auto">
                <Select
                    id="user_package_id"
                    class="w-full"
                    v-model="tokenForm.user_package_id"
                    :options="user_packages"
                    :optionLabel="(item) => `(${item.domain})`"
                    optionValue="id"
                    placeholder="Select website plan"
                />
                <span
                    v-if="tokenForm.errors.user_package_id"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ tokenForm.errors.user_package_id }}</span
                >
            </div>
        </div>
        <div class="mb-4 flex flex-col gap-1">
            <label for="expires_at" class="text-sm font-semibold">Expires at</label>
            <div class="relative flex-auto">
                <div class="flex-auto">
                    <DatePicker
                        id="expires_at"
                        v-model="tokenForm.expires_at"
                        showTime
                        hourFormat="12"
                        dateFormat="dd/mm/yy"
                        placeholder="No expiration"
                        fluid
                    />
                </div>
                <span
                    v-if="tokenForm.errors.expires_at"
                    class="absolute -bottom-6 left-0 text-red-500"
                    >{{ tokenForm.errors.expires_at }}</span
                >
            </div>
        </div>
        <label class="flex items-center gap-5">
            <ToggleSwitch v-model="tokenForm.status" />
            Active license
        </label>

        <div
            v-if="showSummary && selectedPackage"
            class="my-4 rounded-xl border border-gray-100 bg-slate-50 p-4 text-sm dark:border-gray-700 dark:bg-slate-900/40"
        >
            <p class="font-semibold">Summary</p>
            <p class="mt-1 text-gray-600 dark:text-gray-300">
                License for website
                <strong>{{ selectedPackage.domain }}</strong>
            </p>
        </div>

        <div class="flex justify-end gap-2">
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                @click="$emit('onClose')"
            ></Button>
            <Button
                type="submit"
                :label="tokenForm.id ? 'Update License' : 'Generate License'"
                :loading="tokenForm.processing"
                @click="$emit('handleSave')"
            ></Button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
    defineProps<{
        tokenForm: any;
        user_packages: any[];
        showSummary?: boolean;
    }>(),
    {
        showSummary: false,
    },
);

const selectedPackage = computed(() =>
    props.user_packages.find(
        (item) => item.id == props.tokenForm.user_package_id,
    ),
);
</script>
