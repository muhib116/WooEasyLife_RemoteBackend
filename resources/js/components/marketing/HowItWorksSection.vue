<script setup>
defineProps({
    steps: { type: Array, default: () => [] },
});
</script>

<template>
    <section v-if="steps.length" id="how-it-works" class="scroll-mt-24 border-t border-white/10 bg-[#111111] py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-white sm:text-3xl lg:text-4xl">কিভাবে কাজ করে?</h2>
                <p class="mt-3 text-sm text-slate-400 sm:text-base">মাত্র ৪ ধাপে শুরু — সহজ ও দ্রুত</p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
                <div
                    v-for="(item, index) in steps"
                    :key="item.step"
                    class="relative rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-6"
                >
                    <span
                        v-if="index < steps.length - 1"
                        class="pointer-events-none absolute -right-2 top-1/2 hidden h-0.5 w-4 bg-amber-500/30 lg:block"
                    />
                    <span class="text-3xl font-extrabold text-amber-500/50">{{ item.step }}</span>
                    <h3 class="mt-3 text-base font-bold text-white sm:text-lg">{{ item.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ item.description }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
