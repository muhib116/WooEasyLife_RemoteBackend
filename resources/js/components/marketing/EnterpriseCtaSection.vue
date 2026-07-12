<script setup>
defineProps({
    cta: { type: Object, default: () => ({}) },
    contactUrl: { type: String, default: null },
});
</script>

<template>
    <section v-if="cta?.title" class="px-4 pb-4 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <div class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/5 p-5 sm:flex-row sm:p-6">
                <div class="flex items-center gap-4 text-center sm:text-left">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-yellow-500 text-black shadow-lg shadow-amber-900/40">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 8l4.5 3.5L12 5l4.5 6.5L21 8l-1.7 10.2a1 1 0 01-.99.8H5.69a1 1 0 01-.99-.8L3 8zm2.86 9h12.28l.2-1.2H5.66l.2 1.2z" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-lg font-bold text-white">{{ cta.title }}</p>
                        <p v-if="cta.subtitle" class="mt-0.5 text-sm text-slate-400">{{ cta.subtitle }}</p>
                    </div>
                </div>

                <a
                    v-if="contactUrl"
                    :href="contactUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-slate-900 shadow-lg transition hover:bg-slate-100 sm:w-auto"
                >
                    {{ cta.button_label ?? 'সেলস টিমের সাথে কথা বলুন' }}
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </a>
            </div>
        </div>
    </section>
</template>
