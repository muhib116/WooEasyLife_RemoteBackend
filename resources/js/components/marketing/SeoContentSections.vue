<script setup>
defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <section
        v-if="sections?.length"
        class="border-t border-white/10 bg-[#0a0a0a] px-4 py-12 lg:px-8"
        data-seo-longform
    >
        <div class="mx-auto max-w-3xl space-y-8">
            <article
                v-for="(section, index) in sections"
                :key="section.heading || index"
                class="space-y-3"
            >
                <h2
                    v-if="section.heading"
                    class="text-xl font-bold text-white sm:text-2xl"
                >
                    {{ section.heading }}
                </h2>
                <p
                    v-for="(paragraph, pIndex) in section.paragraphs || []"
                    :key="pIndex"
                    class="text-sm leading-relaxed text-slate-300 sm:text-base"
                >
                    {{ paragraph }}
                </p>
            </article>
        </div>
    </section>
</template>
