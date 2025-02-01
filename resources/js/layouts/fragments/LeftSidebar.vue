<template>
    <div class="px-2">
        <ul class="space-y-2 font-medium">
            <li v-for="(_route, index) in routes" :index="index">
                <Link
                    :href="route(_route.name)"
                    class="group flex items-center rounded-lg p-2 text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                    :class="{
                        'bg-gray-100 text-black dark:!bg-gray-900':
                            route().current(_route.name),
                    }"
                >
                    <Icon :name="_route.icon" />
                    <span class="ms-3">
                        {{ _route.title }}
                    </span>
                </Link>
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import { IconName } from "@/types";

type SidebarRoutes = {
    title: string;
    name: string;
    icon: IconName;
};

const routes: SidebarRoutes[] = [
    {
        title: "Dashboard",
        name: "dashboard",
        icon: "PhChartBar",
    },
    {
        title: "Users",
        name: "users.index",
        icon: "PhUsers",
    },
    {
        title: "Fraud Checker",
        name: "frauds.index",
        icon: "PhUserCheck",
    },
    {
        title: "Manage Plugins",
        name: "plugins.index",
        icon: "PhPlugsConnected",
    },
    {
        title: "Package Hub",
        name: "packages.index",
        icon: "PhPackage",
    },
    {
        title: "Api Keys",
        name: "apiKeys.index",
        icon: "PhLockKeyOpen",
    },
    {
        title: "Products",
        name: "products.index",
        icon: "PhBookmarks",
    },
    {
        title: "Error Logs",
        name: "logs.index",
        icon: "PhBug",
    },
    {
        title: "Phosphor Icons",
        name: "icons",
        icon: "PhListHeart",
    },
    {
        title: "Prime Icons",
        name: "icons.prime",
        icon: "PhListHeart",
    },
];
</script>
