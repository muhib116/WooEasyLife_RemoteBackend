<template>
    <div
        class="rounded-2xl border border-amber-400/35 bg-gradient-to-br from-amber-500/15 via-amber-500/10 to-transparent px-4 py-4 sm:px-5 sm:py-5"
        role="status"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-300">
                    সাবস্ক্রিপশন অনুরোধ {{ inquiry.status_label || 'প্রক্রিয়াধীন' }}
                </p>
                <p class="mt-1 text-sm font-semibold text-white sm:text-base">
                    {{ inquiry.message }}
                </p>
                <p v-if="inquiry.plan_title || inquiry.domain" class="mt-2 text-xs text-slate-400">
                    <span v-if="inquiry.plan_title">প্ল্যান: {{ inquiry.plan_title }}</span>
                    <span v-if="inquiry.plan_title && inquiry.domain"> · </span>
                    <span v-if="inquiry.domain">ডোমেইন: {{ inquiry.domain }}</span>
                </p>
            </div>
            <a
                v-if="whatsappUrl"
                :href="whatsappUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex shrink-0 items-center justify-center rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-400"
            >
                WhatsApp-এ জানুন
            </a>
        </div>
    </div>
</template>

<script setup>
defineProps({
    inquiry: { type: Object, required: true },
    whatsappUrl: { type: String, default: null },
});
</script>
