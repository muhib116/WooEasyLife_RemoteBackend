<template>
    <div
        class="box-bg box-color col-[span_var(--span)_/_span_var(--span)] box-border flex h-full flex-col justify-between rounded-2xl border p-5 md:p-6"
        :style="`--span: ${data?.col_span || 1}`"
    >
        <div>
            <div class="mb-1 flex items-center gap-3">
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
                    <h3
                        class="text-lg font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ data?.title }}
                    </h3>
                    <p
                        v-if="description"
                        class="text-theme-xs text-gray-400 dark:text-gray-500"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>

            <div v-if="showProgress" class="mb-5 mt-4">
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">
                        Usage
                    </span>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">
                        {{ progressPercent }}%
                    </span>
                </div>
                <div
                    class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                >
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
                    <span class="text-theme-sm text-gray-500 dark:text-gray-400">
                        {{ item?.title }}
                    </span>
                    <span
                        class="text-right text-sm font-semibold text-gray-800 dark:text-white/90"
                    >
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
        </div>

        <Link
            v-if="data?.link"
            :href="data?.link"
            class="text-theme-sm shadow-theme-xs mt-5 flex items-center justify-center gap-2 rounded-lg border bg-white px-4 py-2.5 font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-white/[0.03]"
        >
            {{ data?.link_text ? data?.link_text : "See Details" }}
            <Icon name="PhArrowRight" />
        </Link>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import type { IconName } from "@/types";

interface DataProps {
    [key: string]: any;
}

defineProps<{
    data: DataProps;
    icon?: IconName;
    description?: string;
    showProgress?: boolean;
    progressPercent?: number;
}>();
</script>
