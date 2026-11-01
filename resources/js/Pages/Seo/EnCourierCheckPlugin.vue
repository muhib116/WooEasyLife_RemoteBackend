<script setup>
import Page from './CourierCheckPlugin.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    whatsappUrl: { type: String, default: null },
    whatsappContactUrl: { type: String, default: null },
    faqs: { type: Array, default: () => [] },
    wporgLive: { type: Boolean, default: false },
    downloadUrl: { type: String, default: '' },
    screenshots: { type: Array, default: () => [] },
});
</script>

<template>
    <Page
        :can-login="props.canLogin"
        :seo="props.seo"
        :whatsapp-url="props.whatsappUrl"
        :faqs="props.faqs"
        :wporg-live="props.wporgLive"
        :download-url="props.downloadUrl"
        :screenshots="props.screenshots"
    />
</template>
