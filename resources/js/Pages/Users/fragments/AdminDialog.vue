<template>
    <Dialog
        v-model:visible="visible"
        modal
        :dismissableMask="dismissableMask"
        :header="header"
        :style="style"
        :draggable="draggable"
        :maximizable="maximizable"
        class="admin-dialog"
        @hide="$emit('hide')"
    >
        <template v-if="$slots.header" #header>
            <slot name="header" />
        </template>

        <slot />

        <template v-if="$slots.footer" #footer>
            <slot name="footer" />
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import type { CSSProperties } from "vue";

withDefaults(
    defineProps<{
        header?: string;
        style?: CSSProperties | string;
        draggable?: boolean;
        dismissableMask?: boolean;
        maximizable?: boolean;
    }>(),
    {
        draggable: false,
        dismissableMask: true,
        maximizable: false,
    },
);

const visible = defineModel<boolean>("visible", { required: true });

defineEmits<{
    hide: [];
}>();
</script>
