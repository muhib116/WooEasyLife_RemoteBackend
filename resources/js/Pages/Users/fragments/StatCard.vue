<template>
    <div
        class="box-bg box-color box-border group relative h-full overflow-hidden rounded-2xl border p-3.5 transition-shadow hover:shadow-md sm:p-5"
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
                    class="mt-2 break-words text-xl font-bold leading-tight text-gray-800 sm:text-2xl dark:text-white/90"
                >
                    {{ value }}
                </h4>
                <p
                    v-if="subtitle"
                    class="mt-1 text-[13px] leading-snug text-gray-500 dark:text-gray-400"
                >
                    {{ subtitle }}
                </p>
            </div>
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl sm:h-11 sm:w-11"
                :class="[iconBgClass, compact ? 'max-sm:hidden' : '']"
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
        compact?: boolean;
        accentClass?: string;
        iconBgClass?: string;
        iconClass?: string;
    }>(),
    {
        compact: false,
        accentClass: "bg-primary-500",
        iconBgClass: "bg-primary-50 dark:bg-primary-500/15",
        iconClass: "text-primary-600 dark:text-primary-400",
    },
);
</script>
