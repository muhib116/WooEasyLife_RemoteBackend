<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <nav v-if="items.length > 1" aria-label="Breadcrumb" class="mb-6 text-sm text-slate-400">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li v-for="(item, index) in items" :key="`${item.path}-${index}`" class="flex items-center gap-1.5">
                <span v-if="index > 0" aria-hidden="true" class="text-slate-600">/</span>
                <Link
                    v-if="index < items.length - 1"
                    :href="item.path"
                    class="hover:text-amber-300"
                >
                    {{ item.name }}
                </Link>
                <span v-else class="text-slate-300" aria-current="page">{{ item.name }}</span>
            </li>
        </ol>
    </nav>
</template>
