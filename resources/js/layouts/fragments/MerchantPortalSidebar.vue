<template>
    <aside class="flex h-svh flex-col overflow-hidden">
        <div class="sticky top-0 z-10 flex h-16 shrink-0 items-center gap-3 border-b px-5" style="border-color: var(--mp-line)">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[#ffc107]/40 bg-[#ffc107]/15">
                <img src="/app-logo" alt="" class="h-7 w-7 object-contain" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold" style="color: var(--mp-ink)">
                    WooEasyLife
                </p>
                <p class="truncate text-[10px] font-medium uppercase tracking-wider text-[#c49000] dark:text-[#ffd54f]">
                    {{ portal?.merchant_name || "Merchant portal" }}
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-[#ffc107]/15 lg:hidden"
                style="color: var(--mp-muted)"
                @click="$emit('close')"
            >
                <Icon name="PhX" />
            </button>
        </div>

        <nav class="admin-scrollbar min-h-0 flex-1 overflow-y-auto px-3 py-4">
            <ul class="space-y-1">
                <li v-for="item in navItems" :key="item.title">
                    <div v-if="item.children?.length" class="space-y-1">
                        <button
                            type="button"
                            class="merchant-nav-link group flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all"
                            :class="{ 'merchant-nav-group-active': isGroupActive(item) }"
                            :aria-expanded="isOpen(item)"
                            @click="toggle(item.title)"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="merchant-nav-icon flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
                                    <Icon :name="item.icon" class="text-lg" />
                                </span>
                                <span class="truncate">{{ item.title }}</span>
                            </span>
                            <Icon
                                name="PhCaretDown"
                                :size="14"
                                class="shrink-0 transition-transform duration-200"
                                :class="isOpen(item) ? 'rotate-180' : ''"
                            />
                        </button>
                        <ul v-show="isOpen(item)" class="space-y-1 pl-3">
                            <li v-for="child in item.children" :key="child.name">
                                <Link
                                    :href="route(child.name)"
                                    class="merchant-nav-link group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition-all"
                                    :class="{ 'merchant-nav-active': route().current(child.active ?? child.name) }"
                                    @click="$emit('close')"
                                >
                                    <span class="merchant-nav-icon flex h-7 w-7 shrink-0 items-center justify-center rounded-lg">
                                        <Icon :name="child.icon" class="text-base" />
                                    </span>
                                    <span>{{ child.title }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <Link
                        v-else-if="item.name"
                        :href="route(item.name)"
                        class="merchant-nav-link group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all"
                        :class="{ 'merchant-nav-active': route().current(item.active ?? item.name) }"
                        @click="$emit('close')"
                    >
                        <span class="merchant-nav-icon flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
                            <Icon :name="item.icon" class="text-lg" />
                        </span>
                        <span>{{ item.title }}</span>
                    </Link>
                </li>
            </ul>
        </nav>

        <div class="shrink-0 border-t px-3 py-3" style="border-color: var(--mp-line)">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="flex w-full items-center justify-center rounded-lg border px-3 py-2.5 text-sm font-semibold text-[#0a0a0a] transition hover:brightness-105"
                style="background: linear-gradient(90deg, #f59e0b, #eab308); border-color: #e6a800"
            >
                Log out
            </Link>
        </div>
    </aside>
</template>

<script setup lang="ts">
import { useMerchantPortalNav } from "@/composables/useMerchantPortalNav";
import { Icon } from "@/plugins";
import { Link, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

defineEmits<{
    close: [];
}>();

const page = usePage();
const portal = computed(() => (page.props.auth as any)?.portal);
const { navItems } = useMerchantPortalNav();
const openGroups = ref<Record<string, boolean>>({ Settings: true });

const isGroupActive = (item: { children?: Array<{ name: string; active?: string }> }) =>
    item.children?.some((child) => route().current(child.active ?? child.name)) ?? false;

const isOpen = (item: { title: string; children?: Array<{ name: string; active?: string }> }) =>
    openGroups.value[item.title] ?? isGroupActive(item);

const toggle = (title: string) => {
    const item = navItems.value.find((entry) => entry.title === title);
    const currently = item ? isOpen(item) : false;
    openGroups.value = { ...openGroups.value, [title]: !currently };
};
</script>
