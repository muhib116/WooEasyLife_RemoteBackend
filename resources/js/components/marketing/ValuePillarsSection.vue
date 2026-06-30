<script setup>
defineProps({
    pillars: { type: Array, default: () => [] },
});

const accentStyles = (accent) => {
    const map = {
        emerald: {
            badge: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300',
            border: 'border-emerald-500/20 hover:border-emerald-500/35',
            bg: 'from-emerald-950/20 to-[#111111]',
            dot: 'text-emerald-400',
        },
        amber: {
            badge: 'border-amber-400/30 bg-amber-500/10 text-amber-300',
            border: 'border-amber-500/20 hover:border-amber-500/35',
            bg: 'from-amber-950/20 to-[#111111]',
            dot: 'text-amber-400',
        },
        sky: {
            badge: 'border-sky-400/30 bg-sky-500/10 text-sky-300',
            border: 'border-sky-500/20 hover:border-sky-500/35',
            bg: 'from-sky-950/20 to-[#111111]',
            dot: 'text-sky-400',
        },
        violet: {
            badge: 'border-amber-400/30 bg-amber-500/10 text-amber-300',
            border: 'border-amber-500/20 hover:border-amber-500/35',
            bg: 'from-amber-950/20 to-[#111111]',
            dot: 'text-amber-400',
        },
        fuchsia: {
            badge: 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-300',
            border: 'border-fuchsia-500/20 hover:border-fuchsia-500/35',
            bg: 'from-fuchsia-950/20 to-[#111111]',
            dot: 'text-fuchsia-400',
        },
    };

    return map[accent] ?? map.violet;
};
</script>

<template>
    <section v-if="pillars.length" class="border-y border-white/10 bg-[#111111] py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="text-center">
                <span class="inline-flex rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-300">
                    মূল সুবিধা
                </span>
                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl lg:text-4xl">
                    ইউনিক ফিচার যা business-কে সহজ ও profitable করে
                </h2>
                <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    Fraud protection থেকে AI order, multi-website management — সব এক platform-এ।
                </p>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-2">
                <article
                    v-for="pillar in pillars"
                    :key="pillar.id"
                    class="rounded-2xl border bg-gradient-to-br p-5 sm:p-6"
                    :class="[accentStyles(pillar.accent).border, accentStyles(pillar.accent).bg]"
                >
                    <span
                        class="inline-flex rounded-full border px-3 py-1 text-xs font-bold"
                        :class="accentStyles(pillar.accent).badge"
                    >
                        {{ pillar.badge }}
                    </span>
                    <h3 class="mt-4 text-lg font-bold text-white sm:text-xl">
                        {{ pillar.headline }}
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">
                        {{ pillar.subheadline }}
                    </p>
                    <ul v-if="pillar.features?.length" class="mt-5 flex flex-wrap gap-2">
                        <li
                            v-for="feature in pillar.features"
                            :key="feature.key"
                            class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-black/20 px-3 py-1.5 text-xs text-slate-300"
                        >
                            <span :class="accentStyles(pillar.accent).dot">✓</span>
                            {{ feature.label }}
                        </li>
                    </ul>
                </article>
            </div>
        </div>
    </section>
</template>
