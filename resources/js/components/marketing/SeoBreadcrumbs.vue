<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <nav v-if="items.length > 1" aria-label="Breadcrumb" class="mb-4 text-xs text-slate-400 sm:mb-6 sm:text-sm">
        <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
            <li v-for="(item, index) in items" :key="`${item.path}-${index}`" class="flex max-w-full items-center gap-1.5">
                <span v-if="index > 0" aria-hidden="true" class="shrink-0 text-slate-600">/</span>
                <Link
                    v-if="index < items.length - 1"
                    :href="item.path"
                    class="min-h-9 inline-flex max-w-[11rem] items-center truncate hover:text-amber-300 sm:max-w-none"
                >
                    {{ item.name }}
                </Link>
                <span v-else class="line-clamp-2 text-slate-300" aria-current="page">{{ item.name }}</span>
            </li>
        </ol>
    </nav>
</template>
