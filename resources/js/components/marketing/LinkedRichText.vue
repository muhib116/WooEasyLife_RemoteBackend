<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { linkifyInternalPaths } from '@/utils/internalPathLinks';

const props = defineProps({
    text: { type: String, default: '' },
    isEn: { type: Boolean, default: false },
});

const segments = computed(() => linkifyInternalPaths(props.text, props.isEn));
</script>

<template>
    <template v-for="(seg, si) in segments" :key="si">
        <a
            v-if="seg.type === 'link' && seg.external"
            :href="seg.href"
            target="_blank"
            rel="noopener noreferrer"
            class="inline break-words font-semibold text-amber-400 underline-offset-2 hover:text-amber-300 hover:underline"
        >{{ seg.label }}</a>
        <Link
            v-else-if="seg.type === 'link'"
            :href="seg.href"
            class="inline break-words font-semibold text-amber-400 underline-offset-2 hover:text-amber-300 hover:underline"
        >{{ seg.label }}</Link>
        <span v-else class="break-words">{{ seg.text }}</span>
    </template>
</template>
