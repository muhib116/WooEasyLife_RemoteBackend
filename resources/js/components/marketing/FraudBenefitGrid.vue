<script setup>
defineProps({
    section: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section v-if="section.cards?.length" class="py-12 sm:py-16">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="text-center">
                <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-300">
                    Fraud Protection
                </span>
                <h2 v-if="section.headline" class="mt-4 text-2xl font-bold text-white sm:text-3xl">
                    {{ section.headline }}
                </h2>
                <p v-if="section.subtitle" class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    {{ section.subtitle }}
                </p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="card in section.cards"
                    :key="card.title"
                    class="flex flex-col rounded-2xl border border-white/10 bg-white/5 p-5 transition hover:border-emerald-500/25 hover:bg-emerald-500/5"
                >
                    <span class="text-3xl">{{ card.icon }}</span>
                    <h3 class="mt-4 text-base font-bold text-white">{{ card.title }}</h3>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-400">
                        {{ card.description }}
                    </p>
                    <a
                        v-if="card.cta"
                        :href="card.cta"
                        class="mt-4 inline-flex items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/20"
                    >
                        {{ card.cta_label ?? 'আরও দেখুন' }}
                    </a>
                </article>
            </div>
        </div>
    </section>
</template>
