<template>
    <div class="flex justify-between items-center gap-5 border-b pb-3">
        User Details
        <Link :href="route('users.index')">
            <Button
                severity="info"
                size="small"
                icon="pi pi-arrow-left"
                class="pointer-events-none"
            />
        </Link>
    </div>
</template>

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
</script>
