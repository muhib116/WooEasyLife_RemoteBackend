<template>
    <div
        class="box-bg box-border rounded-2xl border p-2 shadow-sm dark:border-gray-700"
    >
        <div
            class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between"
        >
            <div
                class="flex gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1 dark:bg-slate-900/60"
            >
                <Link
                    v-for="menu in menus"
                    :key="menu.title"
                    :href="menu.url"
                    class="whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition-all"
                    :class="
                        menu.isActive
                            ? 'bg-white text-primary-600 shadow-sm dark:bg-slate-800 dark:text-primary-400'
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'
                    "
                >
                    <span class="flex items-center gap-2">
                        <Icon :name="menu.icon" class="text-base" />
                        {{ menu.title }}
                        <span
                            v-if="menu.count !== null && menu.count !== undefined"
                            class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-xs font-semibold"
                            :class="
                                menu.isActive
                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300'
                                    : 'bg-slate-200 text-gray-600 dark:bg-slate-700 dark:text-gray-300'
                            "
                        >
                            {{ menu.count }}
                        </span>
                    </span>
                </Link>
            </div>
            <div
                v-if="$slots.default"
                class="flex shrink-0 items-center justify-end gap-2 px-1"
            >
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";
import type { IconName } from "@/types";

const props = defineProps<{
    user: {
        id: number;
        websites_count?: number;
        merchant_employees_count?: number;
    };
}>();

const menus = computed(
    (): {
        title: string;
        url: string;
        isActive: boolean;
        icon: IconName;
        count?: number | null;
    }[] => [
        {
            title: "Overview",
            url: route("users.view", props.user.id),
            isActive: route().current("users.view"),
            icon: "PhSquaresFour",
        },
        {
            title: "Websites",
            url: route("users.websites", props.user.id),
            isActive:
                route().current("users.websites") ||
                route().current("users.packages") ||
                route().current("users.apiKeys"),
            icon: "PhGlobe",
            count: props.user.websites_count ?? null,
        },
        {
            title: "Usage",
            url: route("users.packagesOrders", props.user.id),
            isActive: route().current("users.packagesOrders"),
            icon: "PhShoppingCart",
        },
        {
            title: "SMS",
            url: route("users.sms", props.user.id),
            isActive:
                route().current("users.sms") ||
                route().current("users.smsRecharge") ||
                route().current("users.smsUseHistory"),
            icon: "PhWallet",
        },
        {
            title: "Billing",
            url: route("users.billing", props.user.id),
            isActive: route().current("users.billing"),
            icon: "PhCreditCard",
        },
        {
            title: "Employees",
            url: route("users.employees", props.user.id),
            isActive: route().current("users.employees"),
            icon: "PhUsersThree",
            count: props.user.merchant_employees_count ?? null,
        },
    ],
);
</script>
