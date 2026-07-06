<script setup>
import { computed } from 'vue';
import { planEnabledFeatureCount, resolvePlanCatalogFeatures } from '@/utils/planDisplay';

const props = defineProps({
    plan: { type: Object, required: true },
    compact: { type: Boolean, default: false },
    showCount: { type: Boolean, default: false },
    scrollable: { type: Boolean, default: false },
});

const features = computed(() => resolvePlanCatalogFeatures(props.plan));
const enabledCount = computed(() => planEnabledFeatureCount(props.plan, features.value));
const heading = computed(() => props.plan.features_heading || 'প্ল্যান ফিচার');
</script>

<template>
    <div class="border-t border-white/10 pt-5" :class="compact ? 'mt-4' : 'mt-5 flex-1'">
        <p
            v-if="showCount"
            class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500"
        >
            {{ heading }}
            <span class="ml-1 font-normal normal-case text-slate-600">
                ({{ enabledCount }}/{{ features.length }})
            </span>
        </p>
        <ul
            class="space-y-2"
            :class="scrollable
                ? 'max-h-52 overflow-y-auto pr-1 [scrollbar-color:rgba(255,255,255,0.15)_transparent] [scrollbar-width:thin]'
                : ''"
        >
            <li
                v-for="feature in features"
                :key="feature.key"
                class="flex items-start gap-2 text-sm leading-snug"
                :class="feature.enabled ? 'text-slate-300' : 'text-slate-500'"
                :aria-label="feature.enabled ? `${feature.label} — অন্তর্ভুক্ত` : `${feature.label} — অন্তর্ভুক্ত নয়`"
            >
                <svg
                    v-if="feature.enabled"
                    class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg
                    v-else
                    class="mt-0.5 h-4 w-4 shrink-0 text-slate-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span :class="feature.enabled ? '' : 'line-through opacity-60'">
                    {{ feature.label }}
                </span>
            </li>
        </ul>
    </div>
</template>
