<template>
    <div
        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 px-6 py-14 text-center dark:border-gray-700"
    >
        <div
            class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800"
        >
            <Icon :name="icon" class="text-2xl text-gray-400 dark:text-gray-500" />
        </div>
        <p class="font-medium text-gray-800 dark:text-gray-200">
            {{ title }}
        </p>
        <p
            v-if="description"
            class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400"
        >
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
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
        icon?: IconName;
    }>(),
    {
        icon: "PhFolderOpen",
    },
);
</script>
