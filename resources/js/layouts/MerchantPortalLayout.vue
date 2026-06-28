<template>
    <Head :title="title" />

    <div class="admin-shell flex min-h-svh bg-slate-100 dark:bg-slate-950">
        <div
            class="fixed inset-y-0 left-0 z-50 hidden h-svh w-[272px] border-r border-gray-200/80 bg-white lg:sticky lg:top-0 lg:block lg:shrink-0 dark:border-gray-800 dark:bg-slate-900"
        >
            <MerchantPortalSidebar />
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 border-b border-gray-200/80 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-slate-900/95">
                <div class="flex h-16 items-center justify-between gap-4 px-4 lg:px-6">
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ title }}
                        </h1>
                        <p v-if="portal?.is_staff" class="text-xs text-gray-500 dark:text-gray-400">
                            {{ portal.employee?.role }} ·
                            {{ portal.employee?.website_domain || "All websites" }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('profile.edit')"
                            class="text-theme-sm rounded-lg border border-gray-200 px-3 py-2 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-slate-800"
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
                </div>
            </header>

            <main class="admin-scrollbar flex-1 overflow-auto px-4 py-5 lg:px-6 lg:py-6">
                <div class="mx-auto w-full max-w-[1200px]">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup lang="ts">
import MerchantPortalSidebar from "./fragments/MerchantPortalSidebar.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

defineProps<{
    title: string;
}>();

const page = usePage();
const portal = computed(() => (page.props.auth as any)?.portal);
</script>
