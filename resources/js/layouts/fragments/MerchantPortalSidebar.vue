<template>
    <aside class="merchant-nav flex h-svh flex-col overflow-hidden" :class="{ 'is-rail': collapsed }">
        <div class="merchant-nav-brand">
            <div class="merchant-nav-logo">
                <img src="/app-logo" alt="" />
            </div>
            <div v-show="!collapsed" class="min-w-0 flex-1">
                <p class="merchant-nav-name">WooEasyLife</p>
                <p class="merchant-nav-meta">{{ portal?.merchant_name || "Merchant portal" }}</p>
            </div>
            <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-[#ffc107]/15 lg:hidden"
                style="color: var(--mp-muted)"
                aria-label="Close menu"
                @click="$emit('close')"
            >
                <Icon name="PhX" :size="16" />
            </button>
        </div>

        <nav class="merchant-nav-scroll" @scroll="closeFlyout">
            <ul class="merchant-nav-list">
                <li v-for="item in navItems" :key="item.title" class="w-full">
                    <div v-if="item.children?.length">
                        <button
                            type="button"
                            class="merchant-nav-link"
                            :class="{ 'merchant-nav-group-active': isGroupActive(item) || (collapsed && flyout?.title === item.title) }"
                            :aria-expanded="collapsed ? flyout?.title === item.title : isOpen(item)"
                            :aria-label="item.title"
                            data-sidebar-flyout-trigger
                            @click="onGroupActivate($event, item)"
                        >
                            <span class="merchant-nav-icon">
                                <Icon :name="item.icon" :size="18" />
                            </span>
                            <span v-show="!collapsed" class="min-w-0 flex-1 truncate">{{ item.title }}</span>
                            <Icon
                                v-show="!collapsed"
                                name="PhCaretDown"
                                :size="14"
                                class="merchant-nav-caret"
                                :class="isOpen(item) ? 'rotate-180' : ''"
                            />
                        </button>
                        <ul v-show="!collapsed && isOpen(item)" class="merchant-nav-sub">
                            <li v-for="child in item.children" :key="child.name">
                                <Link
                                    :href="route(child.name)"
                                    class="merchant-nav-link"
                                    :class="{ 'merchant-nav-active': route().current(child.active ?? child.name) }"
                                    @click="$emit('close')"
                                >
                                    <span class="merchant-nav-icon">
                                        <Icon :name="child.icon" :size="16" />
                                    </span>
                                    <span class="truncate">{{ child.title }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <Link
                        v-else-if="item.name"
                        :href="route(item.name)"
                        class="merchant-nav-link"
                        :class="{ 'merchant-nav-active': route().current(item.active ?? item.name) }"
                        :aria-label="item.title"
                        @click="$emit('close')"
                    >
                        <span class="merchant-nav-icon">
                            <Icon :name="item.icon" :size="18" />
                        </span>
                        <span v-show="!collapsed" class="truncate">{{ item.title }}</span>
                    </Link>
                </li>
            </ul>
        </nav>

        <div class="merchant-nav-foot">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="merchant-nav-link merchant-logout"
                aria-label="Log out"
            >
                <span class="merchant-nav-icon">
                    <Icon name="PhSignOut" :size="18" />
                </span>
                <span v-show="!collapsed">Log out</span>
            </Link>
        </div>

        <Teleport to="body">
            <div
                v-if="collapsed && flyout"
                ref="flyoutRef"
                class="merchant-flyout"
                :style="{ top: `${flyout.top}px`, left: `${flyout.left}px` }"
            >
                <p class="merchant-flyout-label">{{ flyout.title }}</p>
                <Link
                    v-for="child in flyout.children"
                    :key="child.name"
                    :href="route(child.name)"
                    class="merchant-nav-link"
                    :class="{ 'merchant-nav-active': route().current(child.active ?? child.name) }"
                    @click="closeMenus"
                >
                    <span class="merchant-nav-icon">
                        <Icon :name="child.icon" :size="16" />
                    </span>
                    <span class="truncate">{{ child.title }}</span>
                </Link>
            </div>
        </Teleport>
    </aside>
</template>

<script setup lang="ts">
import { useMerchantPortalNav, type MerchantNavItem } from "@/composables/useMerchantPortalNav";
import { Icon } from "@/plugins";
import { Link, usePage } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";

const props = withDefaults(
    defineProps<{
        collapsed?: boolean;
    }>(),
    {
        collapsed: false,
    },
);

const emit = defineEmits<{
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

const flyout = ref<{
    title: string;
    top: number;
    left: number;
    children: NonNullable<MerchantNavItem["children"]>;
} | null>(null);
const flyoutRef = ref<HTMLElement | null>(null);

const closeFlyout = () => {
    flyout.value = null;
};

const closeMenus = () => {
    closeFlyout();
    emit("close");
};

const openFlyout = async (event: MouseEvent, item: MerchantNavItem) => {
    if (!item.children?.length) {
        return;
    }

    if (flyout.value?.title === item.title) {
        closeFlyout();
        return;
    }

    const trigger = event.currentTarget as HTMLElement;
    const rect = trigger.getBoundingClientRect();
    const rail = trigger.closest(".merchant-sidebar")?.getBoundingClientRect();
    const left = Math.round((rail?.right ?? rect.right) + 10);

    flyout.value = {
        title: item.title,
        top: Math.round(rect.top),
        left,
        children: item.children,
    };

    await nextTick();
    const menu = flyoutRef.value;
    if (!menu || !flyout.value) {
        return;
    }

    const height = menu.offsetHeight;
    flyout.value.top = Math.round(
        Math.max(12, Math.min(rect.top + rect.height / 2 - height / 2, window.innerHeight - height - 12)),
    );
};

const onGroupActivate = (event: MouseEvent, item: MerchantNavItem) => {
    if (props.collapsed) {
        openFlyout(event, item);
        return;
    }

    toggle(item.title);
};

const onDocumentPointerDown = (event: MouseEvent) => {
    if (!flyout.value) {
        return;
    }

    const target = event.target as HTMLElement | null;
    if (!target || flyoutRef.value?.contains(target) || target.closest("[data-sidebar-flyout-trigger]")) {
        return;
    }

    closeFlyout();
};

const onEscape = (event: KeyboardEvent) => {
    if (event.key === "Escape") {
        closeFlyout();
    }
};

watch(
    () => props.collapsed,
    () => closeFlyout(),
);

watch(
    () => page.url,
    () => closeFlyout(),
);

onMounted(() => {
    document.addEventListener("pointerdown", onDocumentPointerDown);
    document.addEventListener("keydown", onEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener("pointerdown", onDocumentPointerDown);
    document.removeEventListener("keydown", onEscape);
});
</script>
