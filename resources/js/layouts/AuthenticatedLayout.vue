<template>
    <Head :title="title" />

    <div
        class="h-svh flex flex-col bg-[#f3f4f6] dark:bg-gray-800 dark:text-white"
    >
        <div
            class="bg-white flex-shrink-0 dark:bg-gray-700 border-b border-gray-300 dark:border-gray-600 px-4 h-[60px] flex items-center justify-between"
        >
            <div class="text-xl font-semibold">Natural Care</div>
            <div class="flex items-center gap-5">
                <button @click="isDarkMode = !isDarkMode">
                    <Icon :name="isDarkMode ? 'PhSun' : 'PhMoonStars'" />
                </button>
                <button @click="themeDialog=true">
                    <Icon name="PhPalette" />
                </button>
            </div>
        </div>
        <div class="flex-1 flex">
            <div class="w-[240px] h-[calc(100svh-60px)] overflow-auto py-8">
                <LeftSidebar />
            </div>
            <div class="flex-1 py-6 h-[calc(100svh-60px)] overflow-auto px-6">
                <slot></slot>
            </div>
        </div>
        <Dialog 
            v-model:visible="themeDialog" 
            modal 
            closeOnEscape
            blockScroll
            draggable
            header="Primary Color" 
            :style="{ width: '25rem' }"
        >
            <div class="flex justify-center items-center mt-5 flex-wrap gap-5">
                <div
                    v-for="(color, name) in colors"
                    :key="name"
                >
                    <button 
                        class="border w-9 h-9 rounded select-none cursor-pointer hover:scale-105"
                        :class="{
                            'ring-2 ring-blue-500 ring-offset-2': primaryTheme == name
                        }"
                        :style="{
                            backgroundColor: get(color, '600')
                        }"
                        @click="changePrimaryColor(name)"
                    >
                    </button>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import LeftSidebar from "./fragments/LeftSidebar.vue";
import { Head } from "@inertiajs/vue3";
import { useLayout, useTheme } from "@/composable";
import { ref } from 'vue'
import { get } from 'lodash'

withDefaults(
    defineProps<{
        title?: string;
        skipWrapper?: boolean;
    }>(),
    {
        skipWrapper: false,
    }
);

const themeDialog = ref(false)
const { showLeftSidebar } = useLayout();
const { isDarkMode, colors, primaryTheme, changePrimaryColor } = useTheme();
</script>
