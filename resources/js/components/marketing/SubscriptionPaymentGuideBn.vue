<template>
    <section class="rounded-xl border border-white/10 bg-white/5">
        <div class="border-b border-white/10 px-4 py-3">
            <h4 class="text-sm font-semibold text-white">কীভাবে পেমেন্ট করবেন</h4>
            <p class="mt-1 text-xs leading-relaxed text-slate-400">
                পদ্ধতি বেছে নিন, টাকা পাঠান, তারপর লেনদেন আইডি দিন।
            </p>
        </div>

        <div v-if="!methods.length" class="px-4 py-6 text-center text-sm text-slate-500">
            পেমেন্ট নির্দেশনা এখন উপলব্ধ নেই।
        </div>

        <div v-else class="p-3 sm:p-4">
            <!-- Method switcher — keeps the modal short when many methods exist -->
            <div
                class="grid gap-1.5"
                :class="methods.length === 1 ? 'grid-cols-1' : methods.length === 2 ? 'grid-cols-2' : 'grid-cols-3'"
                role="tablist"
                aria-label="পেমেন্ট পদ্ধতি"
            >
                <button
                    v-for="method in methods"
                    :key="method.payment_partner"
                    type="button"
                    role="tab"
                    class="rounded-lg px-2 py-2.5 text-center text-sm font-semibold transition"
                    :class="isActive(method)
                        ? 'bg-amber-500 text-black shadow-sm shadow-amber-900/30'
                        : 'bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white'"
                    :aria-selected="isActive(method)"
                    @click="selectMethod(method.payment_partner)"
                >
                    {{ method.payment_partner }}
                </button>
            </div>

            <div
                v-if="activeMethod"
                class="mt-3 overflow-hidden rounded-lg border border-white/10 bg-black/20"
                role="tabpanel"
            >
                <div class="flex items-start justify-between gap-3 border-b border-white/10 px-3 py-3 sm:px-4">
                    <div class="min-w-0">
                        <p class="text-xs text-slate-400">এই নম্বরে পাঠান</p>
                        <p class="mt-0.5 font-mono text-base font-bold tracking-wide text-white">
                            {{ activeMethod.account }}
                        </p>
                        <p v-if="activeMethod.note" class="mt-1 text-xs leading-relaxed text-slate-500">
                            {{ activeMethod.note }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-amber-400/30 bg-amber-500/10 px-3 py-2 text-xs font-bold text-amber-300 transition hover:bg-amber-500/20"
                        @click="copyAccount(activeMethod.account)"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        {{ copiedAccount === activeMethod.account ? 'কপি হয়েছে' : 'কপি' }}
                    </button>
                </div>

                <ol class="space-y-2.5 px-3 py-3 sm:px-4">
                    <li
                        v-for="(step, index) in activeMethod.steps"
                        :key="index"
                        class="flex gap-2.5 text-sm text-slate-300"
                    >
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-[11px] font-bold text-amber-300">
                            {{ index + 1 }}
                        </span>
                        <span class="leading-relaxed">{{ step }}</span>
                    </li>
                </ol>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    methods: { type: Array, default: () => [] },
});

const emit = defineEmits(['select']);

const activePartner = ref(null);
const copiedAccount = ref(null);
let copyResetTimer = null;

const activeMethod = computed(() =>
    props.methods.find((method) => method.payment_partner === activePartner.value)
        ?? props.methods[0]
        ?? null,
);

const isActive = (method) => activeMethod.value?.payment_partner === method.payment_partner;

const selectMethod = (partner) => {
    activePartner.value = partner;
    emit('select', partner);
};

watch(
    () => props.methods,
    (methods) => {
        if (!methods.length) {
            activePartner.value = null;
            return;
        }

        const stillExists = methods.some((method) => method.payment_partner === activePartner.value);

        if (!stillExists) {
            activePartner.value = methods[0].payment_partner;
            emit('select', activePartner.value);
        }
    },
    { immediate: true, deep: true },
);

const copyAccount = async (account) => {
    if (!account) {
        return;
    }

    try {
        await navigator.clipboard.writeText(String(account));
        copiedAccount.value = account;

        if (copyResetTimer) {
            clearTimeout(copyResetTimer);
        }

        copyResetTimer = setTimeout(() => {
            copiedAccount.value = null;
        }, 2000);
    } catch {
        copiedAccount.value = null;
    }
};
</script>
