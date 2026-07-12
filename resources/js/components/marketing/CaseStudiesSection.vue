<script setup>
defineProps({
    section: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section v-if="section.items?.length" class="py-12 sm:py-16">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="text-center">
                <span class="inline-flex rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-300">
                    {{ section.badge || 'সেলারদের অভিজ্ঞতা' }}
                </span>
                <h2 v-if="section.headline" class="mt-4 text-2xl font-bold text-white sm:text-3xl">
                    {{ section.headline }}
                </h2>
                <p v-if="section.subtitle" class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    {{ section.subtitle }}
                </p>
            </div>

            <div class="mt-10 grid gap-4 md:grid-cols-3">
                <article
                    v-for="item in section.items"
                    :key="item.name"
                    class="flex flex-col rounded-2xl border border-white/10 bg-white/5 p-5 transition hover:border-amber-500/25 hover:bg-amber-500/5"
                >
                    <p class="text-2xl font-extrabold text-amber-300">{{ item.savings }}</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ item.period || 'মাসিক সাশ্রয়' }}
                    </p>
                    <p class="mt-4 flex-1 text-sm leading-relaxed text-slate-300">
                        “{{ item.quote }}”
                    </p>
                    <div class="mt-5 border-t border-white/10 pt-4">
                        <p class="text-sm font-semibold text-white">{{ item.name }}</p>
                        <p v-if="item.role" class="mt-0.5 text-xs text-slate-500">{{ item.role }}</p>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
