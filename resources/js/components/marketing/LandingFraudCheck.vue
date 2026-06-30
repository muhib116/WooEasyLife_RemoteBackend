<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    fraudCheck: {
        type: Object,
        default: () => ({}),
    },
});

const phone = ref('');
const isLoading = ref(false);
const errorMessage = ref('');
const limitMessage = ref('');
const result = ref(null);
const meta = ref({ ...props.fraudCheck });

const isEnabled = computed(() => meta.value?.enabled !== false);
const remainingSearches = computed(() => meta.value?.remaining_searches ?? 0);
const dailySearchPhrase = computed(() => meta.value?.daily_search_phrase ?? '');
const freeSearchNote = computed(() => meta.value?.free_search_note ?? '');

const fraudNotes = computed(() => {
    const notes = result.value?.report?.frauds ?? result.value?.frauds ?? [];

    return Array.isArray(notes) ? notes.filter((note) => note?.details || note?.name) : [];
});

const courierLogos = {
    'Stead Fast': '/images/steadfast.svg',
    Pathao: '/images/pathao.svg',
    'Paper Fly': '/images/redx.svg',
};

const riskClass = (tone) => {
    const map = {
        safe: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
        caution: 'border-amber-500/30 bg-amber-500/10 text-amber-300',
        risky: 'border-rose-500/30 bg-rose-500/10 text-rose-300',
        neutral: 'border-slate-500/30 bg-slate-500/10 text-slate-300',
    };

    return map[tone] ?? map.neutral;
};

const courierRateClass = (report) => {
    const rate = String(report?.success_rate ?? '').toLowerCase();

    if (rate.includes('good') || rate.includes('excellent') || rate.includes('%')) {
        return 'text-emerald-400';
    }

    if (rate.includes('poor') || rate.includes('risky') || rate.includes('average')) {
        return 'text-rose-400';
    }

    return 'text-slate-400';
};

const normalizePhone = (value) => String(value).replace(/\D/g, '');

const formatFraudDate = (value) => {
    if (!value) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat('bn-BD', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return String(value);
    }
};

const handleSearch = async () => {
    const normalized = normalizePhone(phone.value);

    if (!/^01[3-9]\d{8}$/.test(normalized)) {
        errorMessage.value = 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন (যেমন: 017XXXXXXXX)';
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';
    limitMessage.value = '';
    result.value = null;

    try {
        const { data } = await axios.post(route('landing.fraud-check.check'), {
            phone: normalized,
        }, { timeout: 120000 });

        result.value = data;
        meta.value = data.meta ?? meta.value;
    } catch (error) {
        const response = error?.response;

        if (response?.status === 429) {
            limitMessage.value = response.data?.message ?? 'আজকের ফ্রি সার্চ শেষ হয়ে গেছে।';
            meta.value = response.data?.meta ?? meta.value;
            return;
        }

        errorMessage.value = response?.data?.message ?? 'ফ্রড চেক সম্পন্ন করা যায়নি। আবার চেষ্টা করুন।';
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div v-if="isEnabled">
        <div
            v-if="dailySearchPhrase"
            class="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300"
        >
            <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400" />
            {{ dailySearchPhrase }}
        </div>

        <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#141414]/80 shadow-2xl shadow-amber-900/20">
            <div class="border-b border-white/10 px-4 py-4 sm:px-5">
                <p class="text-sm font-semibold text-white">Number দিন — fake order আগেই ধরুন</p>
                <p class="mt-1 text-xs text-slate-400">
                    Courier delivery history দেখে order confirm করার আগেই customer কেমন — জেনে নিন
                </p>
            </div>

            <div class="p-4 sm:p-5">
                <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="handleSearch">
                    <label class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">📞</span>
                        <input
                            v-model="phone"
                            type="tel"
                            inputmode="numeric"
                            maxlength="14"
                            placeholder="017XXXXXXXX"
                            class="w-full rounded-xl border border-white/10 bg-white/5 py-3.5 pl-11 pr-4 text-white placeholder:text-slate-500 focus:border-amber-500/50 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                        >
                    </label>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-6 py-3.5 text-sm font-bold text-black transition hover:from-amber-400 hover:to-yellow-400 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="isLoading || remainingSearches <= 0"
                    >
                        <span v-if="isLoading" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                        {{ isLoading ? 'চেক হচ্ছে...' : 'ফ্রড চেক করুন' }}
                    </button>
                </form>

                <p v-if="freeSearchNote" class="mt-3 text-center text-xs text-slate-500">
                    {{ freeSearchNote }}
                    <span v-if="remainingSearches > 0" class="text-slate-400">
                        · আজ বাকি {{ remainingSearches }}টি
                    </span>
                </p>

                <p
                    v-if="limitMessage"
                    class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"
                >
                    {{ limitMessage }}
                </p>

                <p
                    v-if="errorMessage"
                    class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200"
                >
                    {{ errorMessage }}
                </p>

                <div v-if="isLoading" class="mt-6 flex flex-col items-center gap-3 py-8 text-sm text-slate-400">
                    <div class="h-10 w-10 animate-spin rounded-full border-2 border-amber-500/30 border-t-amber-400" />
                    কুরিয়ার ডাটা যাচাই হচ্ছে...
                </div>

                <div v-else-if="result?.report" class="mt-6 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/5 p-4">
                        <div>
                            <p class="text-xs text-slate-400">সার্চ করা নম্বর</p>
                            <p class="text-lg font-bold text-white">{{ result.phone_masked }}</p>
                        </div>
                        <span
                            class="rounded-full border px-3 py-1 text-xs font-bold"
                            :class="riskClass(result.risk_tone)"
                        >
                            {{ result.risk_label }}
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-white/10 bg-white/5 p-3 text-center">
                            <p class="text-xs text-slate-400">মোট</p>
                            <p class="text-xl font-bold text-white">{{ result.report.total_order ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-center">
                            <p class="text-xs text-emerald-300">ডেলিভারি</p>
                            <p class="text-xl font-bold text-emerald-300">{{ result.report.confirmed ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-center">
                            <p class="text-xs text-rose-300">রিটার্ন</p>
                            <p class="text-xl font-bold text-rose-300">{{ result.report.cancel ?? 0 }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-white/10 bg-white/5 p-4 text-center">
                        <p class="text-xs text-slate-400">সাকসেস রেট</p>
                        <p class="mt-1 text-2xl font-extrabold text-amber-300">
                            {{ result.report.success_rate }}
                        </p>
                    </div>

                    <div v-if="result.report.courier?.length" class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">কুরিয়ার ভিত্তিক রিপোর্ট</p>
                        <div
                            v-for="item in result.report.courier"
                            :key="item.title"
                            class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-[#111111] px-4 py-3"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-16 items-center justify-center rounded-lg bg-white px-2">
                                    <img
                                        v-if="courierLogos[item.title]"
                                        :src="courierLogos[item.title]"
                                        :alt="item.title"
                                        class="max-h-6 max-w-full object-contain"
                                    >
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ item.title }}</p>
                                    <p v-if="item.report?.total_order > 0" class="text-xs text-slate-400">
                                        {{ item.report.confirmed }} ডেলিভারি · {{ item.report.cancel }} রিটার্ন
                                    </p>
                                    <p v-else class="text-xs text-slate-500">কোনো ডেলিভারি ডাটা নেই</p>
                                </div>
                            </div>
                            <span class="text-sm font-bold" :class="courierRateClass(item.report)">
                                {{ item.report?.success_rate }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Steadfast ফ্রড নোট
                            </p>
                            <span
                                v-if="fraudNotes.length"
                                class="rounded-full border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 text-xs font-bold text-rose-300"
                            >
                                {{ fraudNotes.length }}টি রিপোর্ট
                            </span>
                        </div>

                        <div
                            v-if="fraudNotes.length"
                            class="space-y-3"
                        >
                            <article
                                v-for="(note, index) in fraudNotes"
                                :key="`${note.consignment_id ?? note.user_id ?? index}`"
                                class="rounded-xl border border-rose-500/30 bg-rose-950/30 p-4"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        v-if="note.image"
                                        class="h-10 w-10 shrink-0 overflow-hidden rounded-full border border-rose-500/20"
                                    >
                                        <img :src="note.image" :alt="note.name || 'Reporter'" class="h-full w-full object-cover">
                                    </div>
                                    <div
                                        v-else
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-rose-500/30 bg-rose-500/10 text-sm font-bold text-rose-300"
                                    >
                                        ⚠
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p v-if="note.name" class="text-sm font-semibold text-white">
                                            {{ note.name }}
                                        </p>
                                        <p
                                            v-if="note.details"
                                            class="mt-1 text-sm leading-relaxed text-rose-100"
                                        >
                                            {{ note.details }}
                                        </p>
                                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-rose-200/80">
                                            <span v-if="note.consignment_id">কনসাইনমেন্ট: {{ note.consignment_id }}</span>
                                            <span v-if="note.user_id">ইউজার: {{ note.user_id }}</span>
                                            <span v-if="note.created_at">{{ formatFraudDate(note.created_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <p
                            v-else
                            class="rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-400"
                        >
                            এই নম্বরের জন্য Steadfast-এ কোনো ফ্রড নোট পাওয়া যায়নি।
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
