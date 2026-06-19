<template>
    <div
        class="table-actions"
        :class="align === 'right' ? 'justify-end' : 'justify-start'"
    >
        <slot />
    </div>
</template>

<script setup lang="ts">
withDefaults(
    defineProps<{
        align?: "left" | "right";
    }>(),
    {
        align: "right",
    },
);
</script>
