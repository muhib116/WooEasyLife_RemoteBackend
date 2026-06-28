<template>
    <div class="space-y-2">
        <label v-if="showLabel" class="text-sm font-semibold">Order limit</label>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="preset in presets"
                :key="preset"
                type="button"
                class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                :class="
                    modelValue === preset
                        ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'
                        : 'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-slate-800'
                "
                @click="$emit('update:modelValue', preset)"
            >
                {{ preset }}
            </button>
            <button
                type="button"
                class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                :class="
                    isCustom
                        ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'
                        : 'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-slate-800'
                "
                @click="$emit('update:modelValue', customValue ?? 300)"
            >
                Custom
            </button>
        </div>
        <InputNumber
            :model-value="modelValue"
            :use-grouping="false"
            :min="1"
            placeholder="e.g. 300"
            class="w-full"
            @update:model-value="$emit('update:modelValue', $event)"
        />
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        presets?: number[];
        showLabel?: boolean;
        customValue?: number;
    }>(),
    {
        presets: () => [100, 200, 500, 1000],
        showLabel: true,
        customValue: 300,
    },
);

defineEmits<{
    "update:modelValue": [value: number | null];
}>();

const isCustom = computed(
    () => props.modelValue != null && !props.presets.includes(props.modelValue),
);
</script>
