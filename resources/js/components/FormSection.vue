<template>
    <section class="space-y-2">
        <div class="flex items-start gap-2">
            <span
                v-if="step"
                class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary-100 text-[11px] font-bold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300"
            >
                {{ step }}
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ title }}
                    </h3>
                    <span
                        v-if="optional"
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                    >
                        Optional
                    </span>
                </div>
                <p
                    v-if="hint"
                    class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                >
                    {{ hint }}
                </p>
            </div>
        </div>
        <div class="pl-7">
            <slot />
        </div>
    </section>
</template>

<script setup lang="ts">
defineProps<{
    title: string;
    step?: string;
    hint?: string;
    optional?: boolean;
}>();
</script>
