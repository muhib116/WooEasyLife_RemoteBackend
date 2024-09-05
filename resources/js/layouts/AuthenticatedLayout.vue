<template>
    <Head
        :title="title"
    />
    <div class="flex h-dvh w-full bg-slate-100 dark:text-white dark:bg-slate-800 overflow-hidden">
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="showLeftSidebar"
                class="w-[300px] relative flex-shrink-0 flex flex-col bg-white dark:bg-slate-800 border-r"
                :class="{
                    'max-lg:absolute z-50 h-full': showLeftSidebar
                }"
            >
                <div
                    class="border-b relative h-[60px] flex items-center justify-center font-black text-xl uppercase"
                >
                    Natural Care
                    <div class="h-full flex items-center">
                        <button
                            @click="showLeftSidebar=false"
                            class="max-lg:flex hidden justify-center items-center absolute right-2 rounded-full h-9 w-9 bg-red-200 text-red-500"
                        >
                            <Icon
                                name="PhX"
                                size="20"
                            />
                        </button>
                    </div>
                </div>
                <div class="py-2 flex-1 overflow-y-auto">
                    <LeftSidebar />
                </div>
            </div>
        </Transition>
        <main class="flex-1 h-dvh">
            <div class="h-[60px] px-4 flex justify-between items-center sticky top-0 bg-white dark:text-white dark:bg-slate-800 border-b">
                <div>
                    <button
                        @click="showLeftSidebar=!showLeftSidebar"
                        class="w-10 h-10 rounded grid place-content-center hover:!bg-opacity-30 !bg-opacity-10 bg-slate-900 dark:bg-white"
                    >
                        <Icon 
                            name="PhSidebar"
                        />
                    </button>
                </div>
                <div>
                    <Icon 
                        name="PhGear"
                    />
                </div>
            </div>
            <div class="mx-auto max-w-screen-2xl h-[calc(100dvh-60px)] overflow-y-auto px-4">
                <div v-if="!skipWrapper" class="py-5 bg-white dark:bg-slate-600 mt-5 px-5 min-h-[calc(100dvh-100px)] rounded">
                    <slot></slot>
                </div>
                <slot v-else></slot>
            </div>
        </main>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import LeftSidebar from "./fragments/LeftSidebar.vue";
import { Head } from '@inertiajs/vue3'
import { useLayout } from "@/composable";

withDefaults(defineProps<{
    title?: string
    skipWrapper?: boolean
}>(), {
    skipWrapper: false
})

const { showLeftSidebar } = useLayout()

</script>
