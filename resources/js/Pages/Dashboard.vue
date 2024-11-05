<template>
    <AuthenticatedLayout title="Dashboard">
        <div class="grid grid-cols-4 gap-5 pt-3">
            <div
                v-for="i in 8"
                class="rounded-md bg-white border border-dashed border-gray-200 p-4"
            >
                <div class="flex items-center mb-0.5">
                    <div class="text-xl font-semibold">
                        {{ Math.round(Math.random() * 400) }}
                    </div>
                    <span
                        class="p-1 rounded text-[12px] font-semibold bg-emerald-500/10 text-emerald-500 leading-none ml-1"
                    >
                        {{ Math.round(Math.random() * 400) }}
                    </span>
                </div>
                <span class="text-gray-400 text-sm">Completed</span>
            </div>
        </div>
        <div class="py-12">
            <div class="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 relative">
                    <!-- <Tooltip text="Hi">
                    </Tooltip> -->
                    <button
                        class="relative bg-blue-500 text-white p-2"
                        v-tooltip.right-bottom="{
                            text: 'Hi tooltip',
                            parentPosition: 'relative',
                        }"
                    >
                        <!-- <span class="py-1 px-3 bg-black text-white right-0 group-hover:block hidden rounded-sm absolute bottom-full">Hi tooltip</span> -->
                        Take Screenshot
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import Tooltip from "./Tooltip.vue";
import { vTooltip } from "./directive";
</script>

<style scoped>
.tooltip {
    position: relative;
}
.tooltip::before {
    content: "Hi";
    place-content: center;
    padding: 8px 12px;
    background-color: red;
    color: white;
    display: none;
    position: absolute;
    top: calc(100% + 10px);
}
.tooltip:hover::before {
    display: grid;
}
</style>
