<template>
    <div
        :class="
            twMerge(
                'flex shrink-0 items-center justify-center rounded-xl font-semibold uppercase',
                sizes[size],
                toneClasses[tone],
            )
        "
    >
        {{ initials }}
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { twMerge } from "tailwind-merge";

const props = withDefaults(
    defineProps<{
        name?: string;
        size?: "sm" | "md" | "lg";
        tone?: "primary" | "slate";
    }>(),
    {
        name: "?",
        size: "md",
        tone: "primary",
    },
);

const sizes = {
    sm: "h-9 w-9 text-xs",
    md: "h-11 w-11 text-sm",
    lg: "h-14 w-14 text-base",
};

const toneClasses = {
    primary:
        "bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300",
    slate: "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300",
};

const initials = computed(() => {
    const parts = (props.name || "?").trim().split(/\s+/).filter(Boolean);

    if (!parts.length) {
        return "?";
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2);
    }

    return `${parts[0][0]}${parts[1][0]}`;
});
</script>
