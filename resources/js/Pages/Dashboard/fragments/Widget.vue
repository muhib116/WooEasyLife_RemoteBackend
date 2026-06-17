<template>
    <div
        class="box-bg box-color box-border flex h-full flex-col justify-between rounded-2xl border p-5"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ title }}
                </p>
                <h4
                    class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90"
                >
                    {{ value }}
                </h4>
            </div>
            <div
                v-if="icon"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800"
            >
                <Icon :name="icon" class="text-lg text-gray-500 dark:text-gray-400" />
            </div>
        </div>

        <div
            v-if="badge || rightText"
            class="mt-4 space-y-1.5"
        >
            <p
                v-if="rightText"
                class="text-theme-xs text-gray-400 dark:text-gray-500"
            >
                {{ rightText }}
            </p>
            <span
                v-if="badge"
                :class="
                    twMerge(
                        'text-theme-xs inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 font-medium',
                        badgeClass ||
                            'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
                    )
                "
            >
                {{ badge }}
            </span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { twMerge } from "tailwind-merge";
import type { IconName } from "@/types";

defineProps<{
    title: string;
    value: string | number;
    icon?: IconName;
    badge?: string;
    badgeClass?: string;
    rightText?: string;
}>();
</script>
