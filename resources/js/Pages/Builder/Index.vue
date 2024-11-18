<template>
    <AuthenticatedLayout title="Product List">
        <div>
            <h1 class="text-2xl mb-4">My Web Page</h1>
            <DragDropBuilder />
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import DragDropBuilder from "@/components/DragDropBuilder.vue";
</script>
