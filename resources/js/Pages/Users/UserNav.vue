<template>
    <div
        class="box-bg box-border rounded-2xl border p-2 shadow-sm dark:border-gray-700"
    >
        <div
            class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between"
        >
            <div
                class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 sm:flex sm:flex-wrap dark:bg-slate-900/60 [&>*:last-child:nth-child(odd)]:col-span-2 sm:[&>*]:col-span-auto"
            >
                <Link
                    v-for="menu in menus"
                    :key="menu.title"
                    :href="menu.url"
                    class="flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-all sm:justify-start sm:px-3.5"
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
                class="flex w-full flex-col gap-2 px-1 sm:w-auto sm:flex-row sm:items-center sm:justify-end [&_.p-button]:min-h-11 [&_.p-button]:w-full sm:[&_.p-button]:w-auto"
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
