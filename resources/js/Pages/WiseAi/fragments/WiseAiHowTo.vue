<template>
    <div
        class="rounded-2xl border border-fuchsia-200/80 bg-gradient-to-br from-fuchsia-50/90 to-white p-5 shadow-sm dark:border-fuchsia-500/25 dark:from-fuchsia-500/10 dark:to-slate-900/60"
    >
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="flex items-center gap-2 text-sm font-semibold text-fuchsia-800 dark:text-fuchsia-200">
                    <Icon name="PhInfo" class="text-lg" />
                    {{ title }}
                </p>
                <p v-if="subtitle" class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                    {{ subtitle }}
                </p>
            </div>
            <span
                v-if="badge"
                class="rounded-full bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-fuchsia-700 shadow-sm dark:bg-slate-900 dark:text-fuchsia-300"
            >
                {{ badge }}
            </span>
        </div>

        <ol class="space-y-2.5">
            <li
                v-for="(step, index) in steps"
                :key="index"
                class="flex gap-3 text-sm text-gray-700 dark:text-gray-200"
            >
                <span
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-fuchsia-600 text-[11px] font-bold text-white"
                >
                    {{ index + 1 }}
                </span>
                <span class="min-w-0 leading-relaxed pt-0.5">
                    <span class="font-medium text-gray-900 dark:text-white">{{ step.title }}</span>
                    <span v-if="step.detail" class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                        {{ step.detail }}
                    </span>
                </span>
            </li>
        </ol>

        <div
            v-if="tips.length"
            class="mt-4 rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2.5 dark:border-amber-500/20 dark:bg-amber-500/10"
        >
            <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">
                মনে রাখুন
            </p>
            <ul class="space-y-1 text-xs text-amber-900/90 dark:text-amber-100/90">
                <li v-for="(tip, i) in tips" :key="i" class="flex gap-2">
                    <span class="shrink-0">•</span>
                    <span>{{ tip }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";

export type HowToStep = {
    title: string;
    detail?: string;
};

withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        badge?: string;
        steps: HowToStep[];
        tips?: string[];
    }>(),
    {
        title: "কীভাবে ব্যবহার করবেন",
        subtitle: "",
        badge: "Start here",
        tips: () => [],
    },
);
</script>
