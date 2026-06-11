<template>
    <aside class="flex h-full flex-col">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-200/80 px-5 dark:border-gray-800">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-primary-50 dark:bg-primary-500/15"
            >
                <img src="/app-logo" alt="Logo" class="h-8 w-8 object-contain" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-gray-900 dark:text-white">
                    WooEasyLife
                </p>
                <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Admin Console
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 lg:hidden dark:hover:bg-slate-800"
                @click="$emit('close')"
            >
                <Icon name="PhX" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 admin-scrollbar">
            <div v-for="section in sections" :key="section.label" class="mb-5">
                <p
                    class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                >
                    {{ section.label }}
                </p>
                <ul class="space-y-1">
                    <li v-for="item in section.items" :key="item.name">
                        <Link
                            :href="route(item.name)"
                            class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                            :class="
                                isActive(item.name)
                                    ? 'bg-primary-50 text-primary-700 shadow-sm dark:bg-primary-500/15 dark:text-primary-300'
                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-slate-800 dark:hover:text-gray-100'
                            "
                            @click="$emit('close')"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition"
                                :class="
                                    isActive(item.name)
                                        ? 'bg-primary-100 text-primary-600 dark:bg-primary-500/25 dark:text-primary-300'
                                        : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200 dark:bg-slate-800 dark:text-gray-400'
                                "
                            >
                                <Icon :name="item.icon" class="text-lg" />
                            </span>
                            <span class="truncate">{{ item.title }}</span>
                            <span
                                v-if="isActive(item.name)"
                                class="ml-auto h-1.5 w-1.5 rounded-full bg-primary-500"
                            />
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="shrink-0 border-t border-gray-200/80 p-4 dark:border-gray-800">
            <div
                class="rounded-xl bg-gradient-to-br from-primary-500/10 to-primary-600/5 p-3 dark:from-primary-500/20 dark:to-transparent"
            >
                <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                    Natural Care Platform
                </p>
                <p class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-400">
                    Merchant & courier management
                </p>
            </div>
        </div>
    </aside>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import type { IconName } from "@/types";

defineEmits<{
    close: [];
}>();

type NavItem = {
    title: string;
    name: string;
    icon: IconName;
};

type NavSection = {
    label: string;
    items: NavItem[];
};

const sections: NavSection[] = [
    {
        label: "Overview",
        items: [{ title: "Dashboard", name: "dashboard", icon: "PhChartBar" }],
    },
    {
        label: "Merchants",
        items: [
            { title: "Users", name: "users.index", icon: "PhUsers" },
            { title: "Fraud Checker", name: "frauds.index", icon: "PhUserCheck" },
        ],
    },
    {
        label: "Platform",
        items: [
            { title: "Manage Plugins", name: "plugins.index", icon: "PhPlugsConnected" },
            { title: "Package Hub", name: "packages.index", icon: "PhPackage" },
            { title: "API Keys", name: "apiKeys.index", icon: "PhLockKeyOpen" },
            { title: "Developer API", name: "developer.index", icon: "PhCode" },
        ],
    },
    {
        label: "Analytics",
        items: [
            { title: "Visitor Report", name: "visitor.index", icon: "PhChartLineUp" },
            { title: "Use Analysis", name: "useAnalysis.index", icon: "PhChartScatter" },
        ],
    },
    {
        label: "System",
        items: [
            { title: "Error Logs", name: "logs.index", icon: "PhBug" },
            { title: "Database Backups", name: "backups.index", icon: "PhFloppyDiskBack" },
        ],
    },
    {
        label: "Reference",
        items: [
            { title: "Phosphor Icons", name: "icons", icon: "PhListHeart" },
            { title: "Prime Icons", name: "icons.prime", icon: "PhPalette" },
        ],
    },
];

const isActive = (name: string) => route().current(name);
</script>
