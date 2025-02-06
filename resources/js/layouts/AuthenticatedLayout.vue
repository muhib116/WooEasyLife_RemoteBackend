<template>
    <Head :title="title" />

    <div
        class="flex h-svh flex-col bg-[#f3f4f6] dark:bg-gray-800 dark:text-white"
    >
        <div
            class="flex h-[60px] flex-shrink-0 items-center justify-between bg-white dark:border-gray-600 dark:bg-slate-800"
        >
            <div
                class="w-[240px] cursor-pointer px-4 text-xl font-semibold"
            >
                WooEasyLife
            </div>
            <div class="flex items-center gap-5 px-4">
                <button @click="isDarkMode = !isDarkMode">
                    <Icon :name="isDarkMode ? 'PhSun' : 'PhMoonStars'" />
                </button>
                <button @click="themeDialog = true">
                    <Icon name="PhPalette" />
                </button>
            </div>
        </div>
        <div class="flex flex-1">
            <div
                class="h-[calc(100svh-60px)] w-[240px] overflow-auto bg-white py-8 dark:bg-slate-800"
            >
                <LeftSidebar />
            </div>
            <div class="h-[calc(100svh-60px)] flex-1 overflow-auto px-6 py-6">
                <slot></slot>
            </div>
        </div>
        <Dialog
            v-model:visible="themeDialog"
            modal
            closeOnEscape
            blockScroll
            draggable
            dismissableMask
            header="Primary Color"
            :style="{ width: '25rem' }"
        >
            <div class="mt-5 flex flex-wrap items-center justify-center gap-5">
                <div v-for="(color, name) in colors" :key="name">
                    <button
                        class="h-9 w-9 cursor-pointer select-none rounded border hover:scale-105"
                        :class="{
                            'ring-2 ring-blue-500 ring-offset-2':
                                primaryTheme == name,
                        }"
                        :style="{
                            backgroundColor: get(color, '500'),
                        }"
                        @click="changePrimaryColor(name)"
                        :title="name"
                    ></button>
                </div>
            </div>
        </Dialog>
        <Toast position="bottom-right" group="br" />
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import LeftSidebar from "./fragments/LeftSidebar.vue";
import { Head, usePage } from "@inertiajs/vue3";
import { useLayout, useTheme } from "@/composable";
import { ref, watch } from "vue";
import { get } from "lodash";
import { useToast } from "primevue/usetoast";

withDefaults(
    defineProps<{
        title?: string;
        skipWrapper?: boolean;
    }>(),
    {
        skipWrapper: false,
    },
);

const toast = useToast();
const page = usePage();
let timeout;
watch(
    page,
    () => {
        const props = usePage().props;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            // @ts-ignore
            if (props.flash?.success) {
                let data = {
                    summary: "Success",
                    severity: "success",
                    // @ts-ignore
                    detail: props.flash?.success,
                    // detail: "Message Content",
                    life: 3000,
                    group: "br",
                };
                if (typeof props.flash?.success == "object") {
                    data.detail = props.flash?.success?.message;
                    data.detail = props.flash?.success?.detail;
                }
                toast.add(data);
            }
            // @ts-ignore
            if (props.flash?.error) {
                toast.add({
                    severity: "error",
                    summary: "Error",
                    // @ts-ignore
                    detail: props.flash?.error,
                    // detail: "Message Content",
                    life: 3000,
                });
            }
        }, 100);
    },
    { deep: true },
);

const themeDialog = ref(false);
const { showLeftSidebar } = useLayout();
const { isDarkMode, colors, primaryTheme, changePrimaryColor } = useTheme();
</script>
