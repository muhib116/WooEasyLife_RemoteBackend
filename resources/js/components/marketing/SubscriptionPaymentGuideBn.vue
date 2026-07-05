<template>
    <section class="rounded-xl border border-white/10 bg-white/5">
        <div class="border-b border-white/10 px-4 py-3">
            <h4 class="text-sm font-semibold text-white">কীভাবে পেমেন্ট করবেন</h4>
            <p class="mt-1 text-sm text-slate-400">
                bKash বা Rocket দিয়ে Send Money করুন, তারপর লেনদেন আইডি ফর্মে লিখুন।
            </p>
        </div>

        <div class="space-y-4 p-4">
            <div
                v-for="method in methods"
                :key="method.payment_partner"
                class="overflow-hidden rounded-lg border border-white/10"
            >
                <div class="border-b border-white/10 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-300">{{ method.payment_partner }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <p class="text-xs text-slate-400">
                            এই নম্বরে পাঠান:
                            <span class="font-mono font-bold text-white">{{ method.account }}</span>
                        </p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-white/15 px-2 py-1 text-xs font-semibold text-amber-300 transition hover:bg-white/10"
                            @click="copyAccount(method.account)"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            {{ copiedAccount === method.account ? 'কপি হয়েছে' : 'কপি' }}
                        </button>
                    </div>
                    <p v-if="method.note" class="mt-1 text-xs text-slate-500">{{ method.note }}</p>
                </div>
                <ol class="space-y-2 px-4 py-3">
                    <li
                        v-for="(step, index) in method.steps"
                        :key="index"
                        class="flex gap-3 text-sm text-slate-300"
                    >
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-xs font-bold text-amber-300">
                            {{ index + 1 }}
                        </span>
                        <span class="pt-0.5 leading-relaxed">{{ step }}</span>
                    </li>
                </ol>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    methods: { type: Array, default: () => [] },
});

const copiedAccount = ref(null);
let copyResetTimer = null;

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
