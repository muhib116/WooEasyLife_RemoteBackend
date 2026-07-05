<template>
    <header
        class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between gap-4 border-b border-gray-200/80 bg-white/90 px-4 backdrop-blur-md dark:border-gray-800 dark:bg-slate-900/90 lg:px-6"
    >
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 lg:hidden dark:border-gray-700 dark:text-gray-300 dark:hover:bg-slate-800"
                @click="$emit('toggleSidebar')"
            >
                <Icon name="PhList" class="text-xl" />
            </button>
            <div class="min-w-0">
                <p class="truncate text-base font-semibold text-gray-900 dark:text-white">
                    {{ title }}
                </p>
                        <p
                            v-if="portal?.is_staff"
                            class="truncate text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ portal.employee?.role }} ·
                            {{ portal.employee?.website_domain || "All websites" }}
                        </p>
                        <p
                            v-else-if="accessLabel"
                            class="truncate text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ accessLabel }}
                        </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <Link
                :href="route('portal.profile')"
                class="hidden text-sm font-medium text-gray-700 hover:text-gray-900 sm:inline-flex dark:text-gray-200"
            >
                Profile
            </Link>
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-theme-sm rounded-lg border border-gray-200 px-3 py-2 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-slate-800"
            >
                Log out
            </Link>
        </div>
    </header>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

defineProps<{
    title: string;
}>();

defineEmits<{
    toggleSidebar: [];
}>();

const page = usePage();
const portal = computed(() => (page.props.auth as any)?.portal);
const accessLabel = computed(() => (page.props.auth as any)?.access_label as string | null);
</script>
