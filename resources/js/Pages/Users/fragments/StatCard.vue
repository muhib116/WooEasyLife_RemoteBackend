<template>
    <div
        class="box-bg box-color box-border group relative overflow-hidden rounded-2xl border p-5 transition-shadow hover:shadow-md"
    >
        <div
            class="absolute -right-3 -top-3 h-24 w-24 rounded-full opacity-10"
            :class="accentClass"
        />
        <div class="relative flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ title }}
                </p>
                <h4
                    class="mt-2 truncate text-2xl font-bold text-gray-800 dark:text-white/90"
                >
                    {{ value }}
                </h4>
                <p
                    v-if="subtitle"
                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                >
                    {{ subtitle }}
                </p>
            </div>
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                :class="iconBgClass"
            >
                <Icon :name="icon" class="text-xl" :class="iconClass" />
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
        value: string | number;
        icon: IconName;
        subtitle?: string;
        accentClass?: string;
        iconBgClass?: string;
        iconClass?: string;
    }>(),
    {
        accentClass: "bg-primary-500",
        iconBgClass: "bg-primary-50 dark:bg-primary-500/15",
        iconClass: "text-primary-600 dark:text-primary-400",
    },
);
</script>
