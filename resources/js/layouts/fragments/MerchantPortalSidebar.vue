<template>
    <aside class="flex h-svh flex-col overflow-hidden">
        <div class="sticky top-0 z-10 flex h-16 shrink-0 items-center gap-3 border-b border-gray-200/80 bg-white px-5 dark:border-gray-800 dark:bg-slate-900">
            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-primary-50 dark:bg-primary-500/15">
                <div class="flex h-full w-full items-center justify-center">
                    <Icon name="PhStorefront" class="text-lg text-primary-600 dark:text-primary-400" />
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-gray-900 dark:text-white">
                    Merchant Portal
                </p>
                <p class="truncate text-[10px] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    {{ portal?.merchant_name || "Your account" }}
                </p>
            </div>
        </div>

        <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-4 admin-scrollbar">
            <ul class="space-y-1">
                <li v-for="item in navItems" :key="item.name">
                    <Link
                        :href="route(item.name)"
                        class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all"
                        :class="
                            route().current(item.name)
                                ? 'bg-primary-50/80 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-slate-800/80 dark:hover:text-gray-100'
                        "
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                            :class="
                                route().current(item.name)
                                    ? 'bg-primary-100 text-primary-600 dark:bg-primary-500/25 dark:text-primary-300'
                                    : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200 dark:bg-slate-800 dark:text-gray-400'
                            "
                        >
                            <Icon :name="item.icon" class="text-lg" />
                        </span>
                        <span>{{ item.title }}</span>
                    </Link>
                </li>
            </ul>
        </nav>
    </aside>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { usePermissions } from "@/composables/usePermissions";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import type { IconName } from "@/types";

const page = usePage();
const { can } = usePermissions();

const portal = computed(() => (page.props.auth as any)?.portal);

const navItems = computed(() => {
    const items: Array<{ title: string; name: string; icon: IconName }> = [
        { title: "Dashboard", name: "portal.dashboard", icon: "PhChartBar" },
    ];

    if (can("websites.view")) {
        items.push({ title: "Websites", name: "portal.websites", icon: "PhGlobe" });
    }

    if (can("billing.view")) {
        items.push({ title: "Billing", name: "portal.billing", icon: "PhCreditCard" });
    }

    if (can("employees.view")) {
        items.push({ title: "Team", name: "portal.employees", icon: "PhUsersThree" });
    }

    return items;
});
</script>
