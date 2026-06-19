<template>
    <div class="table-skeleton-loader overflow-x-auto">
        <div class="min-w-[72rem]">
            <div
                v-if="showHeader"
                class="table-skeleton-header grid gap-4 border-b border-slate-200/80 px-5 py-3 dark:border-slate-700/80"
                :style="{ gridTemplateColumns }"
            >
                <div
                    v-for="(column, index) in columns"
                    :key="`header-${index}`"
                    class="skeleton-block h-3"
                    :style="{
                        width: column.headerWidth || '70%',
                        maxWidth: column.width,
                        animationDelay: `${index * 60}ms`,
                    }"
                />
            </div>

            <div
                v-for="row in rows"
                :key="row"
                class="table-skeleton-row border-b border-slate-100 px-5 py-4 last:border-b-0 dark:border-slate-800/80"
            >
                <div
                    class="grid items-center gap-4"
                    :style="{ gridTemplateColumns }"
                >
                <div
                    v-for="(column, index) in columns"
                    :key="`${row}-${index}`"
                    class="min-w-0"
                >
                    <div
                        v-if="column.variant === 'checkbox'"
                        class="skeleton-block h-4 w-4 rounded"
                        :style="{ animationDelay: `${row * 70 + index * 40}ms` }"
                    />
                    <div
                        v-else-if="column.variant === 'badge'"
                        class="skeleton-block h-6 rounded-full"
                        :style="{
                            width: column.width,
                            animationDelay: `${row * 70 + index * 40}ms`,
                        }"
                    />
                    <div
                        v-else-if="column.variant === 'stack'"
                        class="space-y-2"
                    >
                        <div
                            class="skeleton-block h-3.5"
                            :style="{
                                width: column.width,
                                animationDelay: `${row * 70 + index * 40}ms`,
                            }"
                        />
                        <div
                            class="skeleton-block h-3"
                            :style="{
                                width: column.subWidth || '4.5rem',
                                animationDelay: `${row * 70 + index * 40 + 60}ms`,
                            }"
                        />
                    </div>
                    <div
                        v-else-if="column.variant === 'actions'"
                        class="flex gap-2"
                    >
                        <div
                            v-for="action in 3"
                            :key="action"
                            class="skeleton-block h-8 w-8 rounded-full"
                            :style="{
                                animationDelay: `${row * 70 + index * 40 + action * 30}ms`,
                            }"
                        />
                    </div>
                    <div
                        v-else
                        class="skeleton-block"
                        :class="column.heightClass || 'h-3.5'"
                        :style="{
                            width: column.width,
                            animationDelay: `${row * 70 + index * 40}ms`,
                        }"
                    />
                </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";

type SkeletonColumn = {
    width: string;
    headerWidth?: string;
    variant?: "checkbox" | "badge" | "stack" | "actions" | "bar";
    subWidth?: string;
    heightClass?: string;
};

const props = withDefaults(
    defineProps<{
        columns: SkeletonColumn[];
        rows?: number;
        showHeader?: boolean;
    }>(),
    {
        rows: 6,
        showHeader: true,
    },
);

const gridTemplateColumns = computed(() => {
    return props.columns.map((column) => column.width).join(" ");
});
</script>
