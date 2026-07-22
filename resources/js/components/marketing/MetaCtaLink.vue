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
import { trackCta as trackSiteCta } from '@/utils/siteVisitors';

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
    const path = typeof window !== 'undefined' ? window.location.pathname : '/';
    trackSiteCta(path, props.label || props.location || 'cta');
};
</script>
