<template>
    <div
        v-autoScrollTop
        @scroll="handleScroll"
        ref="scrollElement"
        class="overflow-auto relative"
    >
        <slot></slot>
        <button
            v-show="scrollFromTop >= offset"
            class="box-bg box-color sticky bottom-4 left-1/2 z-50 box-border grid h-10 w-10 -translate-x-1/2 place-content-center rounded-full border"
            :class="{
                'zoom-in': scrollFromTop >= offset,
            }"
            @click="scrollByButton(scrollElement)"
        >
            <Icon name="PhCaretDown" size="17" />
        </button>
    </div>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { ref } from "vue";

const props = withDefaults(
    defineProps<{
        offset?: number;
        autoScroll: boolean;
    }>(),
    {
        offset: 200,
        autoScroll: true,
    },
);
const scrollFromTop = ref(0);
const scrollElement = ref<HTMLElement | null>(null);
const isUserScrolling = ref(false); // To detect if the user is manually scrolling

// Handle scroll event
const handleScroll = (event: Event) => {
    const el = event.target as HTMLElement;
    scrollFromTop.value = el.scrollHeight - el.scrollTop - el.clientHeight;

    // If the user scrolls manually (above the offset), detect it and disable auto-scroll
    if (scrollFromTop.value > (props.offset || 200)) {
        isUserScrolling.value = true;
    }
};

// Function to scroll to the bottom programmatically (auto-scroll)
const handleScrollTop = (el?: HTMLElement | null) => {
    // Only auto-scroll if the user isn't manually scrolling
    if (el && !isUserScrolling.value) {
        el.scrollTo({
            top: el.scrollHeight,
            behavior: "smooth",
        });
    }
};

const scrollByButton = (el?: HTMLElement | null) => {
    if (el) {
        el.scrollTo({
            top: el.scrollHeight,
            behavior: "smooth",
        });
    }
};

// Vue directive for auto-scroll
const vAutoScrollTop = {
    mounted(el: HTMLElement) {
        scrollElement.value = el;
        el.addEventListener("scroll", handleScroll);

        if (props.autoScroll) {
            handleScrollTop(el);

            const observer = new MutationObserver(() => {
                handleScrollTop(el);
            });
            observer.observe(el, {
                childList: true,
                subtree: true,
            });
            (el as any)._observer = observer;
        }
    },
    unmounted(el: HTMLElement) {
        el.removeEventListener("scroll", handleScroll);
        if (props.autoScroll) {
            if ((el as any)._observer) {
                (el as any)._observer.disconnect();
            }
        }
    },
};
</script>

<style scoped>
@keyframes zoomIn {
    0% {
        transform: scale(0.5);
        opacity: 0;
    }

    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.zoom-in {
    animation: zoomIn 0.2s ease-out forwards;
}
</style>
