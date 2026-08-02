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
            <p
                v-if="reorderMode"
                class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-[11px] font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200"
            >
                Drag items using the handle, then confirm to save the new order for all admins.
            </p>

            <draggable
                v-model="displaySections"
                item-key="label"
                handle=".section-drag-handle"
                :disabled="dragDisabled"
                :animation="180"
                ghost-class="opacity-40"
                class="space-y-0"
                @end="onSectionDragEnd"
            >
                <template #item="{ element: section }">
                    <div class="mb-5">
                        <div class="mb-2 flex items-center gap-1 px-1">
                            <button
                                v-if="reorderMode"
                                type="button"
                                class="section-drag-handle inline-flex h-6 w-6 shrink-0 cursor-grab items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-600 active:cursor-grabbing dark:hover:bg-slate-800"
                                aria-label="Drag section"
                            >
                                <Icon name="PhDotsSixVertical" class="text-sm" />
                            </button>
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                            >
                                {{ section.label }}
                            </p>
                        </div>

                        <draggable
                            v-model="section.items"
                            item-key="title"
                            handle=".item-drag-handle"
                            :disabled="dragDisabled"
                            :animation="180"
                            ghost-class="opacity-40"
                            class="space-y-1"
                            @end="onItemDragEnd"
                        >
                            <template #item="{ element: item }">
                                <div>
                                    <div v-if="item.children?.length" class="space-y-1">
                                        <div
                                            class="group flex w-full items-center gap-1 rounded-xl transition-all duration-200"
                                            :class="
                                                isGroupActive(item)
                                                    ? 'bg-primary-50/80 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-slate-800/80 dark:hover:text-gray-100'
                                            "
                                        >
                                            <button
                                                v-if="reorderMode"
                                                type="button"
                                                class="item-drag-handle ml-1 inline-flex h-7 w-7 shrink-0 cursor-grab items-center justify-center rounded-lg text-gray-400 hover:bg-gray-200/80 hover:text-gray-600 active:cursor-grabbing dark:hover:bg-slate-700"
                                                aria-label="Drag menu item"
                                            >
                                                <Icon name="PhDotsSixVertical" class="text-sm" />
                                            </button>
                                            <button
                                                type="button"
                                                class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2.5 text-sm font-medium"
                                                :class="reorderMode ? 'pl-1' : ''"
                                                @click="!reorderMode && toggleGroup(item.title)"
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
                                        </div>

                                        <Transition
                                            enter-active-class="transition duration-200 ease-out"
                                            enter-from-class="opacity-0 -translate-y-1 max-h-0"
                                            enter-to-class="opacity-100 translate-y-0 max-h-40"
                                            leave-active-class="transition duration-150 ease-in"
                                            leave-from-class="opacity-100 translate-y-0 max-h-40"
                                            leave-to-class="opacity-0 -translate-y-1 max-h-0"
                                        >
                                            <div
                                                v-show="expandedGroups[item.title] || reorderMode"
                                                class="ml-3 overflow-hidden rounded-xl border border-gray-100 bg-gray-50/90 p-1.5 dark:border-gray-800 dark:bg-slate-800/60"
                                            >
                                                <draggable
                                                    v-model="item.children"
                                                    item-key="name"
                                                    handle=".child-drag-handle"
                                                    :disabled="dragDisabled"
                                                    :animation="180"
                                                    ghost-class="opacity-40"
                                                    class="space-y-0.5"
                                                    @end="onChildDragEnd"
                                                >
                                                    <template #item="{ element: child }">
                                                        <div class="flex items-center gap-1">
                                                            <button
                                                                v-if="reorderMode"
                                                                type="button"
                                                                class="child-drag-handle inline-flex h-6 w-6 shrink-0 cursor-grab items-center justify-center rounded text-gray-400 hover:bg-white hover:text-gray-600 active:cursor-grabbing dark:hover:bg-slate-900"
                                                                aria-label="Drag submenu item"
                                                            >
                                                                <Icon name="PhDotsSixVertical" class="text-xs" />
                                                            </button>
                                                            <component
                                                                :is="reorderMode ? 'div' : Link"
                                                                v-bind="
                                                                    reorderMode
                                                                        ? {}
                                                                        : { href: route(child.name) }
                                                                "
                                                                class="group/sub flex min-w-0 flex-1 items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium transition-all duration-150"
                                                                :class="
                                                                    isActive(child.name)
                                                                        ? 'bg-white text-primary-700 shadow-sm ring-1 ring-primary-100 dark:bg-slate-900 dark:text-primary-300 dark:ring-primary-500/30'
                                                                        : 'text-gray-600 hover:bg-white/80 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-slate-900/60 dark:hover:text-gray-100'
                                                                "
                                                                @click="!reorderMode && $emit('close')"
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
                                                            </component>
                                                        </div>
                                                    </template>
                                                </draggable>
                                            </div>
                                        </Transition>
                                    </div>

                                    <div
                                        v-else-if="item.name"
                                        class="group flex items-center gap-1 rounded-xl transition-all duration-200"
                                        :class="
                                            isActive(item.name)
                                                ? 'bg-primary-50 text-primary-700 shadow-sm dark:bg-primary-500/15 dark:text-primary-300'
                                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-slate-800 dark:hover:text-gray-100'
                                        "
                                    >
                                        <button
                                            v-if="reorderMode"
                                            type="button"
                                            class="item-drag-handle ml-1 inline-flex h-7 w-7 shrink-0 cursor-grab items-center justify-center rounded-lg text-gray-400 hover:bg-gray-200/80 hover:text-gray-600 active:cursor-grabbing dark:hover:bg-slate-700"
                                            aria-label="Drag menu item"
                                        >
                                            <Icon name="PhDotsSixVertical" class="text-sm" />
                                        </button>
                                        <component
                                            :is="reorderMode ? 'div' : Link"
                                            v-bind="
                                                reorderMode
                                                    ? {}
                                                    : { href: route(item.name) }
                                            "
                                            class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2.5 text-sm font-medium"
                                            :class="reorderMode ? 'pl-1' : ''"
                                            @click="!reorderMode && $emit('close')"
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
                                        </component>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                    </div>
                </template>
            </draggable>
        </nav>

        <div class="sticky bottom-0 z-10 shrink-0 border-t border-gray-200/80 bg-white p-4 dark:border-gray-800 dark:bg-slate-900">
            <div
                v-if="canReorder"
                class="mb-3 flex gap-2"
            >
                <Button
                    v-if="!reorderMode"
                    type="button"
                    label="Reorder menu"
                    icon="pi pi-arrows-v"
                    severity="secondary"
                    outlined
                    size="small"
                    class="w-full"
                    @click="enterReorderMode"
                />
                <Button
                    v-else
                    type="button"
                    label="Done"
                    icon="pi pi-check"
                    severity="secondary"
                    outlined
                    size="small"
                    class="w-full"
                    :disabled="saving"
                    @click="exitReorderMode"
                />
            </div>
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
import { Link, usePage } from "@inertiajs/vue3";
import { useConfirm } from "primevue";
import Button from "primevue/button";
import { useToast } from "primevue/usetoast";
import axios from "axios";
import { computed, reactive, ref, watch } from "vue";
import draggable from "vuedraggable";
import { usePermissions } from "@/composables/usePermissions";
import type { IconName } from "@/types";

defineEmits<{
    close: [];
}>();

type NavChild = {
    title: string;
    name: string;
    icon: IconName;
    permission?: string | string[];
};

type NavItem = {
    title: string;
    name?: string;
    icon: IconName;
    permission?: string | string[];
    children?: NavChild[];
};

type NavSection = {
    label: string;
    items: NavItem[];
};

type NavOrder = {
    sections: string[];
    items: Record<string, string[]>;
    children: Record<string, string[]>;
};

const allSections: NavSection[] = [
    // Title strings must stay in sync with AdminSidebarNavOrder::catalog().
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
        label: "AI",
        items: [
            {
                title: "Wise AI",
                icon: "PhBrain",
                children: [
                    {
                        title: "Dashboard",
                        name: "wiseAi.dashboard",
                        icon: "PhGauge",
                        permission: "dashboard.view",
                    },
                    {
                        title: "Config",
                        name: "wiseAi.config",
                        icon: "PhSlidersHorizontal",
                        permission: "dashboard.view",
                    },
                    {
                        title: "Knowledge",
                        name: "wiseAi.knowledge",
                        icon: "PhBooks",
                        permission: "dashboard.view",
                    },
                    {
                        title: "Language",
                        name: "wiseAi.language",
                        icon: "PhTranslate",
                        permission: "dashboard.view",
                    },
                    {
                        title: "Playground",
                        name: "wiseAi.playground",
                        icon: "PhFlask",
                        permission: "dashboard.view",
                    },
                    {
                        title: "Learning",
                        name: "wiseAi.learning",
                        icon: "PhTray",
                        permission: "dashboard.view",
                    },
                    {
                        title: "Help",
                        name: "wiseAi.tutorials",
                        icon: "PhGraduationCap",
                        permission: "dashboard.view",
                    },
                    {
                        title: "Train",
                        name: "wiseAi.train",
                        icon: "PhUploadSimple",
                        permission: "dashboard.view",
                    },
                    {
                        title: "Intelligence",
                        name: "wiseAi.intelligence",
                        icon: "PhChartLineUp",
                        permission: "dashboard.view",
                    },
                    {
                        title: "Fleet",
                        name: "wiseAi.fleet",
                        icon: "PhBroadcast",
                        permission: "dashboard.view",
                    },
                ],
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
                icon: "PhUserCheck",
                permission: "merchants.view",
                children: [
                    {
                        title: "Phone Check",
                        name: "frauds.index",
                        icon: "PhMagnifyingGlass",
                        permission: "merchants.view",
                    },
                    {
                        title: "Partner Credentials",
                        name: "frauds.credentials",
                        icon: "PhKey",
                        permission: "merchants.view",
                    },
                    {
                        title: "Token & CURL",
                        name: "frauds.expire",
                        icon: "PhGear",
                        permission: "merchants.view",
                    },
                ],
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
                title: "Settings",
                name: "landingSettings.index",
                icon: "PhGearSix",
                permission: "billing.manage",
            },
            {
                title: "Tutorials",
                name: "tutorials.index",
                icon: "PhYoutubeLogo",
                permission: "billing.manage",
            },
            {
                title: "Media Library",
                name: "mediaLibrary.index",
                icon: "PhImages",
                permission: "billing.manage",
            },
            {
                title: "Blog Posts",
                icon: "PhNewspaper",
                permission: "billing.manage",
                children: [
                    {
                        title: "All Posts",
                        name: "blogPosts.index",
                        icon: "PhListBullets",
                        permission: "billing.manage",
                    },
                    {
                        title: "Blog AI",
                        name: "blogPosts.ai",
                        icon: "PhSparkle",
                        permission: "billing.manage",
                    },
                    {
                        title: "AI Settings",
                        name: "blogPosts.settings",
                        icon: "PhGearSix",
                        permission: "billing.manage",
                    },
                    {
                        title: "Topic Clusters",
                        name: "blogPosts.clusters.index",
                        icon: "PhTreeStructure",
                        permission: "billing.manage",
                    },
                    {
                        title: "SEO & Learning",
                        name: "blogPosts.seo",
                        icon: "PhChartLineUp",
                        permission: ["roles.manage", "billing.manage"],
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
        label: "Marketing",
        items: [
            {
                title: "Meta Pixel",
                name: "marketingSettings.index",
                icon: "PhMetaLogo",
                permission: "billing.manage",
            },
        ],
    },
    {
        label: "Analytics",
        items: [
            {
                title: "Visitors",
                name: "siteVisitors.index",
                icon: "PhUsers",
                permission: "dashboard.view",
            },
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
                title: "Database Migrations",
                name: "migrations.index",
                icon: "PhStack",
                permission: "roles.manage",
            },
            {
                title: "System Maintenance",
                name: "maintenance.index",
                icon: "PhWrench",
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
const confirm = useConfirm();
const toast = useToast();
const page = usePage();

const canReorder = computed(() => can("roles.manage"));
const reorderMode = ref(false);
const saving = ref(false);
const confirmOpen = ref(false);
const draftSections = ref<NavSection[]>([]);
const snapshotBeforeDrag = ref<NavSection[]>([]);
const dragDisabled = computed(
    () => !reorderMode.value || confirmOpen.value || saving.value,
);

const navOrder = ref<NavOrder | null>(
    (page.props.admin_sidebar_nav_order as NavOrder | null | undefined) ?? null,
);

watch(
    () => page.props.admin_sidebar_nav_order,
    (value) => {
        navOrder.value = (value as NavOrder | null | undefined) ?? null;
    },
);

const canSee = (permission?: string | string[]) => {
    if (!permission) return true;
    if (Array.isArray(permission)) {
        return permission.some((p) => can(p));
    }
    return can(permission);
};

const filterItem = (item: NavItem): NavItem | null => {
    if (item.children?.length) {
        const children = item.children.filter((child) => canSee(child.permission));

        return children.length ? { ...item, children } : null;
    }

    return canSee(item.permission) ? item : null;
};

const sortByTitles = <T,>(items: T[], titles: string[] | undefined, getTitle: (item: T) => string): T[] => {
    if (!titles?.length) {
        return items;
    }

    const rank = new Map(titles.map((title, index) => [title, index]));

    return [...items].sort((a, b) => {
        const aTitle = getTitle(a);
        const bTitle = getTitle(b);
        const aRank = rank.has(aTitle) ? (rank.get(aTitle) as number) : Number.MAX_SAFE_INTEGER;
        const bRank = rank.has(bTitle) ? (rank.get(bTitle) as number) : Number.MAX_SAFE_INTEGER;

        if (aRank === bRank) {
            return 0;
        }

        return aRank - bRank;
    });
};

const applyOrder = (sections: NavSection[], order: NavOrder | null): NavSection[] => {
    if (!order) {
        return sections.map((section) => ({
            ...section,
            items: section.items.map((item) => ({
                ...item,
                children: item.children ? [...item.children] : undefined,
            })),
        }));
    }

    const orderedSections = sortByTitles(sections, order.sections, (section) => section.label);

    return orderedSections.map((section) => ({
        ...section,
        items: sortByTitles(section.items, order.items?.[section.label], (item) => item.title).map(
            (item) => ({
                ...item,
                children: item.children
                    ? sortByTitles(item.children, order.children?.[item.title], (child) => child.title)
                    : undefined,
            }),
        ),
    }));
};

const orderedAndFiltered = computed(() =>
    applyOrder(allSections, navOrder.value)
        .map((section) => ({
            ...section,
            items: section.items
                .map(filterItem)
                .filter((item): item is NavItem => item !== null),
        }))
        .filter((section) => section.items.length > 0),
);

const displaySections = computed({
    get: () => (reorderMode.value ? draftSections.value : orderedAndFiltered.value),
    set: (value: NavSection[]) => {
        if (reorderMode.value) {
            draftSections.value = value;
        }
    },
});

const cloneSections = (sections: NavSection[]): NavSection[] =>
    sections.map((section) => ({
        ...section,
        items: section.items.map((item) => ({
            ...item,
            children: item.children ? item.children.map((child) => ({ ...child })) : undefined,
        })),
    }));

const buildOrderPayload = (sections: NavSection[]): NavOrder => {
    const items: Record<string, string[]> = {};
    const children: Record<string, string[]> = {};

    for (const section of sections) {
        items[section.label] = section.items.map((item) => item.title);

        for (const item of section.items) {
            if (item.children?.length) {
                children[item.title] = item.children.map((child) => child.title);
            }
        }
    }

    return {
        sections: sections.map((section) => section.label),
        items,
        children,
    };
};

const enterReorderMode = () => {
    draftSections.value = cloneSections(orderedAndFiltered.value);
    snapshotBeforeDrag.value = cloneSections(draftSections.value);
    reorderMode.value = true;
};

const exitReorderMode = () => {
    if (saving.value || confirmOpen.value) {
        return;
    }

    reorderMode.value = false;
    draftSections.value = [];
    snapshotBeforeDrag.value = [];
};

const revertDraft = () => {
    draftSections.value = cloneSections(snapshotBeforeDrag.value);
};

const promptSaveOrder = () => {
    if (confirmOpen.value || saving.value) {
        return;
    }

    const nextOrder = buildOrderPayload(draftSections.value);
    const prevOrder = buildOrderPayload(snapshotBeforeDrag.value);

    if (JSON.stringify(nextOrder) === JSON.stringify(prevOrder)) {
        return;
    }

    confirmOpen.value = true;
    let accepted = false;

    confirm.require({
        header: "Save menu order?",
        message: "This updates the sidebar order for all platform admins.",
        icon: "pi pi-arrows-v",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Save",
            severity: "success",
            size: "small",
        },
        accept: () => {
            accepted = true;
            confirmOpen.value = false;
            void saveOrder(nextOrder);
        },
        reject: () => {
            revertDraft();
            confirmOpen.value = false;
        },
        onHide: () => {
            // Esc / overlay dismiss — treat as cancel unless Save was chosen.
            if (!accepted && !saving.value) {
                revertDraft();
            }
            confirmOpen.value = false;
        },
    });
};

const saveOrder = async (order: NavOrder) => {
    saving.value = true;

    try {
        const { data } = await axios.put(route("sidebarNavOrder.update"), order);
        navOrder.value = (data.order as NavOrder) ?? order;
        draftSections.value = cloneSections(
            applyOrder(allSections, navOrder.value)
                .map((section) => ({
                    ...section,
                    items: section.items
                        .map(filterItem)
                        .filter((item): item is NavItem => item !== null),
                }))
                .filter((section) => section.items.length > 0),
        );
        snapshotBeforeDrag.value = cloneSections(draftSections.value);
        toast.add({
            severity: "success",
            summary: "Menu order saved",
            detail: "Sidebar order updated for all admins.",
            life: 3000,
            group: "br",
        });
    } catch {
        revertDraft();
        toast.add({
            severity: "error",
            summary: "Could not save menu order",
            detail: "Your previous order was restored. Please try again.",
            life: 4500,
            group: "br",
        });
    } finally {
        saving.value = false;
        confirmOpen.value = false;
    }
};

const onSectionDragEnd = () => {
    promptSaveOrder();
};

const onItemDragEnd = () => {
    // Nested v-model mutates section.items in place; sync draft array reference.
    draftSections.value = cloneSections(draftSections.value);
    promptSaveOrder();
};

const onChildDragEnd = () => {
    draftSections.value = cloneSections(draftSections.value);
    promptSaveOrder();
};

const expandedGroups = reactive<Record<string, boolean>>({
    "Wise AI": Boolean(route().current("wiseAi.*")),
    Merchants: Boolean(route().current("users.*")),
    "Plans & Billing": Boolean(
        route().current("packages.*")
            || route().current("orders.*")
            || route().current("packagePayments.*")
            || route().current("customerNotices.*"),
    ),
    "Fraud Checker": Boolean(route().current("frauds.*")),
    "Blog Posts": Boolean(route().current("blogPosts.*")),
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
