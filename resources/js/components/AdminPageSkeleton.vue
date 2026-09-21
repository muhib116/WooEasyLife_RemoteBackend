<template>
    <div
        class="space-y-5"
        role="status"
        aria-live="polite"
        aria-label="Loading page"
    >
        <div class="rounded-2xl border border-gray-200/80 bg-white p-5 dark:border-gray-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="skeleton-block h-11 w-11 rounded-xl" />
                    <div class="space-y-2">
                        <div class="skeleton-block h-5 w-48" />
                        <div class="skeleton-block h-3 w-72 max-w-full" />
                    </div>
                </div>
                <div class="skeleton-block h-9 w-32 rounded-lg" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCardSkeleton :delay="0" />
            <StatCardSkeleton :delay="80" />
            <StatCardSkeleton :delay="160" />
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white dark:border-gray-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <div class="skeleton-block h-10 w-full max-w-sm rounded-lg" />
                <div class="hidden gap-2 sm:flex">
                    <div class="skeleton-block h-9 w-20 rounded-lg" />
                    <div class="skeleton-block h-9 w-24 rounded-lg" />
                </div>
            </div>
            <TableSkeletonLoader :columns="columns" :rows="8" />
        </div>
    </div>
</template>

<script setup lang="ts">
import StatCardSkeleton from "@/Pages/Users/fragments/StatCardSkeleton.vue";
import TableSkeletonLoader from "@/Pages/Users/fragments/TableSkeletonLoader.vue";

const columns = [
    { width: "16rem", headerWidth: "4rem", variant: "stack" as const },
    { width: "8rem", headerWidth: "3.5rem" },
    { width: "5rem", headerWidth: "3.5rem", variant: "badge" as const },
    { width: "7rem", headerWidth: "4.5rem" },
    { width: "9rem", headerWidth: "4rem" },
    { width: "8rem", headerWidth: "5rem" },
    { width: "7rem", headerWidth: "4.5rem", variant: "actions" as const },
];
</script>
