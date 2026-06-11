<template>
    <form class="space-y-5" @submit.prevent="$emit('handleSubmit')">
        <div
            class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300"
        >
            Package price = <strong>per order rate × order limit</strong> when
            assigned to a user. No time expiry — quota is order-based.
        </div>

        <div>
            <label
                for="title"
                class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
            >
                Package Title <span class="text-rose-500">*</span>
            </label>
            <InputText
                v-model="form.title"
                id="title"
                placeholder="e.g. Starter Plan"
                class="!w-full"
                autocomplete="off"
            />
            <p v-if="form.errors.title" class="mt-1 text-sm text-rose-500">
                {{ form.errors.title }}
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
                v-model="form.description"
                id="description"
                placeholder="Brief description for admins and merchants"
                class="w-full"
                auto-resize
                rows="3"
            />
            <p
                v-if="form.errors.description"
                class="mt-1 text-sm text-rose-500"
            >
                {{ form.errors.description }}
            </p>
        </div>

        <div>
            <label
                for="per_order_rate"
                class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
            >
                Per Order Rate (TK) <span class="text-rose-500">*</span>
            </label>
            <InputNumber
                v-model="form.per_order_rate"
                input-id="per_order_rate"
                placeholder="e.g. 1.00"
                class="w-full"
                :max-fraction-digits="5"
                :use-grouping="false"
                :min="0"
                suffix=" TK"
            />
            <p
                v-if="form.errors.per_order_rate"
                class="mt-1 text-sm text-rose-500"
            >
                {{ form.errors.per_order_rate }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Charged per order processed against this package.
            </p>
        </div>

        <div
            class="flex cursor-pointer items-center justify-between rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-700"
            @click="form.is_active = !form.is_active"
        >
            <div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                    Active package
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Only active packages can be assigned to users
                </p>
            </div>
            <ToggleSwitch
                v-model="form.is_active"
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
                label="Create Package"
                icon="pi pi-save"
                :loading="form.processing"
            />
        </div>
    </form>
</template>

<script setup lang="ts">
defineProps<{
    form: any;
}>();

defineEmits<{
    onClose: [];
    handleSubmit: [];
}>();
</script>
