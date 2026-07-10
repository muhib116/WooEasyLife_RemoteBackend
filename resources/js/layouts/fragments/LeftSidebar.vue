<template>
    <aside class="flex h-svh flex-col overflow-hidden">
        <div class="sticky top-0 z-10 flex h-16 shrink-0 items-center gap-3 border-b border-gray-200/80 bg-white px-5 dark:border-gray-800 dark:bg-slate-900">
            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl">
                <img
                    src="/app-logo"
                    alt="WooEasyLife"
                    class="h-full w-full object-cover"
                />
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-gray-900 dark:text-white">
                    WooEasyLife Platform
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

        <nav class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 py-4 admin-scrollbar">
            <div v-for="section in sections" :key="section.label" class="mb-5">
                <p
                    class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                >
                    {{ section.label }}
                </p>
                <ul class="space-y-1">
                    <li v-for="item in section.items" :key="item.title">
                        <div v-if="item.children?.length" class="space-y-1">
                            <button
                                type="button"
                                class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200"
                                :class="
                                    isGroupActive(item)
                                        ? 'bg-primary-50/80 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-slate-800/80 dark:hover:text-gray-100'
                                "
                                @click="toggleGroup(item.title)"
                            >
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors"
                                    :class="
                                        isGroupActive(item)
                                            ? 'bg-primary-100 text-primary-600 dark:bg-primary-500/25 dark:text-primary-300'
                                            : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200 dark:bg-slate-800 dark:text-gray-400'
                                    "
                                >
                                    <Icon :name="item.icon" class="text-lg" />
                                </span>
                                <span class="truncate">{{ item.title }}</span>
                                <span
                                    class="ml-auto flex h-6 w-6 items-center justify-center rounded-md transition-colors"
                                    :class="
                                        isGroupActive(item)
                                            ? 'bg-primary-100/80 text-primary-600 dark:bg-primary-500/20 dark:text-primary-300'
                                            : 'text-gray-400 group-hover:bg-gray-100 group-hover:text-gray-600 dark:group-hover:bg-slate-800'
                                    "
                                >
                                    <Icon
                                        name="PhCaretDown"
                                        class="text-sm transition-transform duration-200"
                                        :class="expandedGroups[item.title] ? 'rotate-180' : ''"
                                    />
                                </span>
                            </button>

                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 -translate-y-1 max-h-0"
                                enter-to-class="opacity-100 translate-y-0 max-h-40"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="opacity-100 translate-y-0 max-h-40"
                                leave-to-class="opacity-0 -translate-y-1 max-h-0"
                            >
                                <ul
                                    v-show="expandedGroups[item.title]"
                                    class="ml-3 space-y-0.5 overflow-hidden rounded-xl border border-gray-100 bg-gray-50/90 p-1.5 dark:border-gray-800 dark:bg-slate-800/60"
                                >
                                    <li v-for="child in item.children" :key="child.name">
                                        <Link
                                            :href="route(child.name)"
                                            class="group/sub flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium transition-all duration-150"
                                            :class="
                                                isActive(child.name)
                                                    ? 'bg-white text-primary-700 shadow-sm ring-1 ring-primary-100 dark:bg-slate-900 dark:text-primary-300 dark:ring-primary-500/30'
                                                    : 'text-gray-600 hover:bg-white/80 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-slate-900/60 dark:hover:text-gray-100'
                                            "
                                            @click="$emit('close')"
                                        >
                                            <span
                                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md transition-colors"
                                                :class="
                                                    isActive(child.name)
                                                        ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/20 dark:text-primary-300'
                                                        : 'bg-white text-gray-400 group-hover/sub:text-gray-600 dark:bg-slate-900/50 dark:text-gray-500 dark:group-hover/sub:text-gray-300'
                                                "
                                            >
                                                <Icon :name="child.icon" class="text-base" />
                                            </span>
                                            <span class="min-w-0 flex-1 truncate">
                                                {{ child.title }}
                                            </span>
                                            <span
                                                v-if="isActive(child.name)"
                                                class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500"
                                            />
                                        </Link>
                                    </li>
                                </ul>
                            </Transition>
                        </div>
                        <Link
                            v-else-if="item.name"
                            :href="route(item.name)"
                            class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200"
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

        <div class="sticky bottom-0 z-10 shrink-0 border-t border-gray-200/80 bg-white p-4 dark:border-gray-800 dark:bg-slate-900">
            <div
                class="rounded-xl bg-gradient-to-br from-primary-500/10 to-primary-600/5 p-3 dark:from-primary-500/20 dark:to-transparent"
            >
                <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                    WooEasyLife Platform
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
import { computed, reactive } from "vue";
import { usePermissions } from "@/composables/usePermissions";
import type { IconName } from "@/types";

defineEmits<{
    close: [];
}>();

type NavChild = {
    title: string;
    name: string;
    icon: IconName;
    permission?: string;
};

type NavItem = {
    title: string;
    name?: string;
    icon: IconName;
    permission?: string;
    children?: NavChild[];
};

type NavSection = {
    label: string;
    items: NavItem[];
};

const allSections: NavSection[] = [
    {
        label: "Overview",
        items: [
            {
                title: "Dashboard",
                name: "dashboard",
                icon: "PhChartBar",
                permission: "dashboard.view",
            },
        ],
    },
    {
        label: "Merchants",
        items: [
            {
                title: "Merchants",
                icon: "PhUsers",
                children: [
                    {
                        title: "All Merchants",
                        name: "users.index",
                        icon: "PhUsersThree",
                        permission: "merchants.view",
                    },
                    {
                        title: "Trashed Merchants",
                        name: "users.trashed",
                        icon: "PhTrash",
                        permission: "merchants.manage",
                    },
                ],
            },
            {
                title: "Fraud Checker",
                name: "frauds.index",
                icon: "PhUserCheck",
                permission: "merchants.view",
            },
            {
                title: "Fraud Package Test",
                name: "fraudPackageTest.index",
                icon: "PhFlask",
                permission: "dashboard.view",
            },
            {
                title: "Whitelisted Domains",
                name: "whitelistedDomains.index",
                icon: "PhGlobe",
                permission: "merchants.manage",
            },
        ],
    },
    {
        label: "Platform",
        items: [
            {
                title: "Plugin Versions",
                name: "plugins.index",
                icon: "PhPlugsConnected",
                permission: "licenses.manage",
            },
            {
                title: "Plans & Billing",
                icon: "PhCurrencyCircleDollar",
                children: [
                    {
                        title: "Pricing Plans",
                        name: "packages.index",
                        icon: "PhPackage",
                        permission: "billing.view",
                    },
                    {
                        title: "Landing Orders",
                        name: "orders.index",
                        icon: "PhShoppingCart",
                        permission: "payments.view",
                    },
                    {
                        title: "Payment Requests",
                        name: "packagePayments.index",
                        icon: "PhCreditCard",
                        permission: "payments.view",
                    },
                    {
                        title: "Customer Notices",
                        name: "customerNotices.index",
                        icon: "PhMegaphone",
                        permission: "billing.manage",
                    },
                ],
            },
            {
                title: "Subscription Alerts",
                name: "subscriptionAlerts.index",
                icon: "PhBellRinging",
                permission: "billing.view",
            },
        ],
    },
    {
        label: "Analytics",
        items: [
            {
                title: "Visitor Report",
                name: "visitor.index",
                icon: "PhChartLineUp",
                permission: "dashboard.view",
            },
            {
                title: "Use Analysis",
                name: "useAnalysis.index",
                icon: "PhChartScatter",
                permission: "dashboard.view",
            },
            {
                title: "Order Intelligence",
                name: "orderIntelligence.index",
                icon: "PhBrain",
                permission: "dashboard.view",
            },
        ],
    },
    {
        label: "System",
        items: [
            {
                title: "Webhook Activities",
                name: "webhooks.index",
                icon: "PhArrowClockwise",
                permission: "roles.manage",
            },
            {
                title: "Error Logs",
                name: "logs.index",
                icon: "PhBug",
                permission: "roles.manage",
            },
            {
                title: "Roles & Access",
                name: "roles.index",
                icon: "PhShieldCheck",
                permission: "roles.manage",
            },
            {
                title: "Database Backups",
                name: "backups.index",
                icon: "PhFloppyDiskBack",
                permission: "roles.manage",
            },
            {
                title: "Developer API",
                name: "developer.index",
                icon: "PhCode",
                permission: "roles.manage",
            },
        ],
    },
];

const { can } = usePermissions();

const canSee = (permission?: string) => !permission || can(permission);

const filterItem = (item: NavItem): NavItem | null => {
    if (item.children?.length) {
        const children = item.children.filter((child) => canSee(child.permission));

        return children.length ? { ...item, children } : null;
    }

    return canSee(item.permission) ? item : null;
};

const sections = computed(() =>
    allSections
        .map((section) => ({
            ...section,
            items: section.items
                .map(filterItem)
                .filter((item): item is NavItem => item !== null),
        }))
        .filter((section) => section.items.length > 0),
);

const expandedGroups = reactive<Record<string, boolean>>({
    Merchants: Boolean(route().current("users.*")),
    "Plans & Billing": Boolean(
        route().current("packages.*")
            || route().current("orders.*")
            || route().current("packagePayments.*")
            || route().current("customerNotices.*"),
    ),
});

const toggleGroup = (title: string) => {
    expandedGroups[title] = !expandedGroups[title];
};

const isActive = (name: string) => route().current(name);

const isGroupActive = (item: NavItem) => {
    if (!item.children?.length) {
        return item.name ? isActive(item.name) : false;
    }

    return item.children.some((child) => isActive(child.name));
};
</script>
