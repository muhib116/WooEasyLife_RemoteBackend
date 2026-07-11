<template>
    <div
        class="box-bg box-color box-border rounded-2xl border px-5 py-4 shadow-sm md:px-6"
    >
        <div
            class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
                    :class="iconBgClass"
                >
                    <Icon :name="icon" class="text-2xl" :class="iconClass" />
                </div>
                <div>
                    <h1
                        class="text-xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
            <div
                v-if="$slots.actions"
                class="flex shrink-0 flex-wrap items-center gap-2 self-start lg:self-center"
            >
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import type { IconName } from "@/types";

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon: IconName;
        iconBgClass?: string;
        iconClass?: string;
    }>(),
    {
        iconBgClass: "bg-primary-50 dark:bg-primary-500/15",
        iconClass: "text-primary-600 dark:text-primary-400",
    },
);
</script>
