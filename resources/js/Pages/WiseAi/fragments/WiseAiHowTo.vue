<template>
    <div
        class="rounded-2xl border border-fuchsia-200/80 bg-gradient-to-br from-fuchsia-50/90 to-white shadow-sm dark:border-fuchsia-500/25 dark:from-fuchsia-500/10 dark:to-slate-900/60"
    >
        <button
            type="button"
            class="flex w-full items-start justify-between gap-3 px-4 py-3 text-left sm:px-5"
            :aria-expanded="expanded"
            @click="toggle"
        >
            <div class="min-w-0">
                <p class="flex items-center gap-2 text-sm font-semibold text-fuchsia-800 dark:text-fuchsia-200">
                    <Icon name="PhInfo" class="shrink-0 text-lg" />
                    <span class="truncate">{{ title }}</span>
                    <span
                        v-if="badge"
                        class="hidden rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-fuchsia-700 shadow-sm sm:inline dark:bg-slate-900 dark:text-fuchsia-300"
                    >
                        {{ badge }}
                    </span>
                </p>
                <p v-if="subtitle" class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">
                    {{ subtitle }}
                </p>
            </div>
            <span class="shrink-0 text-xs font-semibold text-fuchsia-700 dark:text-fuchsia-300">
                {{ expanded ? "সংক্ষেপ" : "বিস্তারিত" }}
            </span>
        </button>

        <div v-if="expanded" class="border-t border-fuchsia-100 px-4 pb-4 pt-3 sm:px-5 dark:border-fuchsia-500/20">
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
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { Icon } from "@/plugins";

export type HowToStep = {
    title: string;
    detail?: string;
};

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        badge?: string;
        steps: HowToStep[];
        tips?: string[];
        storageKey?: string;
    }>(),
    {
        title: "কীভাবে ব্যবহার করবেন",
        subtitle: "",
        badge: "Guide",
        tips: () => [],
        storageKey: "",
    },
);

const expanded = ref(false);

const key = () => {
    if (props.storageKey) return `wiseAi.howTo.${props.storageKey}`;
    const slug = (props.title || "guide").toLowerCase().replace(/[^a-z0-9]+/g, "-").slice(0, 48);
    return `wiseAi.howTo.${slug}`;
};

onMounted(() => {
    try {
        expanded.value = localStorage.getItem(key()) === "1";
    } catch {
        expanded.value = false;
    }
});

const toggle = () => {
    expanded.value = !expanded.value;
    try {
        localStorage.setItem(key(), expanded.value ? "1" : "0");
    } catch {
        // ignore
    }
};
</script>
