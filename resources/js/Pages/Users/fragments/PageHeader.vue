<template>
    <div
        class="box-bg box-color box-border rounded-2xl border px-4 py-4 shadow-sm sm:px-5 md:px-6"
    >
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="flex min-w-0 flex-1 items-start gap-3 sm:gap-4">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl sm:h-12 sm:w-12"
                    :class="iconBgClass"
                >
                    <Icon :name="icon" class="text-xl sm:text-2xl" :class="iconClass" />
                </div>
                <div class="min-w-0 flex-1">
                    <h1
                        class="text-lg font-semibold text-gray-900 sm:text-xl dark:text-white"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-1 max-w-2xl text-sm leading-relaxed text-gray-500 dark:text-gray-400"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
            <div
                v-if="$slots.actions"
                class="flex w-full min-w-0 flex-wrap items-center gap-2 xl:max-w-[min(100%,40rem)] xl:justify-end"
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
