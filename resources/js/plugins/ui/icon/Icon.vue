<template>
    <div
        :style="{
            width: `${size}px`,
            height: `${size}px`,
        }"
    >
        <component :is="iconComponent" v-bind="$attrs" :size="size" />
    </div>
</template>

<script setup lang="ts">
import { shallowRef, watch } from "vue";
import { IconName } from "@/types";

defineOptions({
    name: "Icon",
    inheritAttrs: false,
});
const iconComponent = shallowRef(null);
const props = withDefaults(
    defineProps<{
        name: IconName;
        source?: "phosphor" | "custom";
        wrapperClass?: string;
        size?: number | string;
    }>(),
    {
        source: "phosphor",
        size: 25,
    }
);
watch(
    () => props.name,
    async () => {
        let response;
        if (props.source == "phosphor") {
            response = await import("@phosphor-icons/vue");
        }
        if (props.source == "custom") {
            response = await import("@/icons");
        }
        iconComponent.value = response ? response[props.name] : "";
    },
    { immediate: true }
);
</script>
