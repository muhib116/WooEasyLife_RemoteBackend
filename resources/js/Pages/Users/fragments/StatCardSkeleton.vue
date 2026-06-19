<template>
    <div
        class="box-bg box-color box-border relative overflow-hidden rounded-2xl border p-5"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1 space-y-3">
                <div
                    class="skeleton-block h-3.5"
                    :style="{ width: '5.5rem', animationDelay: `${delay}ms` }"
                />
                <div
                    class="skeleton-block h-8"
                    :style="{ width: '3.5rem', animationDelay: `${delay + 80}ms` }"
                />
                <div
                    class="skeleton-block h-3"
                    :style="{ width: '7.5rem', animationDelay: `${delay + 160}ms` }"
                />
            </div>
            <div
                class="skeleton-block h-11 w-11 shrink-0 rounded-xl"
                :style="{ animationDelay: `${delay + 120}ms` }"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
withDefaults(
    defineProps<{
        delay?: number;
    }>(),
    {
        delay: 0,
    },
);
</script>
