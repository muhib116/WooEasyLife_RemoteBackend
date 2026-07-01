<template>
    <Head :title="title" />

    <div class="admin-shell flex min-h-svh bg-slate-100 dark:bg-slate-950">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"
            @click="sidebarOpen = false"
        />

        <div
            class="fixed inset-y-0 left-0 z-50 h-svh w-[272px] -translate-x-full border-r border-gray-200/80 bg-white transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto lg:translate-x-0 lg:shrink-0 dark:border-gray-800 dark:bg-slate-900"
            :class="{ '!translate-x-0': sidebarOpen }"
        >
            <MerchantPortalSidebar @close="sidebarOpen = false" />
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <MerchantPortalHeader :title="title" @toggle-sidebar="sidebarOpen = !sidebarOpen" />

            <main class="admin-scrollbar flex-1 overflow-auto px-4 py-5 lg:px-6 lg:py-6">
                <div class="mx-auto w-full max-w-[1200px]">
                    <slot />
                </div>
            </main>

            <nav
                class="sticky bottom-0 z-20 flex border-t border-gray-200/80 bg-white/95 backdrop-blur lg:hidden dark:border-gray-800 dark:bg-slate-900/95"
            >
                <Link
                    v-for="item in navItems"
                    :key="item.name"
                    :href="route(item.name)"
                    class="flex flex-1 flex-col items-center gap-1 py-2.5 text-[10px] font-medium"
                    :class="
                        route().current(item.name)
                            ? 'text-primary-600 dark:text-primary-400'
                            : 'text-gray-500 dark:text-gray-400'
                    "
                >
                    <Icon :name="item.icon" class="text-lg" />
                    <span>{{ item.title }}</span>
                </Link>
            </nav>
        </div>

        <Toast position="bottom-right" group="br" />
    </div>
</template>

<script setup lang="ts">
import MerchantPortalHeader from "./fragments/MerchantPortalHeader.vue";
import MerchantPortalSidebar from "./fragments/MerchantPortalSidebar.vue";
import { useInertiaFlashToasts } from "@/composables/useInertiaFlashToasts";
import { useMerchantPortalNav } from "@/composables/useMerchantPortalNav";
import { Icon } from "@/plugins";
import { Head, Link } from "@inertiajs/vue3";
import Toast from "primevue/toast";
import { ref } from "vue";

defineProps<{
    title: string;
}>();

const sidebarOpen = ref(false);
const { navItems } = useMerchantPortalNav();

useInertiaFlashToasts();
</script>
