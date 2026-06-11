<template>
    <div
        class="box-bg box-color box-border min-w-[180px] flex-1 rounded-xl border p-4"
    >
        <p class="text-theme-sm text-gray-500 dark:text-gray-400">
            {{ title }}
        </p>
        <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
            {{ value }}
        </p>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    title?: string;
    value?: string | number;
}>();
</script>
