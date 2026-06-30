<template>
    <div
        v-if="status"
        class="mt-3 flex items-start gap-3 rounded-lg border px-3.5 py-3 text-sm shadow-sm"
        :class="alertClass"
        role="alert"
        :aria-live="status === 'loading' ? 'polite' : 'assertive'"
    >
        <i
            v-if="status === 'loading'"
            class="pi pi-spin pi-spinner mt-0.5 shrink-0 text-base"
            aria-hidden="true"
        />
        <Icon
            v-else-if="status === 'error'"
            name="PhWarningCircle"
            class="mt-0.5 shrink-0 text-xl"
            weight="duotone"
            aria-hidden="true"
        />
        <Icon
            v-else-if="status === 'success'"
            name="PhCheckCircle"
            class="mt-0.5 shrink-0 text-xl"
            weight="duotone"
            aria-hidden="true"
        />

        <div class="min-w-0 flex-1">
            <p class="font-semibold leading-snug">
                {{ title }}
            </p>
            <p class="mt-1 leading-relaxed">
                {{ message }}
            </p>
            <p
                v-if="hint"
                class="mt-2 text-xs leading-relaxed opacity-90"
            >
                {{ hint }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { computed } from "vue";

const props = defineProps<{
    status: "loading" | "error" | "success" | null;
    title: string;
    message: string;
    hint?: string | null;
}>();

const alertClass = computed(() => {
    switch (props.status) {
        case "loading":
            return "border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-500/40 dark:bg-sky-500/15 dark:text-sky-50";
        case "error":
            return "border-rose-300 bg-rose-50 text-rose-950 ring-1 ring-rose-200/80 dark:border-rose-500/50 dark:bg-rose-500/15 dark:text-rose-50 dark:ring-rose-500/20";
        case "success":
            return "border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-50";
        default:
            return "";
    }
});
</script>
