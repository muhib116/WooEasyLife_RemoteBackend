<script setup>
defineProps({
    features: { type: Array, default: () => [] },
});

const colorClass = (color) => {
    const map = {
        violet: 'border-amber-500/20 bg-amber-500/10 text-amber-300',
        emerald: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300',
        sky: 'border-sky-500/20 bg-sky-500/10 text-sky-300',
        amber: 'border-amber-500/20 bg-amber-500/10 text-amber-300',
        rose: 'border-rose-500/20 bg-rose-500/10 text-rose-300',
        cyan: 'border-cyan-500/20 bg-cyan-500/10 text-cyan-300',
        fuchsia: 'border-fuchsia-500/20 bg-fuchsia-500/10 text-fuchsia-300',
        lime: 'border-lime-500/20 bg-lime-500/10 text-lime-300',
    };

    return map[color] ?? map.violet;
};

const iconEmoji = (icon) => {
    const map = {
        shield: '🛡️',
        truck: '🚚',
        message: '💬',
        clipboard: '📋',
        spark: '✨',
        phone: '📞',
        users: '👥',
        globe: '🌐',
        bell: '🔔',
        check: '✓',
    };

    return map[icon] ?? '⭐';
};
</script>

<template>
    <section v-if="features.length" class="py-12 sm:py-16">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-white sm:text-3xl">
                    Popular features — এক নজরে
                </h2>
                <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-400 sm:text-base">
                    Featured plan-এ যা পাবেন — fraud check, courier automation, AI order আরও অনেক কিছু।
                </p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="feature in features"
                    :key="feature.key"
                    class="rounded-2xl border border-white/10 bg-white/5 p-5 transition hover:border-white/20"
                >
                    <span
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border text-lg"
                        :class="colorClass(feature.color)"
                    >
                        {{ iconEmoji(feature.icon) }}
                    </span>
                    <h3 class="mt-4 text-sm font-bold text-white">{{ feature.label }}</h3>
                    <p class="mt-2 text-xs leading-relaxed text-slate-400 sm:text-sm">
                        {{ feature.description }}
                    </p>
                </article>
            </div>
        </div>
    </section>
</template>
