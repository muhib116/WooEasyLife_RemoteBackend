<template>
    <div class="space-y-4 sm:space-y-5">
        <template v-for="(block, bi) in blocks" :key="bi">
            <p
                v-if="block.type === 'paragraph'"
                class="break-words text-[0.95rem] leading-7 text-slate-300 sm:text-base sm:leading-8"
                :class="leadFirst && bi === 0 ? 'text-slate-200' : ''"
            >
                <LinkedRichText :text="block.text" :is-en="isEn" />
            </p>

            <h4
                v-else-if="block.type === 'subtitle'"
                class="scroll-mt-28 border-l-2 border-amber-400/70 pl-3 text-base font-bold leading-snug text-amber-100 sm:text-lg"
            >
                {{ block.text }}
            </h4>

            <!-- Explanatory diagram -->
            <figure
                v-else-if="block.type === 'figure'"
                class="-mx-1 overflow-hidden rounded-2xl border border-white/10 bg-[#121212] sm:mx-0"
            >
                <img
                    :src="block.src"
                    :alt="block.alt || block.caption || (isEn ? 'Diagram' : 'ডায়াগ্রাম')"
                    class="block h-auto w-full object-cover"
                    loading="lazy"
                    decoding="async"
                    width="1200"
                    height="675"
                />
                <figcaption
                    v-if="block.caption"
                    class="border-t border-white/10 px-3 py-2.5 text-xs leading-relaxed text-slate-400 sm:px-4 sm:text-sm"
                >
                    {{ block.caption }}
                </figcaption>
            </figure>

            <!-- Arrow / process flow -->
            <div
                v-else-if="block.type === 'flow'"
                class="rounded-2xl border border-sky-400/20 bg-sky-950/20 p-3 sm:p-4"
            >
                <p class="mb-3 text-[11px] font-bold uppercase tracking-[0.14em] text-sky-300/80">
                    {{ isEn ? 'Process flow' : 'প্রসেস ফ্লো' }}
                </p>

                <!-- Vertical timeline (default) — never overlaps long BN text -->
                <ol class="space-y-0 xl:hidden">
                    <li
                        v-for="(step, si) in block.steps"
                        :key="`v-${si}`"
                        class="relative flex gap-3"
                        :class="si < block.steps.length - 1 ? 'pb-4' : ''"
                    >
                        <div class="flex w-7 shrink-0 flex-col items-center">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full border border-sky-400/40 bg-sky-500/20 text-[11px] font-bold text-sky-100">
                                {{ si + 1 }}
                            </span>
                            <span
                                v-if="si < block.steps.length - 1"
                                class="mt-1 w-px flex-1 min-h-[0.75rem] bg-sky-400/30"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0 flex-1 rounded-xl border border-white/10 bg-black/30 px-3 py-2.5">
                            <p class="break-words text-sm font-medium leading-snug text-slate-200">
                                <LinkedRichText :text="step" :is-en="isEn" />
                            </p>
                        </div>
                    </li>
                </ol>

                <!-- Wide desktop: equal cards + arrows between (not inside cards) -->
                <div class="hidden xl:flex xl:items-stretch xl:gap-2">
                    <template v-for="(step, si) in block.steps" :key="`h-${si}`">
                        <div class="flex min-w-0 flex-1 flex-col gap-2 rounded-xl border border-white/10 bg-black/30 p-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full border border-sky-400/40 bg-sky-500/20 text-[11px] font-bold text-sky-100">
                                {{ si + 1 }}
                            </span>
                            <p class="break-words text-sm font-medium leading-snug text-slate-200">
                                <LinkedRichText :text="step" :is-en="isEn" />
                            </p>
                        </div>
                        <div
                            v-if="si < block.steps.length - 1"
                            class="flex w-6 shrink-0 items-center justify-center text-sky-400/80"
                            aria-hidden="true"
                        >
                            →
                        </div>
                    </template>
                </div>
            </div>

            <!-- Hub → branch tree (reference-style diagram) -->
            <div
                v-else-if="block.type === 'tree'"
                class="overflow-x-auto rounded-2xl border border-white/10 bg-[#151515] p-4 sm:p-5"
            >
                <div class="flex min-w-0 flex-col gap-3 font-mono text-[12px] leading-6 text-slate-100 sm:flex-row sm:items-stretch sm:gap-0 sm:text-[13px] sm:leading-7">
                    <!-- Optional inbound nodes (Incoming → Hub) -->
                    <div
                        v-if="block.inbound?.length"
                        class="flex shrink-0 flex-row flex-wrap items-center gap-1.5 sm:flex-col sm:justify-center sm:pr-2"
                    >
                        <template v-for="(node, ni) in block.inbound" :key="`in-${ni}`">
                            <span class="whitespace-nowrap rounded-md border border-white/10 bg-white/[0.03] px-2 py-1 text-slate-200">
                                [{{ node }}]
                            </span>
                            <span class="select-none text-slate-500 sm:rotate-90" aria-hidden="true">→</span>
                        </template>
                    </div>

                    <!-- Root hub -->
                    <div class="flex shrink-0 items-center sm:pr-2">
                        <span class="whitespace-nowrap rounded-md border border-white/15 bg-white/5 px-2.5 py-1.5 font-semibold text-white">
                            [{{ block.root }}]
                        </span>
                    </div>

                    <!-- Vertical rail + branches -->
                    <div class="flex min-w-0 flex-col justify-center gap-1.5 border-l border-white/10 pl-3 sm:border-l-0 sm:pl-0">
                        <div
                            v-for="(branch, bri) in block.branches"
                            :key="bri"
                            class="flex items-start gap-0"
                        >
                            <span class="shrink-0 select-none text-slate-500" aria-hidden="true">
                                {{ treeBranchPrefix(bri, block.branches.length) }}
                            </span>
                            <span class="min-w-0 break-words text-slate-100">
                                <template v-for="(seg, si) in treeBranchSegments(branch)" :key="si">
                                    <span
                                        v-if="seg.kind === 'node'"
                                        class="inline-block max-w-full whitespace-normal break-words rounded-sm border border-white/10 bg-white/[0.04] px-1.5 py-0.5 text-white"
                                    >[{{ seg.text }}]</span>
                                    <span
                                        v-else-if="seg.kind === 'arrow'"
                                        class="text-slate-500"
                                    >{{ seg.text }}</span>
                                    <span
                                        v-else-if="seg.kind === 'note'"
                                        class="text-amber-200/80"
                                    >{{ seg.text }}</span>
                                    <span
                                        v-else-if="seg.kind === 'ok'"
                                        class="text-emerald-300"
                                    >{{ seg.text }}</span>
                                    <span
                                        v-else-if="seg.kind === 'bad'"
                                        class="text-rose-300"
                                    >{{ seg.text }}</span>
                                    <span v-else>{{ seg.text }}</span>
                                </template>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Many → one merge (ETL / dashboard) -->
            <div
                v-else-if="block.type === 'mergeFlow'"
                class="rounded-2xl border border-violet-400/20 bg-violet-950/20 p-3 sm:p-4"
            >
                <p class="mb-3 text-[11px] font-bold uppercase tracking-[0.14em] text-violet-300/80">
                    {{ isEn ? 'Data pipeline' : 'ডাটা পাইপলাইন' }}
                </p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <ul class="flex min-w-0 flex-1 flex-col gap-2">
                        <li
                            v-for="(src, si) in block.sources"
                            :key="si"
                            class="rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm font-medium text-slate-200"
                        >
                            {{ src }}
                        </li>
                    </ul>
                    <div class="flex shrink-0 items-center justify-center text-violet-300/80" aria-hidden="true">
                        <span class="sm:hidden">↓</span>
                        <span class="hidden sm:inline">→</span>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <div class="rounded-xl border border-violet-400/30 bg-violet-500/10 px-3 py-2.5 text-sm font-semibold text-violet-50">
                            {{ block.hub }}
                        </div>
                        <template v-for="(sink, ki) in block.sinks" :key="ki">
                            <div class="flex items-center justify-center text-violet-300/70 sm:justify-start" aria-hidden="true">
                                <span class="sm:hidden">↓</span>
                                <span class="hidden sm:inline">→</span>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm font-medium text-slate-100">
                                {{ sink }}
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Readable formula cards (replaces raw $$ LaTeX $$) -->
            <div
                v-else-if="block.type === 'formulas'"
                class="space-y-3"
            >
                <div
                    v-for="(f, fi) in block.formulas"
                    :key="fi"
                    class="overflow-x-auto rounded-2xl border border-amber-400/25 bg-gradient-to-br from-amber-950/30 to-[#151515] px-4 py-3.5"
                >
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-amber-300/70">
                        {{ isEn ? 'Formula' : 'ফর্মুলা' }}
                    </p>
                    <div
                        v-if="f.kind === 'frac'"
                        class="flex flex-wrap items-center gap-2 font-mono text-sm text-amber-50 sm:text-base"
                    >
                        <span class="font-semibold text-white">{{ f.left }}</span>
                        <span class="text-amber-300/80">=</span>
                        <div class="inline-flex min-w-[10rem] flex-col items-stretch text-center">
                            <span class="border-b border-amber-200/40 px-2 pb-1 text-slate-100">{{ f.num }}</span>
                            <span class="px-2 pt-1 text-slate-100">{{ f.den }}</span>
                        </div>
                    </div>
                    <p
                        v-else
                        class="font-mono text-sm leading-relaxed text-amber-50 sm:text-base"
                    >
                        <span v-if="f.left" class="font-semibold text-white">{{ f.left }}</span>
                        <span v-if="f.left" class="text-amber-300/80"> = </span>
                        <span class="break-words text-slate-100">{{ f.right }}</span>
                    </p>
                </div>
            </div>

            <!-- WhatsApp / SMS message bubble -->
            <figure
                v-else-if="block.type === 'message'"
                class="overflow-hidden rounded-2xl border border-emerald-400/25 bg-gradient-to-br from-emerald-950/40 to-[#0a1210]"
            >
                <figcaption class="flex items-center gap-2 border-b border-emerald-400/15 px-4 py-2.5 text-xs font-semibold text-emerald-200/90">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 text-[10px]" aria-hidden="true">WA</span>
                    {{ block.label || (isEn ? 'Message template' : 'মেসেজ টেমপ্লেট') }}
                </figcaption>
                <blockquote class="whitespace-pre-wrap px-4 py-3.5 text-sm leading-7 text-emerald-50/95 sm:text-[0.95rem] sm:leading-8">
                    {{ block.text }}
                </blockquote>
            </figure>

            <div
                v-else-if="block.type === 'kvTable'"
                class="-mx-1 overflow-x-auto rounded-xl border border-white/10 sm:mx-0"
            >
                <table class="w-full min-w-[18rem] border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/10 text-xs uppercase tracking-wide text-slate-400">
                            <th class="px-3 py-2.5 font-semibold">{{ isEn ? 'Item' : 'বিষয়' }}</th>
                            <th class="px-3 py-2.5 font-semibold">{{ isEn ? 'Detail' : 'বিবরণ' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, ri) in block.rows"
                            :key="ri"
                            class="border-b border-white/5 last:border-b-0 odd:bg-white/[0.03]"
                        >
                            <td class="px-3 py-2.5 align-top font-medium text-slate-200">{{ row.key }}</td>
                            <td class="px-3 py-2.5 align-top leading-relaxed text-amber-100/90">
                                <LinkedRichText :text="row.value" :is-en="isEn" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else-if="block.type === 'numberedTable'"
                class="-mx-1 overflow-x-auto rounded-xl border border-white/10 sm:mx-0"
            >
                <table class="w-full min-w-[20rem] border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/10 text-xs uppercase tracking-wide text-slate-400">
                            <th class="w-12 px-3 py-2.5 font-semibold">#</th>
                            <th class="px-3 py-2.5 font-semibold">{{ isEn ? 'Point' : 'পয়েন্ট' }}</th>
                            <th class="px-3 py-2.5 font-semibold">{{ isEn ? 'Detail' : 'বিবরণ' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, ri) in block.rows"
                            :key="ri"
                            class="border-b border-white/5 last:border-b-0 odd:bg-white/[0.03]"
                        >
                            <td class="px-3 py-2.5 align-top font-bold text-amber-300">{{ row.no }}</td>
                            <td class="px-3 py-2.5 align-top font-medium text-slate-200">{{ row.item }}</td>
                            <td class="px-3 py-2.5 align-top leading-relaxed text-slate-400">
                                <LinkedRichText :text="row.detail" :is-en="isEn" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else-if="block.type === 'compare'"
                class="-mx-1 overflow-x-auto rounded-xl border border-amber-400/25 sm:mx-0"
            >
                <p class="border-b border-amber-400/15 bg-amber-500/5 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-amber-200/70 sm:hidden">
                    {{ isEn ? 'Swipe to compare →' : 'তুলনা দেখতে সাইডে স্ক্রল →' }}
                </p>
                <table class="w-full min-w-[20rem] border-collapse text-left text-sm sm:min-w-[28rem]">
                    <thead>
                        <tr class="border-b border-amber-400/20 bg-amber-500/10 text-xs text-amber-100/90">
                            <th
                                v-for="(h, hi) in block.headers"
                                :key="hi"
                                class="px-3 py-2.5 font-semibold"
                            >
                                {{ h }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, ri) in block.rows"
                            :key="ri"
                            class="border-b border-white/5 last:border-b-0 odd:bg-white/[0.03]"
                        >
                            <td class="px-3 py-2.5 align-top font-semibold text-white">{{ row.feature }}</td>
                            <td class="px-3 py-2.5 align-top leading-relaxed text-slate-300">
                                <LinkedRichText :text="row.a" :is-en="isEn" />
                            </td>
                            <td class="px-3 py-2.5 align-top leading-relaxed font-medium text-emerald-200/90">
                                <LinkedRichText :text="row.b" :is-en="isEn" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script setup>
import LinkedRichText from '@/components/marketing/LinkedRichText.vue';

defineProps({
    blocks: { type: Array, default: () => [] },
    isEn: { type: Boolean, default: false },
    leadFirst: { type: Boolean, default: false },
});

const treeBranchPrefix = (index, total) => {
    if (total <= 1) return '──> ';
    if (index === 0) return '┌──> ';
    if (index === total - 1) return '└──> ';
    return '├──> ';
};

/** Highlight [nodes], arrows, notes, and allow/block markers inside a branch path. */
const treeBranchSegments = (branch) => {
    const raw = String(branch || '');
    const segs = [];
    const re = /(🟢|🔴)|(\[[^\]]{1,80}\])|(──+>?|[➔→])|(\([^)]{1,80}\))/g;
    let last = 0;
    let m;
    while ((m = re.exec(raw)) !== null) {
        if (m.index > last) {
            segs.push({ kind: 'text', text: raw.slice(last, m.index) });
        }
        if (m[1] === '🟢') segs.push({ kind: 'ok', text: '🟢 ' });
        else if (m[1] === '🔴') segs.push({ kind: 'bad', text: '🔴 ' });
        else if (m[2]) segs.push({ kind: 'node', text: m[2].slice(1, -1) });
        else if (m[3]) segs.push({ kind: 'arrow', text: ` ${m[3]} ` });
        else if (m[4]) segs.push({ kind: 'note', text: m[4] });
        last = m.index + m[0].length;
    }
    if (last < raw.length) segs.push({ kind: 'text', text: raw.slice(last) });
    return segs.length ? segs : [{ kind: 'text', text: raw }];
};
</script>
