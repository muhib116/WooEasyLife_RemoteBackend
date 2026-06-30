<template>
    <div
        v-show="show"
        id="app-navigation-loader"
        :class="{ 'is-hidden': !show }"
        aria-live="polite"
        aria-busy="true"
    >
        <div class="app-loader-spinner" role="status" aria-label="লোড হচ্ছে">
            <div class="app-loader-spinner__ring app-loader-spinner__ring--one" />
            <div class="app-loader-spinner__ring app-loader-spinner__ring--two" />
            <img src="/app-logo" alt="" class="app-loader-spinner__logo" width="53" height="53">
        </div>
    </div>
</template>

<script setup>
defineProps({
    show: {
        type: Boolean,
        default: true,
    },
});
</script>
