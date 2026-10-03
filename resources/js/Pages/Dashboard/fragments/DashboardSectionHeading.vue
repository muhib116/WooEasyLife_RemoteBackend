<template>
    <div class="flex items-center justify-between gap-3">
        <h2 class="min-w-0 truncate text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ title }}
        </h2>
        <Link
            v-if="href"
            :href="href"
            class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-primary-600 transition hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
        >
            {{ linkText }}
            <Icon name="PhArrowRight" class="text-sm" />
        </Link>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";

defineProps<{
    title: string;
    href?: string;
    linkText?: string;
}>();
</script>
