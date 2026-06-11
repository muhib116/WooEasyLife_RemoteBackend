<template>
    <AuthenticatedLayout :title="title">
        <div class="space-y-5">
            <Header
                :user="user"
                :section="section"
                :subtitle="subtitle"
            />
            <UserNav v-if="user" :user="user">
                <slot name="actions" />
            </UserNav>
            <slot />
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import Header from "./Header.vue";
import UserNav from "./UserNav.vue";

defineProps<{
    title: string;
    section: string;
    subtitle?: string;
    user: { id: number; name: string } | null;
}>();
</script>
