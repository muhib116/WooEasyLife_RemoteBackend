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
                    ? 'bg-violet-600 text-white shadow-sm'
                    : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-slate-800'
            "
        >
            <Icon :name="item.icon" class="text-lg" />
            {{ item.label }}
        </Link>
    </div>
</template>

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { Icon } from "@/plugins";
import type { IconName } from "@/types";

const items: { label: string; name: string; icon: IconName }[] = [
    { label: "Dashboard", name: "orderIntelligence.index", icon: "PhGauge" },
    { label: "Customers", name: "orderIntelligence.customers", icon: "PhUsers" },
    { label: "Orders", name: "orderIntelligence.orders", icon: "PhPackage" },
    { label: "Database Records", name: "orderIntelligence.records", icon: "PhDatabase" },
    { label: "API & Integration", name: "orderIntelligence.apiDocs", icon: "PhBookOpen" },
];
</script>
