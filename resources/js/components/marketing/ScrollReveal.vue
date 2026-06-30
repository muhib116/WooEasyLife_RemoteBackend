<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    delay: { type: Number, default: 0 },
    as: { type: String, default: 'div' },
});

const root = ref(null);
let observer;

onMounted(() => {
    const el = root.value;

    if (!el) {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        el.classList.add('scroll-reveal-visible');
        return;
    }

    observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('scroll-reveal-visible');
                observer?.unobserve(entry.target);
            }
        },
        { threshold: 0.08, rootMargin: '0px 0px -48px 0px' },
    );

    observer.observe(el);
});

onUnmounted(() => {
    observer?.disconnect();
});
</script>

<template>
    <component
        :is="as"
        ref="root"
        class="scroll-reveal"
        :style="delay ? { transitionDelay: `${delay}ms` } : undefined"
    >
        <slot />
    </component>
</template>
