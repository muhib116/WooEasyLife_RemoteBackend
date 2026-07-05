<template>
    <Button
        :as="linkTag"
        :href="href"
        :download="download"
        size="small"
        rounded
        :icon="resolvedIcon"
        :severity="resolvedSeverity"
        :loading="loading"
        :disabled="disabled"
        class="table-action-btn"
        v-tooltip.top="tooltip"
        @click="handleClick"
    />
</template>

<script setup lang="ts">
import { computed } from "vue";

const ACTION_ICONS: Record<string, string> = {
    edit: "pi pi-pencil",
    delete: "pi pi-trash",
    download: "pi pi-download",
    view: "pi pi-eye",
    copy: "pi pi-copy",
    retry: "pi pi-replay",
    restore: "pi pi-replay",
    link: "pi pi-link",
    external: "pi pi-external-link",
    key: "pi pi-key",
    map: "pi pi-map",
    approve: "pi pi-check",
    contact: "pi pi-phone",
    reject: "pi pi-times",
    navigate: "pi pi-arrow-right",
};

const props = withDefaults(
    defineProps<{
        action?: keyof typeof ACTION_ICONS;
        icon?: string;
        severity?: "secondary" | "danger" | "warn" | "success" | "info";
        tooltip?: string;
        href?: string;
        download?: boolean | string;
        loading?: boolean;
        disabled?: boolean;
        as?: string;
    }>(),
    {},
);

const emit = defineEmits<{
    click: [event: MouseEvent];
}>();

const resolvedIcon = computed(() => {
    if (props.icon) {
        return props.icon;
    }

    if (props.action) {
        return ACTION_ICONS[props.action];
    }

    return "pi pi-ellipsis-h";
});

const resolvedSeverity = computed(() => {
    if (props.severity) {
        return props.severity;
    }

    switch (props.action) {
        case "delete":
        case "reject":
            return "danger";
        case "retry":
            return "warn";
        case "restore":
        case "approve":
            return "success";
        case "edit":
        case "contact":
            return "info";
        default:
            return "secondary";
    }
});

const linkTag = computed(() => {
    if (props.as) {
        return props.as;
    }

    return props.href ? "a" : "button";
});

const handleClick = (event: MouseEvent) => {
    if (!props.href) {
        emit("click", event);
    }
};
</script>
