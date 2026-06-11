<template>
    <div
        class="box-bg box-color box-border rounded-2xl border px-5 py-4 shadow-sm"
    >
        <div class="flex items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                <UserAvatar
                    v-if="user?.name"
                    :name="user.name"
                    size="lg"
                />
                <div class="min-w-0">
                    <nav
                        class="mb-1 flex min-w-0 flex-wrap items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500"
                    >
                        <Link
                            :href="route('users.index')"
                            class="transition-colors hover:text-primary-500"
                        >
                            Users
                        </Link>
                        <template v-if="user?.name">
                            <span>/</span>
                            <Link
                                :href="route('users.view', user.id)"
                                class="transition-colors hover:text-primary-500"
                            >
                                {{ user.name }}
                            </Link>
                        </template>
                        <template v-if="section">
                            <span>/</span>
                            <span class="text-primary-600 dark:text-primary-400">
                                {{ section }}
                            </span>
                        </template>
                    </nav>
                    <h1
                        class="truncate text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ pageTitle }}
                    </h1>
                    <p
                        v-if="subtitle"
                        class="mt-0.5 text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ subtitle }}
                    </p>
                </div>
            </div>
            <Link :href="route('users.index')">
                <Button
                    label="All Users"
                    icon="pi pi-arrow-left"
                    size="small"
                    severity="secondary"
                    outlined
                />
            </Link>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";
import UserAvatar from "./fragments/UserAvatar.vue";

const props = defineProps<{
    user?: { id: number; name: string } | null;
    section?: string;
    subtitle?: string;
}>();

const pageTitle = computed(() => {
    if (props.section && props.user?.name) {
        return props.section;
    }

    if (props.user?.name) {
        return props.user.name;
    }

    return "Users";
});
</script>
