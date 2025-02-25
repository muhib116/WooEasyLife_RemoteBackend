<template>
    <div
        class="box-bg box-color col-[span_var(--span)_/_span_var(--span)] box-border rounded-2xl border p-4 md:p-6"
        :style="`--span: ${data?.col_span || 1}`"
    >
        <div
            class="flex items-start justify-between text-lg font-semibold text-gray-800 dark:text-white/90"
        >
            {{ data?.title }}
        </div>

        <AutoScrollTop
            :offset="0"
            :autoScroll="false"
            class="my-4 max-h-[213px] overflow-y-auto"
        >
            <div
                v-for="(item, index) in data?.data || []"
                :key="index"
                class="box-border flex items-center justify-between border-b pb-4"
                :class="{
                    'pt-4': index > 0,
                }"
            >
                <span class="text-theme-xs text-gray-400">
                    {{ item?.title }}
                </span>
                <span class="text-right font-bold">
                    {{
                        item?.modifier && item?.modifier_position == "left"
                            ? item?.modifier
                            : ""
                    }}
                    {{ item?.value }}
                    {{
                        item?.modifier && item?.modifier_position == "right"
                            ? item?.modifier
                            : ""
                    }}
                </span>
            </div>
            <!-- <div
                class="box-color sticky bottom-0 z-20 grid place-content-center"
            >
                <button
                    class="box-bg box-border grid h-10 w-10 place-content-center rounded-full border shadow-box"
                >
                    <Icon name="PhCaretDown" />
                </button>
            </div> -->
        </AutoScrollTop>

        <Link
            v-if="data?.link"
            :href="data?.link"
            class="text-theme-sm shadow-theme-xs box-border flex justify-center gap-2 rounded-lg border bg-white p-1.5 font-medium text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-white/[0.03]"
        >
            {{ data?.link_text ? data?.link_text : "See Details" }}
            <Icon name="PhArrowRight" />
        </Link>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import AutoScrollTop from "./AutoScrollTop.vue";

interface DataProps {
    [key: string]: any; // Any key with any value type
}

defineProps<{
    data: DataProps;
}>();
</script>
