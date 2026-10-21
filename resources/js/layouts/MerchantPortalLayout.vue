<template>
    <Head :title="title" />

    <div class="merchant-portal admin-shell flex min-h-svh">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/55 backdrop-blur-sm lg:hidden"
            @click="sidebarOpen = false"
        />

        <div
            class="merchant-sidebar fixed inset-y-0 left-0 z-50 h-svh w-[272px] -translate-x-full border-r backdrop-blur-md transition-[width,transform] duration-200 lg:sticky lg:top-0 lg:z-40 lg:translate-x-0 lg:shrink-0"
            :class="{
                '!translate-x-0': sidebarOpen,
                'lg:w-[72px]': rail,
            }"
        >
            <button
                type="button"
                class="merchant-rail-toggle"
                :aria-label="rail ? 'Expand sidebar' : 'Collapse sidebar'"
                :title="rail ? 'Expand sidebar' : 'Collapse sidebar'"
                @click="toggleSidebar"
            >
                <Icon :name="rail ? 'PhCaretRight' : 'PhCaretLeft'" :size="12" />
            </button>
            <MerchantPortalSidebar :collapsed="rail" @close="sidebarOpen = false" />
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <MerchantPortalHeader
                :title="title"
                :sidebar-collapsed="rail"
                @toggle-sidebar="toggleSidebar"
            />

            <main class="admin-scrollbar flex-1 overflow-auto px-4 py-5 lg:px-6 lg:py-6">
                <div class="mx-auto w-full max-w-[1200px]">
                    <AdminPageSkeleton v-if="pageLoading" />
                    <div v-show="!pageLoading">
                        <slot />
                    </div>
                </div>
            </main>

            <nav class="merchant-tabbar sticky bottom-0 z-20 flex border-t backdrop-blur lg:hidden">
                <Link
                    v-for="item in tabItems"
                    :key="item.name"
                    :href="route(item.name)"
                    class="merchant-tab flex flex-1 flex-col items-center gap-1 border-t-2 py-2.5 text-[10px] font-medium"
                    :class="
                        route().current(item.active ?? item.name)
                            ? 'merchant-tab-active border-[#ffc107]'
                            : 'border-transparent'
                    "
                >
                    <Icon :name="item.icon" class="text-lg" />
                    <span>{{ item.title }}</span>
                </Link>
            </nav>
        </div>

        <AdminConfirmDialog />
        <Toast position="bottom-right" group="br" />
    </div>
</template>

<script setup lang="ts">
import AdminConfirmDialog from "@/Pages/Users/fragments/AdminConfirmDialog.vue";
import AdminPageSkeleton from "@/components/AdminPageSkeleton.vue";
import MerchantPortalHeader from "./fragments/MerchantPortalHeader.vue";
import MerchantPortalSidebar from "./fragments/MerchantPortalSidebar.vue";
import { useCollapsibleSidebar } from "@/composables/useCollapsibleSidebar";
import { useInertiaFlashToasts } from "@/composables/useInertiaFlashToasts";
import { useInertiaPageLoading } from "@/composables/useInertiaPageLoading";
import { useMerchantPortalNav } from "@/composables/useMerchantPortalNav";
import { Icon } from "@/plugins";
import { Head, Link } from "@inertiajs/vue3";
import Toast from "primevue/toast";
import { computed, onBeforeUnmount, onMounted } from "vue";
import "../../css/merchant-portal.css";

defineProps<{
    title: string;
}>();

const { sidebarOpen, sidebarCollapsed, isDesktop, toggleSidebar } = useCollapsibleSidebar(
    "merchant-sidebar-collapsed",
);
const rail = computed(() => sidebarCollapsed.value && isDesktop.value);
const pageLoading = useInertiaPageLoading();
const { tabItems } = useMerchantPortalNav();

onMounted(() => document.documentElement.classList.add("merchant-theme"));
onBeforeUnmount(() => document.documentElement.classList.remove("merchant-theme"));

useInertiaFlashToasts();
</script>
