<template>
    <header class="merchant-header sticky top-0 z-30 border-b backdrop-blur-md">
        <div class="flex h-16 shrink-0 items-center justify-between gap-4 px-4 lg:px-6">
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border transition hover:bg-[#ffc107]/15 lg:hidden"
                style="border-color: var(--mp-line); color: var(--mp-ink)"
                @click="$emit('toggleSidebar')"
            >
                <Icon name="PhList" class="text-xl" />
            </button>
            <div class="min-w-0">
                <p class="truncate text-base font-semibold" style="color: var(--mp-ink)">
                    {{ title }}
                </p>
                <p v-if="portal?.is_staff" class="truncate text-xs" style="color: var(--mp-muted)">
                    {{ portal.employee?.role }} ·
                    {{ portal.employee?.website_domain || "All websites" }}
                </p>
                <p v-else-if="accessLabel" class="truncate text-xs" style="color: var(--mp-muted)">
                    {{ accessLabel }}
                </p>
            </div>
        </div>

        <nav v-if="sectionTabs.length" class="hidden min-w-0 items-center justify-center gap-1 md:flex" aria-label="Section">
            <Link
                v-for="tab in sectionTabs"
                :key="tab.href"
                :href="tab.href"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="tab.active ? 'bg-[#ffc107] text-[#0a0a0a]' : 'hover:bg-[#ffc107]/15'"
                :style="tab.active ? undefined : { color: 'var(--mp-ink)' }"
            >
                {{ tab.title }}
            </Link>
        </nav>

        <div class="flex items-center gap-2">
            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border transition hover:bg-[#ffc107]/15"
                style="border-color: var(--mp-line); color: var(--mp-ink)"
                :title="isDark ? 'Light mode' : 'Dark mode'"
                @click="isDark = !isDark"
            >
                <Icon :name="isDark ? 'PhSun' : 'PhMoonStars'" class="text-lg" />
            </button>
        </div>
        </div>
        <nav v-if="sectionTabs.length" class="flex gap-2 px-4 pb-3 md:hidden" aria-label="Section">
            <Link
                v-for="tab in sectionTabs"
                :key="tab.href"
                :href="tab.href"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="tab.active ? 'bg-[#ffc107] text-[#0a0a0a]' : 'hover:bg-[#ffc107]/15'"
                :style="tab.active ? undefined : { color: 'var(--mp-ink)' }"
            >
                {{ tab.title }}
            </Link>
        </nav>
    </header>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { Link, usePage } from "@inertiajs/vue3";
import { useDark } from "@vueuse/core";
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
const isDark = useDark();

const sectionTabs = computed(() => {
    if (!route().current("portal.fraud-check*")) {
        return [];
    }

    return [
        {
            title: "New batch",
            href: route("portal.fraud-check"),
            active: route().current("portal.fraud-check"),
        },
        {
            title: "Saved batches",
            href: route("portal.fraud-check.batches.index"),
            active: route().current("portal.fraud-check.batches.*"),
        },
    ];
});
</script>
