<template>
    <div
        class="flex justify-between gap-3 border-b border-gray-100 pb-2 dark:border-gray-700"
    >
        <div class="flex flex-1 flex-wrap gap-3">
            <Link
                v-for="(menu, index) in menus"
                :key="index"
                :href="menu.url"
                class="rounded border border-gray-100 px-4 py-1 hover:bg-primary-500 hover:text-white dark:border-gray-700"
                :class="{
                    'bg-primary-500 text-white': menu.isActive,
                }"
            >
                {{ menu.title }}
            </Link>
        </div>
        <div>
            <slot></slot>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps<{
    user: any;
}>();

const menus = ref([
    {
        title: "Overview",
        url: route("users.view", props.user.id),
        isActive: route().current("users.view"),
    },
    {
        title: "Api Keys",
        url: route("users.apiKeys", props.user.id),
        isActive: route().current("users.apiKeys"),
    },
    {
        title: "Packages",
        url: route("users.packages", props.user.id),
        isActive: route().current("users.packages"),
    },
    {
        title: "SMS Balance",
        url: route("users.smsRecharge", props.user.id),
        isActive: route().current("users.smsRecharge"),
    },
    {
        title: "SMS Use History",
        url: route("users.smsUseHistory", props.user.id),
        isActive: route().current("users.smsUseHistory"),
    },
]);
</script>
