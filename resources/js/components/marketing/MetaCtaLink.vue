<template>
    <Link
        :href="href"
        :class="linkClass"
        @click="onClick"
    >
        <slot>{{ label }}</slot>
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { trackCtaClick } from '@/utils/metaPixel';

const props = defineProps({
    href: { type: String, required: true },
    label: { type: String, default: '' },
    location: { type: String, default: 'seo_primary_cta' },
    linkClass: { type: String, default: '' },
});

const onClick = () => {
    trackCtaClick({
        location: props.location,
        href: props.href,
        label: props.label || undefined,
    });
};
</script>
