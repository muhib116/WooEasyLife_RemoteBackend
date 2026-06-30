<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import PaymentRequestFormFields from '@/components/PaymentRequestFormFields.vue';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    plans: { type: Array, default: () => [] },
    featuredPlan: { type: Object, default: null },
    featureGroups: { type: Array, default: () => [] },
    whatsappUrl: { type: String, default: null },
    domains: { type: Array, default: () => [] },
    canPurchase: { type: Boolean, default: false },
    preselectedPlanId: { type: Number, default: null },
});

const page = usePage();
const toast = useToast();
const showPaymentForm = ref(false);
const selectedPlan = ref(null);

const authUser = computed(() => page.props.auth?.user ?? null);
const hasPortal = computed(() => Boolean(page.props.auth?.portal));

const trialPlan = computed(() =>
    props.plans.find((plan) => plan.package_duration === 'free_trial') ?? null,
);

const paidPlans = computed(() =>
    props.plans.filter((plan) => plan.package_duration !== 'free_trial'),
);

const paymentForm = useForm({
    domain: '',
    package_hub_id: null,
    order_limit: 100,
    total_amount: null,
    transaction_method: 'Bkash',
    transaction_id: '',
    account_number: '',
    transaction_charge: 0,
    note: '',
});

const purchaseLabel = (plan) => {
    if (props.canPurchase && props.domains.length) {
        return 'এখনই কিনুন';
    }

    if (hasPortal.value && !props.domains.length) {
        return 'ওয়েবসাইট যোগ করুন';
    }

    if (props.canLogin && !authUser.value) {
        return 'লগইন করে কিনুন';
    }

    return props.whatsappUrl ? 'যোগাযোগ করুন' : 'শুরু করুন';
};

const openPurchase = (plan) => {
    if (props.canPurchase && props.domains.length) {
        selectedPlan.value = plan;
        paymentForm.reset();
        paymentForm.domain = props.domains[0] ?? '';
        paymentForm.package_hub_id = plan.id;
        paymentForm.order_limit = plan.order_rate_token || 100;
        paymentForm.total_amount = plan.package_price ?? null;
        showPaymentForm.value = true;
        return;
    }

    if (hasPortal.value && !props.domains.length) {
        router.visit(route('portal.websites'));
        return;
    }

    if (!authUser.value && props.canLogin) {
        router.visit(`${route('login')}?redirect=${encodeURIComponent(`/pricing?plan=${plan.id}`)}`);
        return;
    }

    if (props.whatsappUrl) {
        window.open(props.whatsappUrl, '_blank', 'noopener,noreferrer');
        return;
    }

    router.visit(route('login'));
};

const submitPayment = () => {
    paymentForm.post(route('portal.billing.payment-request'), {
        onSuccess: () => {
            showPaymentForm.value = false;
            paymentForm.reset();
            toast.add({
                severity: 'success',
                summary: 'সফল',
                detail: 'পেমেন্ট রিকোয়েস্ট জমা হয়েছে। অনুমোদনের জন্য অপেক্ষা করুন।',
                life: 5000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'ত্রুটি',
                detail: 'পেমেন্ট রিকোয়েস্ট জমা দেওয়া যায়নি।',
                life: 4000,
            });
        },
    });
};

onMounted(() => {
    if (!props.preselectedPlanId) {
        return;
    }

    const plan = props.plans.find((item) => item.id === props.preselectedPlanId);
    if (plan) {
        openPurchase(plan);
    }
});
</script>

<template>
    <Head title="প্রাইসিং — WooEasyLife" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="pricing" variant="dark">
        <section class="border-b border-white/10 py-14 sm:py-16">
            <div class="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
                <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">
                    আপনার ব্যবসার জন্য সঠিক প্ল্যান বেছে নিন
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-400">
                    সব প্যাকেজে স্বচ্ছ মূল্য, টোকেন-ভিত্তিক ব্যবহার ও প্রিমিয়াম ফিচার।
                    লগইন করে সরাসরি সাবস্ক্রিপশন কিনতে পারবেন।
                </p>
                <p
                    v-if="authUser && canPurchase"
                    class="mt-4 text-sm font-medium text-emerald-400"
                >
                    আপনি লগইন আছেন — পছন্দের প্ল্যানে «এখনই কিনুন» ক্লিক করুন।
                </p>
            </div>
        </section>

        <section class="py-16">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-3">
                    <article
                        v-for="plan in paidPlans"
                        :key="plan.id"
                        class="relative flex flex-col rounded-2xl border p-6 transition hover:-translate-y-0.5"
                        :class="plan.is_special
                            ? 'border-violet-400/50 bg-violet-600/10 shadow-xl shadow-violet-900/30'
                            : 'border-white/10 bg-white/5'"
                    >
                        <span
                            v-if="plan.is_special"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-amber-400 px-3 py-0.5 text-xs font-bold text-amber-950"
                        >
                            সবচেয়ে জনপ্রিয়
                        </span>

                        <p class="text-sm font-semibold text-violet-300">{{ plan.duration_label }}</p>
                        <h2 class="mt-1 text-xl font-bold text-white">{{ plan.title }}</h2>
                        <p class="mt-2 text-4xl font-extrabold text-white">{{ plan.price_label }}</p>
                        <p class="mt-1 text-sm text-slate-400">{{ plan.token_label }}</p>
                        <p v-if="plan.website_label" class="text-sm text-slate-400">{{ plan.website_label }}</p>
                        <p v-if="plan.app_connect" class="mt-2 text-xs font-semibold text-emerald-400">
                            ✓ মোবাইল অ্যাপ অন্তর্ভুক্ত
                        </p>
                        <p v-if="plan.plain_description" class="mt-3 text-sm text-slate-400">
                            {{ plan.plain_description }}
                        </p>

                        <ul class="mt-5 flex-1 space-y-2 border-t border-white/10 pt-5">
                            <li
                                v-for="feature in plan.top_features"
                                :key="feature.key"
                                class="flex items-start gap-2 text-sm text-slate-300"
                            >
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ feature.label }}
                            </li>
                            <li class="text-xs text-slate-500">
                                + আরও {{ Math.max(0, plan.enabled_feature_count - plan.top_features.length) }} ফিচার
                            </li>
                        </ul>

                        <button
                            type="button"
                            class="mt-6 w-full rounded-xl py-3 text-sm font-bold transition"
                            :class="plan.is_special
                                ? 'bg-violet-600 text-white hover:bg-violet-500'
                                : 'border border-white/15 text-white hover:bg-white/10'"
                            @click="openPurchase(plan)"
                        >
                            {{ purchaseLabel(plan) }}
                        </button>
                    </article>
                </div>

                <div
                    v-if="trialPlan"
                    class="mx-auto mt-10 max-w-xl rounded-2xl border border-emerald-500/30 bg-emerald-950/30 p-6 text-center"
                >
                    <h3 class="text-lg font-bold text-emerald-300">{{ trialPlan.title }}</h3>
                    <p class="mt-2 text-sm text-emerald-200/80">
                        {{ trialPlan.duration_label }} · {{ trialPlan.token_label }} · {{ trialPlan.price_label }}
                    </p>
                    <button
                        type="button"
                        class="mt-4 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-emerald-700"
                        @click="openPurchase(trialPlan)"
                    >
                        {{ purchaseLabel(trialPlan) }}
                    </button>
                </div>
            </div>
        </section>

        <section v-if="featuredPlan && featureGroups.length" class="border-t border-white/10 py-16">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold text-white">প্রো প্ল্যানে যা পাবেন</h2>
                    <p class="mt-3 text-slate-400">
                        {{ featuredPlan.title }} — মোট {{ featuredPlan.enabled_feature_count }}+ ফিচার
                    </p>
                </div>
                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="group in featureGroups"
                        :key="group.group"
                        class="rounded-2xl border border-white/10 bg-white/5 p-5"
                    >
                        <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-violet-300">
                            {{ group.group }}
                        </h3>
                        <ul class="space-y-2">
                            <li
                                v-for="item in group.items"
                                :key="item"
                                class="flex items-start gap-2 text-sm text-slate-300"
                            >
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-violet-600 py-14">
            <div class="mx-auto max-w-3xl px-4 text-center">
                <h2 class="text-3xl font-bold text-white">এখনই শুরু করুন</h2>
                <p class="mt-3 text-violet-100">
                    প্রশ্ন থাকলে হোয়াটসঅ্যাপে যোগাযোগ করুন, অথবা লগইন করে পেমেন্ট জমা দিন।
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a
                        v-if="whatsappUrl"
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-xl bg-white px-6 py-3 text-sm font-bold text-violet-700"
                    >
                        হোয়াটসঅ্যাপে যোগাযোগ
                    </a>
                    <Link
                        v-if="canLogin && hasPortal"
                        :href="route('portal.billing')"
                        class="rounded-xl border border-white/30 px-6 py-3 text-sm font-bold text-white"
                    >
                        বিলিং ড্যাশবোর্ড
                    </Link>
                </div>
            </div>
        </section>

        <Dialog
            v-model:visible="showPaymentForm"
            header="সাবস্ক্রিপশন কিনুন"
            modal
            :style="{ width: 'min(100vw - 2rem, 42rem)' }"
            draggable
            dismissable-mask
        >
            <PaymentRequestFormFields
                v-if="showPaymentForm"
                :form="paymentForm"
                :plans="plans"
                :domains="domains"
                empty-domains-message="কোনো ওয়েবসাইট পাওয়া যায়নি। প্রথমে ওয়েবসাইট যোগ করুন।"
                submit-label="পেমেন্ট জমা দিন"
                @submit="submitPayment"
                @cancel="showPaymentForm = false"
            />
        </Dialog>

        <Toast />
    </MarketingLayout>
</template>
