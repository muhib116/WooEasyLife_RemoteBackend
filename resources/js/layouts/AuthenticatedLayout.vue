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
            <LeftSidebar @close="sidebarOpen = false" />
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <AppHeader
                :title="title"
                @toggle-sidebar="sidebarOpen = !sidebarOpen"
                @open-theme="themeDialog = true"
            />

            <main
                class="admin-scrollbar flex-1 overflow-auto px-4 py-5 lg:px-6 lg:py-6"
                :class="wrapperClass"
            >
                <div class="mx-auto w-full max-w-[1600px]">
                    <slot />
                </div>
            </main>
        </div>

        <Dialog
            v-model:visible="themeDialog"
            modal
            closeOnEscape
            blockScroll
            draggable
            dismissableMask
            header="Brand Color"
            :style="{ width: '25rem' }"
        >
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                Choose the primary accent color for the admin panel.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                <button
                    v-for="(color, name) in colors"
                    :key="name"
                    type="button"
                    class="h-9 w-9 cursor-pointer rounded-full border-2 border-transparent transition hover:scale-110"
                    :class="{
                        'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-slate-900':
                            primaryTheme == name,
                    }"
                    :style="{ backgroundColor: get(color, '500') }"
                    :title="String(name)"
                    @click="changePrimaryColor(name)"
                />
            </div>
        </Dialog>

        <AdminConfirmDialog />
        <Toast position="bottom-right" group="br" />
    </div>
</template>

<script setup lang="ts">
import AdminConfirmDialog from "@/Pages/Users/fragments/AdminConfirmDialog.vue";
import LeftSidebar from "./fragments/LeftSidebar.vue";
import AppHeader from "./fragments/AppHeader.vue";
import { useInertiaFlashToasts } from "@/composables/useInertiaFlashToasts";
import { Head, router, usePage } from "@inertiajs/vue3";
import { useTheme } from "@/composable";
import { computed, onMounted, ref } from "vue";
import { get } from "lodash";

withDefaults(
    defineProps<{
        title?: string;
        skipWrapper?: boolean;
        wrapperClass?: string;
    }>(),
    {
        skipWrapper: false,
    },
);

const page = usePage();
const accessArea = computed(() => (page.props.auth as any)?.access_area);

onMounted(() => {
    if (accessArea.value === "portal") {
        router.visit(route("portal.dashboard"), { replace: true });
    }
});

const sidebarOpen = ref(false);
const themeDialog = ref(false);
const { colors, primaryTheme, changePrimaryColor } = useTheme();

useInertiaFlashToasts();
</script>
