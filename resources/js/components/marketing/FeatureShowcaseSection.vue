<script setup>
defineProps({
    showcases: { type: Array, default: () => [] },
});

const accentStyles = (accent) => {
    const map = {
        emerald: {
            badge: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300',
            border: 'border-emerald-500/20',
            bg: 'from-emerald-950/30 to-[#0a0f1c]',
            dot: 'text-emerald-400',
            benefit: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-200',
        },
        amber: {
            badge: 'border-amber-400/30 bg-amber-500/10 text-amber-300',
            border: 'border-amber-500/20',
            bg: 'from-amber-950/30 to-[#0a0f1c]',
            dot: 'text-amber-400',
            benefit: 'border-amber-500/20 bg-amber-500/10 text-amber-200',
        },
        sky: {
            badge: 'border-sky-400/30 bg-sky-500/10 text-sky-300',
            border: 'border-sky-500/20',
            bg: 'from-sky-950/30 to-[#0a0f1c]',
            dot: 'text-sky-400',
            benefit: 'border-sky-500/20 bg-sky-500/10 text-sky-200',
        },
        violet: {
            badge: 'border-violet-400/30 bg-violet-500/10 text-violet-300',
            border: 'border-violet-500/20',
            bg: 'from-violet-950/30 to-[#0a0f1c]',
            dot: 'text-violet-400',
            benefit: 'border-violet-500/20 bg-violet-500/10 text-violet-200',
        },
        fuchsia: {
            badge: 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-300',
            border: 'border-fuchsia-500/20',
            bg: 'from-fuchsia-950/30 to-[#0a0f1c]',
            dot: 'text-fuchsia-400',
            benefit: 'border-fuchsia-500/20 bg-fuchsia-500/10 text-fuchsia-200',
        },
        cyan: {
            badge: 'border-cyan-400/30 bg-cyan-500/10 text-cyan-300',
            border: 'border-cyan-500/20',
            bg: 'from-cyan-950/30 to-[#0a0f1c]',
            dot: 'text-cyan-400',
            benefit: 'border-cyan-500/20 bg-cyan-500/10 text-cyan-200',
        },
    };

    return map[accent] ?? map.violet;
};
</script>

<template>
    <section id="features" class="scroll-mt-24 py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-white sm:text-3xl lg:text-4xl">
                    আপনি কী পাবেন — বিস্তারিত
                </h2>
                <p class="mt-3 text-sm text-slate-400 sm:text-base">
                    প্রতিটি ফিচারে ব্যথা, সমাধান ও লাভ — স্পষ্টভাবে
                </p>
            </div>

            <div class="mt-10 space-y-8 sm:space-y-12">
                <article
                    v-for="(showcase, index) in showcases"
                    :key="showcase.id"
                    class="overflow-hidden rounded-3xl border bg-gradient-to-br"
                    :class="[accentStyles(showcase.accent).border, accentStyles(showcase.accent).bg]"
                >
                    <div
                        class="grid gap-6 p-5 sm:p-7 lg:grid-cols-2 lg:gap-8 lg:p-8"
                        :class="index % 2 === 1 ? 'lg:[&>*:first-child]:order-2' : ''"
                    >
                        <div>
                            <span
                                class="inline-flex rounded-full border px-3 py-1 text-xs font-bold"
                                :class="accentStyles(showcase.accent).badge"
                            >
                                {{ showcase.badge }}
                            </span>
                            <h3 class="mt-4 text-xl font-bold leading-snug text-white sm:text-2xl">
                                {{ showcase.headline }}
                            </h3>

                            <div class="mt-5 space-y-3">
                                <div class="rounded-xl border border-rose-500/20 bg-rose-950/20 px-4 py-3">
                                    <p class="text-xs font-bold uppercase tracking-wide text-rose-400">সমস্যা</p>
                                    <p class="mt-1 text-sm leading-relaxed text-rose-100/90">{{ showcase.pain }}</p>
                                </div>
                                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">সমাধান</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-200">{{ showcase.solution }}</p>
                                </div>
                                <div
                                    class="rounded-xl border px-4 py-3"
                                    :class="accentStyles(showcase.accent).benefit"
                                >
                                    <p class="text-xs font-bold uppercase tracking-wide opacity-80">লাভ</p>
                                    <p class="mt-1 text-sm font-medium leading-relaxed">{{ showcase.benefit }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-black/20 p-4 sm:p-5">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">অন্তর্ভুক্ত ফিচার</p>
                            <ul class="mt-4 space-y-3">
                                <li
                                    v-for="feat in showcase.features"
                                    :key="feat.key"
                                    class="flex gap-3 rounded-xl border border-white/10 bg-white/5 p-3"
                                >
                                    <span class="shrink-0 font-bold" :class="accentStyles(showcase.accent).dot">✦</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-white">{{ feat.label }}</p>
                                        <p class="mt-0.5 text-xs leading-relaxed text-slate-400">{{ feat.description }}</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
