<template>
    <div
        class="flex items-center justify-between gap-5 border-b border-gray-100 pb-3 dark:border-gray-700"
    >
        <div>
            User Details Of
            <span class="text-primary-500">{{
                get($page.props, "user.name")
            }}</span>
        </div>
        <Link :href="route('users.index')">
            <Button
                severity="primary"
                size="small"
                icon="pi pi-arrow-left"
                class="pointer-events-none"
            />
        </Link>
    </div>
</template>

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { get } from "lodash";
</script>
