<template>
    <div
        :class="
            twMerge(
                'w-[300px] rounded border border-slate-200 p-4',
                get($attrs, 'class'),
            )
        "
    >
        <h2 class="font-semibold">{{ title }}</h2>
        <div class="text-4xl">
            {{ value }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { twMerge } from "tailwind-merge";
import { get } from "lodash";
defineProps<{
    title?: string;
    value?: string;
}>();
</script>
