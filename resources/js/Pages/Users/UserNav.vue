<template>
    <div class="flex gap-3 border-b pb-2 justify-between">
        <div class="flex gap-3">
            <Link
                :href="route('users.view', user.id)"
                class="py-1 px-4 hover:bg-indigo-500 hover:text-white"
                :class="{
                    'bg-indigo-500 text-white': route().current('users.view'),
                }"
            >
                Overview
            </Link>
            <Link
                :href="route('users.apiKeys', user.id)"
                class="py-1 px-4 hover:bg-indigo-500 hover:text-white"
                :class="{
                    'bg-indigo-500 text-white': route().current('users.apiKeys'),
                }"
            >
                Api Keys
            </Link>
            <Link
                :href="route('users.packages', user.id)"
                class="py-1 px-4 hover:bg-indigo-500 hover:text-white"
                :class="{
                    'bg-indigo-500 text-white': route().current('users.packages'),
                }"
            >
                Packages
            </Link>
            <Link
                :href="route('users.index')"
                class="py-1 px-4 hover:bg-indigo-500 hover:text-white"
            >
                SMS Balance
            </Link>
        </div>
        <div>
            <slot></slot>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";

const props = defineProps<{
    user: any;
}>();
</script>
