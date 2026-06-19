<template>
    <div :class="gridColumnClass">
        <PageCard>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    v-if="icon"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-500/15"
                >
                    <Icon
                        :name="icon"
                        class="text-lg text-primary-600 dark:text-primary-400"
                    />
                </div>
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">
                        {{ data?.title }}
                    </h2>
                    <p
                        v-if="description"
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
        </template>

        <div v-if="showProgress" class="mb-5">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">Usage</span>
                <span class="font-semibold text-gray-700 dark:text-gray-200">
                    {{ progressPercent }}%
                </span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div
                    class="h-full rounded-full bg-primary-500 transition-all duration-500"
                    :style="{ width: `${progressPercent}%` }"
                />
            </div>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            <div
                v-for="(item, index) in data?.data || []"
                :key="index"
                class="flex items-center justify-between py-3.5 first:pt-0 last:pb-0"
            >
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ item?.title }}
                </span>
                <span class="text-right text-sm font-semibold text-gray-800 dark:text-white/90">
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
        </div>

        <Link
            v-if="data?.link"
            :href="data?.link"
            class="text-theme-sm mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-slate-800 dark:text-gray-100 dark:hover:bg-slate-700"
        >
            {{ data?.link_text ? data?.link_text : "See Details" }}
            <Icon name="PhArrowRight" />
        </Link>
        </PageCard>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import type { IconName } from "@/types";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

interface DataProps {
    [key: string]: any;
}

const props = defineProps<{
    data: DataProps;
    icon?: IconName;
    description?: string;
    showProgress?: boolean;
    progressPercent?: number;
}>();

const gridColumnClass = computed(() => {
    const span = Number(props.data?.col_span ?? 1);

    if (span >= 2) {
        return "xl:col-span-2";
    }

    return "";
});
</script>
