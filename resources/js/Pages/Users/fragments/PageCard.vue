<template>
    <div
        class="box-bg box-color box-border overflow-hidden rounded-2xl border shadow-sm"
    >
        <div
            v-if="title || $slots.header || $slots.actions"
            class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700/80 sm:flex-row sm:items-center sm:justify-between md:px-6"
        >
            <div class="min-w-0">
                <slot name="header">
                    <h2
                        v-if="title"
                        class="text-base font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ title }}
                    </h2>
                    <p
                        v-if="description"
                        class="mt-0.5 text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ description }}
                    </p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
        <div :class="paddingClass">
            <slot />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        noPadding?: boolean;
    }>(),
    {
        noPadding: false,
    },
);

const paddingClass = computed(() =>
    props.noPadding ? "" : "p-5 md:p-6",
);
</script>
