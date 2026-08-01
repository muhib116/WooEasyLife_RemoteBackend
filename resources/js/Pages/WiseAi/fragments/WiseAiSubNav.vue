<template>
    <div
        class="flex flex-wrap gap-2 rounded-2xl border border-gray-200 bg-white p-1.5 shadow-sm dark:border-gray-700 dark:bg-slate-900/60"
    >
        <Link
            v-for="item in items"
            :key="item.name"
            :href="route(item.name)"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition-colors"
            :class="
                route().current(item.name)
                    ? 'bg-fuchsia-600 text-white shadow-sm'
                    : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-slate-800'
            "
        >
            <Icon :name="item.icon" class="text-lg" />
            {{ item.label }}
        </Link>
        <span
            class="ml-auto hidden items-center gap-1.5 self-center rounded-full bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-700 sm:inline-flex dark:bg-amber-500/10 dark:text-amber-300"
        >
            <Icon name="PhSparkle" class="text-sm" />
            UI Preview
        </span>
    </div>
</template>

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { Icon } from "@/plugins";
import type { IconName } from "@/types";

const items: { label: string; name: string; icon: IconName }[] = [
    { label: "Dashboard", name: "wiseAi.dashboard", icon: "PhGauge" },
    { label: "Playground", name: "wiseAi.playground", icon: "PhFlask" },
    { label: "Knowledge", name: "wiseAi.knowledge", icon: "PhBooks" },
    { label: "Config", name: "wiseAi.config", icon: "PhSlidersHorizontal" },
];
</script>
