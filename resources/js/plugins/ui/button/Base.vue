<!-- src/components/BaseButton.vue -->
<template>
    <button 
        :class="twMerge(variantClasses, $attrs.class as string || '')"
    >
        {{ variant }}
        <slot />
    </button>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useButton } from "./useButton";
import { twMerge } from "tailwind-merge";

const props = withDefaults(defineProps<{
    variant: 'primary' | 'secondary' | 'danger' | 'success'
}>(), {
    variant: 'primary'
});

const variant = ref(props.variant);
const { variantClasses } = useButton(variant);
</script>
