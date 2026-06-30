<script setup>
defineProps({
    scenarios: { type: Array, default: () => [] },
});

const accentClass = (accent) => {
    const map = {
        rose: 'border-rose-500/25 bg-rose-950/20',
        amber: 'border-amber-500/25 bg-amber-950/20',
        sky: 'border-sky-500/25 bg-sky-950/20',
        violet: 'border-amber-500/25 bg-amber-950/20',
        emerald: 'border-emerald-500/25 bg-emerald-950/20',
    };

    return map[accent] ?? map.violet;
};

const titleClass = (accent) => {
    const map = {
        rose: 'text-rose-300',
        amber: 'text-amber-300',
        sky: 'text-sky-300',
        violet: 'text-amber-300',
        emerald: 'text-emerald-300',
    };

    return map[accent] ?? map.violet;
};
</script>

<template>
    <section v-if="scenarios.length" class="border-y border-white/10 bg-[#111111] py-14 sm:py-16">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="text-center">
                <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                    টাকা ও সময় হিসাব
                </span>
                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl lg:text-4xl">
                    মাসে কত টাকা ও সময় বাঁচবেন?
                </h2>
                <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    আপনার ব্যবসায় সংখ্যা ভিন্ন হতে পারে — নিচের হিসাব বাংলাদেশি মার্চেন্টদের গড় অভিজ্ঞতা।
                </p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <article
                    v-for="item in scenarios"
                    :key="item.title"
                    class="rounded-2xl border p-5 sm:p-6"
                    :class="accentClass(item.accent)"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">{{ item.icon }}</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-bold" :class="titleClass(item.accent)">
                                {{ item.title }}
                            </h3>
                            <p class="mt-2 text-sm font-medium leading-relaxed text-slate-200">
                                {{ item.calculation }}
                            </p>
                            <p class="mt-3 rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-sm leading-relaxed text-emerald-200/90">
                                ✓ {{ item.benefit }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
