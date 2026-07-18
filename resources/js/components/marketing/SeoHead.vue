<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    seo: { type: Object, default: null },
    title: { type: String, default: null },
});

const pageTitle = computed(() => props.seo?.title || props.title || 'WooEasyLife');
const description = computed(() => props.seo?.description || '');
const canonical = computed(() => props.seo?.canonical || '');
const ogImage = computed(() => props.seo?.og_image || '');
const robots = computed(() => props.seo?.robots || 'index,follow');
const hreflang = computed(() => (Array.isArray(props.seo?.hreflang) ? props.seo.hreflang : []));
const jsonLd = computed(() => {
    if (!props.seo?.json_ld) {
        return '';
    }
    try {
        return JSON.stringify(props.seo.json_ld);
    } catch {
        return '';
    }
});
</script>

<template>
    <Head :title="pageTitle">
        <meta v-if="description" head-key="description" name="description" :content="description" />
        <meta v-if="robots" head-key="robots" name="robots" :content="robots" />
        <link v-if="canonical" head-key="canonical" rel="canonical" :href="canonical" />
        <link
            v-for="(alt, index) in hreflang"
            :key="`hreflang-${alt.hreflang || index}`"
            :head-key="`hreflang-${alt.hreflang || index}`"
            rel="alternate"
            :hreflang="alt.hreflang"
            :href="alt.url"
        />
        <meta v-if="pageTitle" head-key="og:title" property="og:title" :content="pageTitle" />
        <meta v-if="description" head-key="og:description" property="og:description" :content="description" />
        <meta v-if="canonical" head-key="og:url" property="og:url" :content="canonical" />
        <meta head-key="og:type" property="og:type" :content="seo?.og_type || 'website'" />
        <meta v-if="ogImage" head-key="og:image" property="og:image" :content="ogImage" />
        <meta
            v-if="seo?.og_image_width"
            head-key="og:image:width"
            property="og:image:width"
            :content="String(seo.og_image_width)"
        />
        <meta
            v-if="seo?.og_image_height"
            head-key="og:image:height"
            property="og:image:height"
            :content="String(seo.og_image_height)"
        />
        <meta head-key="og:locale" property="og:locale" content="bn_BD" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta v-if="pageTitle" head-key="twitter:title" name="twitter:title" :content="pageTitle" />
        <meta v-if="description" head-key="twitter:description" name="twitter:description" :content="description" />
        <meta v-if="ogImage" head-key="twitter:image" name="twitter:image" :content="ogImage" />
        <component
            :is="'script'"
            v-if="jsonLd"
            head-key="json-ld"
            type="application/ld+json"
            v-text="jsonLd"
        />
    </Head>
</template>
