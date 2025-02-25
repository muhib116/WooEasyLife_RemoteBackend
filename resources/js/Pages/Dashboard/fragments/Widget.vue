<template>
    <div class="box-bg box-color box-border rounded-2xl border p-5">
        <p class="text-theme-sm text-black dark:text-white">
            {{ title }}
        </p>

        <div class="mt-3 flex items-end justify-between">
            <div>
                <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ value }}
                </h4>
            </div>

            <div class="flex items-center gap-1">
                <span
                    v-if="badge"
                    :class="
                        twMerge(
                            'text-theme-xs flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500',
                            badgeClass,
                        )
                    "
                >
                    {{ badge }}
                </span>

                <span
                    v-if="rightText"
                    class="text-theme-xs text-gray-500 dark:text-gray-400"
                >
                    {{ rightText }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { twMerge } from "tailwind-merge";

defineProps<{
    title: string;
    value: any;
    badge?: any;
    badgeClass?: string;
    rightText?: any;
}>();
</script>
