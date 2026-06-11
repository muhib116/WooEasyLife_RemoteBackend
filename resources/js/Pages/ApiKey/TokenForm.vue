<template>
    <form class="space-y-5" @submit.prevent="$emit('handleSave')">
        <div>
            <label
                for="title"
                class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
            >
                Token Title
            </label>
            <InputText
                v-model="tokenForm.title"
                id="title"
                placeholder="Auto-generated if left empty"
                class="!w-full"
            />
            <p v-if="tokenForm.errors.title" class="mt-1 text-sm text-rose-500">
                {{ tokenForm.errors.title }}
            </p>
        </div>

        <div>
            <label
                for="domain"
                class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
            >
                Domain <span class="text-rose-500">*</span>
            </label>
            <InputText
                v-model="tokenForm.domain"
                id="domain"
                placeholder="merchant-store.com"
                class="!w-full"
            />
            <p v-if="tokenForm.errors.domain" class="mt-1 text-sm text-rose-500">
                {{ tokenForm.errors.domain }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Whitelisted domain for API access. Must resolve via DNS.
            </p>
        </div>

        <div>
            <label
                for="description"
                class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
            >
                Description
            </label>
            <Textarea
                v-model="tokenForm.description"
                id="description"
                placeholder="Optional notes about this token"
                class="w-full"
                auto-resize
                rows="3"
            />
            <p
                v-if="tokenForm.errors.description"
                class="mt-1 text-sm text-rose-500"
            >
                {{ tokenForm.errors.description }}
            </p>
        </div>

        <div>
            <label
                class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
            >
                Expires At
            </label>
            <DatePicker
                v-model="tokenForm.expires_at"
                show-time
                hour-format="12"
                date-format="dd/mm/yy"
                fluid
                placeholder="No expiration"
            />
            <p
                v-if="tokenForm.errors.expires_at"
                class="mt-1 text-sm text-rose-500"
            >
                {{ tokenForm.errors.expires_at }}
            </p>
        </div>

        <div
            class="flex cursor-pointer items-center justify-between rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-700"
            @click="tokenForm.status = !tokenForm.status"
        >
            <div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                    Token enabled
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Disabled tokens cannot access the API
                </p>
            </div>
            <ToggleSwitch
                v-model="tokenForm.status"
                class="pointer-events-none"
            />
        </div>

        <div
            class="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700"
        >
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                outlined
                @click="$emit('onClose')"
            />
            <Button
                type="submit"
                :label="tokenForm.id ? 'Save Changes' : 'Generate Token'"
                icon="pi pi-key"
                :loading="tokenForm.processing"
            />
        </div>
    </form>
</template>

<script setup lang="ts">
defineProps<{
    tokenForm: any;
}>();
</script>
