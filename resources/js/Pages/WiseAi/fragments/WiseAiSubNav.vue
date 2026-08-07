<template>
    <div
        class="flex flex-wrap items-center gap-1.5 rounded-2xl border border-gray-200 bg-white p-1.5 shadow-sm dark:border-gray-700 dark:bg-slate-900/60"
    >
        <Link
            v-for="item in primary"
            :key="item.name"
            :href="route(item.name)"
            :title="item.title"
            class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition-colors sm:px-4"
            :class="
                isActive(item.name)
                    ? 'bg-fuchsia-600 text-white shadow-sm'
                    : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-slate-800'
            "
        >
            <Icon :name="item.icon" class="text-lg" />
            <span>{{ item.label }}</span>
        </Link>

        <div class="relative ml-auto" ref="advRoot">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition-colors sm:px-4"
                :class="
                    advancedOpen || advancedActive
                        ? 'bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900'
                        : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-slate-800'
                "
                :aria-expanded="advancedOpen"
                @click="advancedOpen = !advancedOpen"
            >
                <Icon name="PhSquaresFour" class="text-lg" />
                Advanced
                <Icon :name="advancedOpen ? 'PhCaretUp' : 'PhCaretDown'" class="text-sm opacity-80" />
            </button>
            <div
                v-if="advancedOpen"
                class="absolute right-0 z-40 mt-1.5 min-w-[12rem] rounded-xl border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-slate-900"
            >
                <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                    Founder tools
                </p>
                <Link
                    v-for="item in advanced"
                    :key="item.name"
                    :href="route(item.name)"
                    :title="item.title"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                    :class="
                        isActive(item.name)
                            ? 'bg-fuchsia-50 text-fuchsia-800 dark:bg-fuchsia-500/15 dark:text-fuchsia-200'
                            : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-slate-800'
                    "
                    @click="advancedOpen = false"
                >
                    <Icon :name="item.icon" class="text-lg" />
                    {{ item.label }}
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import { Icon } from "@/plugins";
import type { IconName } from "@/types";

type NavItem = { label: string; name: string; icon: IconName; title?: string };

const primary: NavItem[] = [
    { label: "Dashboard", name: "wiseAi.dashboard", icon: "PhGauge", title: "Home — health & next actions" },
    { label: "Config", name: "wiseAi.config", icon: "PhSlidersHorizontal", title: "API keys & settings" },
    { label: "Knowledge", name: "wiseAi.knowledge", icon: "PhBooks", title: "Facts — draft → publish" },
    { label: "Language", name: "wiseAi.language", icon: "PhTranslate", title: "Abbrev / Banglish promote" },
    { label: "Playground", name: "wiseAi.playground", icon: "PhFlask", title: "Test real /decide API" },
    { label: "Log", name: "wiseAi.log", icon: "PhHardDrives", title: "Decide request / response analysis" },
    { label: "Learning", name: "wiseAi.learning", icon: "PhTray", title: "কাজের তালিকা — review inbox" },
    { label: "Help", name: "wiseAi.tutorials", icon: "PhGraduationCap", title: "Tutorials & checklist" },
];

const advanced: NavItem[] = [
    { label: "Train", name: "wiseAi.train", icon: "PhUploadSimple", title: "JSON packs (advanced)" },
    { label: "Lab", name: "wiseAi.lab", icon: "PhTestTube", title: "Language pack browser" },
    { label: "Intelligence", name: "wiseAi.intelligence", icon: "PhChartLineUp", title: "Merchant BI reports" },
    { label: "Fleet", name: "wiseAi.fleet", icon: "PhBroadcast", title: "Founder multi-key health" },
];

const advancedOpen = ref(false);
const advRoot = ref<HTMLElement | null>(null);

const isActive = (name: string) => Boolean(route().current(name));

const advancedActive = computed(() => advanced.some((item) => isActive(item.name)));

const onDocClick = (e: MouseEvent) => {
    if (!advancedOpen.value || !advRoot.value) return;
    if (!advRoot.value.contains(e.target as Node)) {
        advancedOpen.value = false;
    }
};

onMounted(() => document.addEventListener("click", onDocClick));
onUnmounted(() => document.removeEventListener("click", onDocClick));
</script>
