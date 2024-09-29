<template>
    <Head :title="title" />

    <div
        class="h-svh flex flex-col bg-[#f3f4f6] dark:bg-gray-800 dark:text-white"
    >
        <div
            class="bg-white dark:bg-gray-700 border-b border-gray-300 dark:border-gray-600 px-4 h-[80px] flex items-center justify-between"
        >
            <div class="text-2xl font-semibold">Natural Care</div>
            <div>
                <button @click="isDarkMode = !isDarkMode">
                    <Icon :name="isDarkMode ? 'PhSun' : 'PhMoonStars'" />
                </button>
            </div>
        </div>
        <div class="flex-1 flex">
            <div class="w-[240px] py-8">
                <LeftSidebar />
            </div>
            <div class="flex-1 py-6 px-6">
                <slot></slot>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import LeftSidebar from "./fragments/LeftSidebar.vue";
import { Head } from "@inertiajs/vue3";
import { useLayout, useTheme } from "@/composable";

withDefaults(
    defineProps<{
        title?: string;
        skipWrapper?: boolean;
    }>(),
    {
        skipWrapper: false,
    }
);

const { showLeftSidebar } = useLayout();
const { isDarkMode } = useTheme();
</script>
