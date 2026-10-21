import { onBeforeUnmount, onMounted, ref } from "vue";

const DESKTOP = "(min-width: 1024px)";

export function useCollapsibleSidebar(storageKey: string) {
    const sidebarOpen = ref(false);
    const sidebarCollapsed = ref(readCollapsed(storageKey));
    const isDesktop = ref(typeof window !== "undefined" && window.matchMedia(DESKTOP).matches);

    const syncDesktop = () => {
        isDesktop.value = window.matchMedia(DESKTOP).matches;
    };

    onMounted(() => {
        syncDesktop();
        window.addEventListener("resize", syncDesktop);
    });

    onBeforeUnmount(() => {
        window.removeEventListener("resize", syncDesktop);
    });

    function toggleSidebar() {
        if (isDesktop.value) {
            sidebarCollapsed.value = !sidebarCollapsed.value;
            try {
                localStorage.setItem(storageKey, sidebarCollapsed.value ? "1" : "0");
            } catch {
                // Private mode can block storage; the toggle still applies this session.
            }
            return;
        }

        sidebarOpen.value = !sidebarOpen.value;
    }

    return {
        sidebarOpen,
        sidebarCollapsed,
        isDesktop,
        toggleSidebar,
    };
}

function readCollapsed(storageKey: string) {
    if (typeof window === "undefined") {
        return false;
    }

    try {
        return localStorage.getItem(storageKey) === "1";
    } catch {
        return false;
    }
}
