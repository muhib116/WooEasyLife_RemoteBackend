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
    user: { id: number };
}>();

const menus = computed(
    (): { title: string; url: string; isActive: boolean; icon: IconName }[] => [
        {
            title: "Overview",
            url: route("users.view", props.user.id),
            isActive: route().current("users.view"),
            icon: "PhSquaresFour",
        },
        {
            title: "API Keys",
            url: route("users.apiKeys", props.user.id),
            isActive: route().current("users.apiKeys"),
            icon: "PhKey",
        },
        {
            title: "Packages",
            url: route("users.packages", props.user.id),
            isActive: route().current("users.packages"),
            icon: "PhPackage",
        },
        {
            title: "Orders",
            url: route("users.packagesOrders", props.user.id),
            isActive: route().current("users.packagesOrders"),
            icon: "PhShoppingCart",
        },
        {
            title: "SMS Recharge",
            url: route("users.smsRecharge", props.user.id),
            isActive: route().current("users.smsRecharge"),
            icon: "PhWallet",
        },
        {
            title: "SMS History",
            url: route("users.smsUseHistory", props.user.id),
            isActive: route().current("users.smsUseHistory"),
            icon: "PhChatCircleText",
        },
    ],
);
</script>
