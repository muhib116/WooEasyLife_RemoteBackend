<template>
    <span
        :class="
            twMerge(
                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                variants[variant],
                $attrs.class as string,
            )
        "
    >
        <slot>{{ label }}</slot>
    </span>
</template>

<script setup lang="ts">
import { twMerge } from "tailwind-merge";

type Variant = "success" | "danger" | "warning" | "info" | "neutral" | "primary";

withDefaults(
    defineProps<{
        label?: string;
        variant?: Variant;
    }>(),
    {
        variant: "neutral",
    },
);

const variants: Record<Variant, string> = {
    success:
        "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300",
    danger: "bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300",
    warning:
        "bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300",
    info: "bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300",
    neutral:
        "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300",
    primary:
        "bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300",
};
</script>
