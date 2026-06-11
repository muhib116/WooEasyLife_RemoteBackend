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
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ title }}
                </p>
                <h4
                    class="mt-2 truncate text-2xl font-bold text-gray-800 dark:text-white/90"
                >
                    {{ value }}
                </h4>
                <p
                    v-if="subtitle"
                    class="mt-1 text-theme-xs text-gray-400 dark:text-gray-500"
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
        <div v-if="badge" class="relative mt-4 flex items-center gap-2">
            <span
                :class="
                    twMerge(
                        'text-theme-xs inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-medium',
                        badgePositive
                            ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500'
                            : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
                        badgeClass,
                    )
                "
            >
                <Icon
                    :name="badgePositive ? 'PhTrendUp' : 'PhTrendDown'"
                    class="text-sm"
                />
                {{ badge }}
            </span>
            <span
                v-if="badgeLabel"
                class="text-theme-xs text-gray-400 dark:text-gray-500"
            >
                {{ badgeLabel }}
            </span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { twMerge } from "tailwind-merge";
import type { IconName } from "@/types";

withDefaults(
    defineProps<{
        title: string;
        value: string | number;
        icon: IconName;
        subtitle?: string;
        badge?: string;
        badgeLabel?: string;
        badgePositive?: boolean;
        accentClass?: string;
        iconBgClass?: string;
        iconClass?: string;
        badgeClass?: string;
    }>(),
    {
        badgePositive: true,
        accentClass: "bg-primary-500",
        iconBgClass: "bg-primary-50 dark:bg-primary-500/15",
        iconClass: "text-primary-600 dark:text-primary-400",
    },
);
</script>
