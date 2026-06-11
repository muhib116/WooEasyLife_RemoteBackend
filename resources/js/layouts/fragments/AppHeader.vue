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
                <p
                    v-if="title"
                    class="truncate text-base font-semibold text-gray-900 dark:text-white"
                >
                    {{ title }}
                </p>
                <p
                    v-else
                    class="text-base font-semibold text-gray-900 dark:text-white"
                >
                    Admin Panel
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-slate-800"
                :title="isDarkMode ? 'Light mode' : 'Dark mode'"
                @click="isDarkMode = !isDarkMode"
            >
                <Icon :name="isDarkMode ? 'PhSun' : 'PhMoonStars'" class="text-lg" />
            </button>
            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-slate-800"
                title="Theme color"
                @click="$emit('openTheme')"
            >
                <Icon name="PhPalette" class="text-lg" />
            </button>

            <div class="relative" ref="menuRef">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-xl border border-gray-200 py-1.5 pl-1.5 pr-3 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-slate-800"
                    @click="menuOpen = !menuOpen"
                >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300"
                    >
                        {{ userInitial }}
                    </div>
                    <span
                        class="hidden max-w-[120px] truncate text-sm font-medium text-gray-700 sm:block dark:text-gray-200"
                    >
                        {{ user?.name || "Admin" }}
                    </span>
                    <Icon name="PhCaretDown" class="text-gray-400" />
                </button>

                <div
                    v-if="menuOpen"
                    class="absolute right-0 top-full z-50 mt-2 w-52 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-slate-800"
                >
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                            {{ user?.name }}
                        </p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ user?.email }}
                        </p>
                    </div>
                    <Link
                        :href="route('profile.edit')"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-slate-700"
                        @click="menuOpen = false"
                    >
                        <Icon name="PhUser" />
                        Profile
                    </Link>
                    <Link
                        :href="route('dashboard')"
                        class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-slate-700"
                        @click="menuOpen = false"
                    >
                        <Icon name="PhSquaresFour" />
                        Dashboard
                    </Link>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                        @click="logout"
                    >
                        <Icon name="PhSignOut" />
                        Sign out
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Icon } from "@/plugins";
import { useTheme } from "@/composable";
import { Link, router, usePage } from "@inertiajs/vue3";

defineProps<{
    title?: string;
}>();

const { isDarkMode } = useTheme();

defineEmits<{
    toggleSidebar: [];
    openTheme: [];
}>();

const page = usePage();
const menuOpen = ref(false);
const menuRef = ref<HTMLElement | null>(null);

const user = computed(() => page.props.auth?.user as { name?: string; email?: string } | null);

const userInitial = computed(() => {
    const name = user.value?.name?.trim();
    return name ? name.charAt(0).toUpperCase() : "A";
});

const logout = () => {
    menuOpen.value = false;
    router.post(route("logout"));
};

const handleClickOutside = (event: MouseEvent) => {
    if (menuRef.value && !menuRef.value.contains(event.target as Node)) {
        menuOpen.value = false;
    }
};

onMounted(() => document.addEventListener("click", handleClickOutside));
onUnmounted(() => document.removeEventListener("click", handleClickOutside));
</script>
