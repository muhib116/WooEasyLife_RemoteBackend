<script setup>
defineProps({
    section: { type: Object, default: () => ({}) },
});

const icons = {
    woocommerce: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
    courier: 'M9 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1',
    sms: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    app: 'M12 18h.01M8 21h8a1 1 0 001-1V4a1 1 0 00-1-1H8a1 1 0 00-1 1v16a1 1 0 001 1z',
    api: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4',
    ai: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
};

const accentTile = (accent) => {
    const map = {
        fuchsia: 'bg-fuchsia-500/15 text-fuchsia-300',
        amber: 'bg-amber-500/15 text-amber-300',
        emerald: 'bg-emerald-500/15 text-emerald-300',
        sky: 'bg-sky-500/15 text-sky-300',
        rose: 'bg-rose-500/15 text-rose-300',
        violet: 'bg-violet-500/15 text-violet-300',
    };

    return map[accent] ?? map.violet;
};
</script>

<template>
    <section v-if="section?.items?.length" id="integrations" class="scroll-mt-24 py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="text-center">
                <span
                    v-if="section.badge"
                    class="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-bold text-slate-300"
                >
                    {{ section.badge }}
                </span>
                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl lg:text-4xl">
                    {{ section.headline }}
                </h2>
                <p v-if="section.subtitle" class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    {{ section.subtitle }}
                </p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="item in section.items"
                    :key="item.title"
                    class="rounded-2xl border border-white/10 bg-white/5 p-5 transition hover:border-white/20 hover:bg-white/[0.07]"
                >
                    <span
                        class="flex h-11 w-11 items-center justify-center rounded-xl"
                        :class="accentTile(item.accent)"
                    >
                        <svg class="h-5.5 w-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="height:1.35rem;width:1.35rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icons[item.icon] ?? icons.api" />
                        </svg>
                    </span>
                    <h3 class="mt-4 text-base font-bold text-white">{{ item.title }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-400">{{ item.description }}</p>
                </article>
            </div>
        </div>
    </section>
</template>
