<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    /** Match SeoMetaService ItemList anchors (#guide-section-N), skipping quick/AI stubs. */
    useGuideAnchors: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const isEn = computed(() => String(page.props.seo?.html_lang || '').startsWith('en'));

const isSkippedForGuideToc = (heading) => {
    const h = String(heading || '').trim();
    if (!h) return true;
    const lower = h.toLowerCase();
    if (h.includes('দ্রুত') || lower.includes('quick')) return true;
    if (h.includes('এআই সারাংশ') || lower.includes('ai summary')) return true;
    return false;
};

const normalizedSections = computed(() => {
    let tocPosition = 0;
    return (props.sections || []).map((section, index) => {
        let id = `seo-section-${index + 1}`;
        if (props.useGuideAnchors) {
            if (isSkippedForGuideToc(section?.heading)) {
                id = index === 0 ? 'guide-section-quick' : `guide-section-skip-${index + 1}`;
            } else {
                tocPosition += 1;
                id = `guide-section-${tocPosition}`;
            }
        }
        return { section, id };
    });
});
</script>

<template>
    <section
        v-if="sections?.length"
        class="border-t border-white/10 bg-[#0a0a0a] px-4 py-12 lg:px-8"
        data-seo-longform
    >
        <div class="mx-auto max-w-3xl space-y-10">
            <article
                v-for="{ section, id } in normalizedSections"
                :id="id"
                :key="id"
                class="scroll-mt-28 space-y-3"
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
                    <LinkedRichText :text="paragraph" :is-en="isEn" />
                </p>
                <ol
                    v-if="section.list?.length"
                    class="mt-1 list-decimal space-y-2 pl-5 text-sm leading-relaxed text-slate-300 sm:text-base"
                >
                    <li
                        v-for="(item, lIndex) in section.list"
                        :key="`list-${lIndex}`"
                        class="pl-1"
                    >
                        <LinkedRichText :text="item" :is-en="isEn" />
                    </li>
                </ol>
                <figure
                    v-for="(figure, fIndex) in section.figures || []"
                    :key="`${id}-fig-${fIndex}`"
                    class="overflow-hidden rounded-xl border border-white/10 bg-white/5"
                >
                    <img
                        :src="figure.src"
                        :alt="figure.alt || section.heading || 'Diagram'"
                        class="h-auto w-full"
                        loading="lazy"
                        decoding="async"
                    />
                    <figcaption
                        v-if="figure.caption"
                        class="border-t border-white/10 px-3 py-2 text-xs text-slate-400 sm:text-sm"
                    >
                        {{ figure.caption }}
                    </figcaption>
                </figure>
            </article>
        </div>
    </section>
</template>
