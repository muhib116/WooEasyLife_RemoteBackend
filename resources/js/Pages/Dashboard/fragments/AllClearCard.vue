<template>
    <div
        class="box-bg box-color box-border flex items-center gap-4 rounded-2xl border px-5 py-6 md:px-6"
    >
        <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-500/15"
        >
            <Icon name="PhCheckCircle" class="text-2xl text-emerald-600 dark:text-emerald-400" />
        </div>
        <div>
            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                All clear
            </p>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                No pending payments, subscription issues, or token expiry alerts right now.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
</script>
