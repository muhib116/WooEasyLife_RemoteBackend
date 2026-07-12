<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import PlanFeatureList from '@/components/marketing/PlanFeatureList.vue';
import SubscriptionWizard from '@/components/marketing/SubscriptionWizard.vue';
import PendingSubscriptionBanner from '@/components/marketing/PendingSubscriptionBanner.vue';
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
    paymentMethods: { type: Array, default: () => [] },
    subscriptionWizard: { type: Object, default: () => ({}) },
    whatsappSupportUrl: { type: String, default: null },
    whatsappDisplayPhone: { type: String, default: null },
    pendingSubscriptionInquiry: { type: Object, default: null },
    seo: { type: Object, default: null },
});

const page = usePage();
const toast = useToast();
const showWizard = ref(false);
const selectedPlan = ref(null);

const authUser = computed(() => page.props.auth?.user ?? null);
const hasPortal = computed(() => Boolean(page.props.auth?.portal));
const hasPendingInquiry = computed(() => Boolean(props.pendingSubscriptionInquiry?.id));

const displayPlans = computed(() => {
    const trial = props.plans.filter((plan) => plan.package_duration === 'free_trial');
    const paid = props.plans.filter((plan) => plan.package_duration !== 'free_trial');

    return [...trial, ...paid];
});

const isFreeTrial = (plan) => plan.package_duration === 'free_trial';

const planBadge = (plan) => plan.badge_label ?? null;

const planBadgeClass = (plan) => {
    if (isFreeTrial(plan)) {
        return 'bg-emerald-400 text-emerald-950';
    }

    return 'bg-amber-400 text-amber-950';
};

const purchaseLabel = (plan) => {
    if (hasPendingInquiry.value) {
        return 'অনুরোধ প্রক্রিয়াধীন';
    }

    if (isFreeTrial(plan)) {
        return 'ফ্রি ট্রায়াল শুরু করুন';
    }

    return 'এখনই সাবস্ক্রাইব করুন';
};

const openPurchase = (plan) => {
    if (!plan || hasPendingInquiry.value) {
        return;
    }

    selectedPlan.value = plan;
    showWizard.value = true;
};

watch(
    () => page.props.flash?.subscription_submitted,
    (payload) => {
        if (payload) {
            showWizard.value = true;
            toast.add({
                severity: 'success',
                summary: 'সফল',
                detail: 'আপনার সাবস্ক্রিপশন অনুরোধ জমা হয়েছে।',
                life: 6000,
            });
        }
    },
);

onMounted(() => {
    if (hasPendingInquiry.value || !props.preselectedPlanId) {
        return;
    }

    const plan = props.plans.find((item) => item.id === props.preselectedPlanId);
    if (plan) {
        openPurchase(plan);
    }
});
</script>

<template>
    <SeoHead :seo="seo" title="প্রাইসিং — WooEasyLife" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="pricing" variant="dark">
        <section class="border-b border-white/10 py-14 sm:py-16">
            <div class="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
                <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">
                    আপনার ব্যবসার জন্য সঠিক প্ল্যান বেছে নিন
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-400">
                    সহজ ধাপে মোবাইল ব্যাংকিং দিয়ে পেমেন্ট করুন — কোনো টেকনিক্যাল জ্ঞান লাগবে না।
                </p>
                <p v-if="whatsappDisplayPhone" class="mt-2 text-sm text-slate-400">
                    সমস্যা হলে WhatsApp-এ {{ whatsappDisplayPhone }} নম্বরে সাহায্য নিন।
                </p>
                <p class="mt-3 text-sm text-slate-500">
                    লগইন ছাড়াই সাবস্ক্রিপশন অনুরোধ করতে পারবেন — প্ল্যান বেছে নিয়ে ফর্ম পূরণ করুন।
                </p>
                <p
                    v-if="authUser && canPurchase && !hasPendingInquiry"
                    class="mt-4 text-sm font-medium text-emerald-400"
                >
                    আপনি লগইন আছেন — পছন্দের প্ল্যানে «এখনই সাবস্ক্রাইব করুন» ক্লিক করুন।
                </p>
                <div v-if="pendingSubscriptionInquiry" class="mx-auto mt-8 max-w-3xl text-left">
                    <PendingSubscriptionBanner
                        :inquiry="pendingSubscriptionInquiry"
                        :whatsapp-url="whatsappSupportUrl || whatsappUrl"
                    />
                </div>
            </div>
        </section>

        <section class="py-16">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="plan in displayPlans"
                        :key="plan.id"
                        class="relative flex flex-col rounded-2xl border p-6 transition"
                        :class="[
                            plan.is_special
                                ? 'border-amber-400/50 bg-amber-500/10 shadow-xl shadow-amber-900/30'
                                : 'border-white/10 bg-white/5',
                            hasPendingInquiry ? 'opacity-70' : 'hover:-translate-y-0.5',
                        ]"
                    >
                        <span
                            v-if="planBadge(plan)"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full px-3 py-0.5 text-xs font-bold"
                            :class="planBadgeClass(plan)"
                        >
                            {{ planBadge(plan) }}
                        </span>

                        <p class="text-sm font-semibold text-amber-300">{{ plan.duration_label }}</p>
                        <h2 class="mt-1 text-xl font-bold text-white">{{ plan.title }}</h2>
                        <p class="mt-2 text-4xl font-extrabold text-white">{{ plan.price_label }}</p>
                        <p class="mt-1 text-sm text-slate-400">{{ plan.token_label }}</p>
                        <p v-if="plan.website_label" class="text-sm text-slate-400">{{ plan.website_label }}</p>
                        <p v-if="plan.plain_description" class="mt-3 text-sm text-slate-400">
                            {{ plan.plain_description }}
                        </p>

                        <PlanFeatureList :plan="plan" />

                        <button
                            type="button"
                            class="mt-6 w-full rounded-xl py-3 text-sm font-bold transition disabled:cursor-not-allowed disabled:opacity-60"
                            :class="plan.is_special
                                ? 'bg-amber-500 text-black hover:bg-amber-400'
                                : 'border border-white/15 text-white hover:bg-white/10'"
                            :disabled="hasPendingInquiry"
                            @click="openPurchase(plan)"
                        >
                            {{ purchaseLabel(plan) }}
                        </button>
                    </article>
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
                        <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-amber-300">
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

        <section class="bg-amber-500 py-14">
            <div class="mx-auto max-w-3xl px-4 text-center">
                <h2 class="text-3xl font-bold text-white">এখনই শুরু করুন</h2>
                <p class="mt-3 text-amber-50">
                    প্ল্যান বেছে নিন → তথ্য দিন → bKash/Rocket পেমেন্ট → লেনদেন আইডি জমা দিন।
                    কোথাও আটকে গেলে WhatsApp করুন।
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a
                        v-if="whatsappSupportUrl || whatsappUrl"
                        :href="whatsappSupportUrl || whatsappUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-amber-950"
                    >
                        <svg class="h-5 w-5 text-emerald-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                        </svg>
                        WhatsApp{{ whatsappDisplayPhone ? `: ${whatsappDisplayPhone}` : ' সাপোর্ট' }}
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

        <SubscriptionWizard
            v-model:visible="showWizard"
            :plan="selectedPlan"
            :plans="plans"
            :domains="domains"
            :payment-methods="paymentMethods"
            :subscription-wizard="subscriptionWizard"
            :whatsapp-support-url="whatsappSupportUrl || whatsappUrl"
            :whatsapp-display-phone="whatsappDisplayPhone"
            :can-login="canLogin"
            :pending-inquiry="pendingSubscriptionInquiry"
        />

        <Toast />
    </MarketingLayout>
</template>
