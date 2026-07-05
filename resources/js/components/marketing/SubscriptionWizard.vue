<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import WhatsAppSupportBar from '@/components/marketing/WhatsAppSupportBar.vue';
import SubscriptionPaymentGuideBn from '@/components/marketing/SubscriptionPaymentGuideBn.vue';
import PlanFeatureList from '@/components/marketing/PlanFeatureList.vue';

const props = defineProps({
    visible: { type: Boolean, default: false },
    plan: { type: Object, default: null },
    plans: { type: Array, default: () => [] },
    domains: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    subscriptionWizard: { type: Object, default: () => ({}) },
    whatsappSupportUrl: { type: String, default: null },
    whatsappDisplayPhone: { type: String, default: '01770989591' },
    canLogin: { type: Boolean, default: false },
});

const emit = defineEmits(['update:visible', 'submitted']);

const page = usePage();
const currentStep = ref(0);
const submittedSummary = ref(null);
const attemptedAdvance = ref(false);

const stepKeys = ['plan', 'contact', 'payment', 'confirm'];

const copy = computed(() => ({
    title: props.subscriptionWizard?.title ?? 'সাবস্ক্রিপশন কিনুন',
    supportHint: props.subscriptionWizard?.support_hint ?? 'কোনো ধাপ বুঝতে সমস্যা? WhatsApp-এ সরাসরি সাহায্য নিন।',
    steps: props.subscriptionWizard?.steps ?? {},
    freeTrialHint: props.subscriptionWizard?.free_trial_hint ?? 'ফ্রি ট্রায়াল শুরু করতে লগইন করুন অথবা WhatsApp-এ যোগাযোগ করুন।',
    successTitle: props.subscriptionWizard?.success_title ?? 'আপনার অনুরোধ জমা হয়েছে!',
    successMessage: props.subscriptionWizard?.success_message ?? 'আমাদের টিম শীঘ্রই যাচাই করবে।',
}));

const isFreeTrial = computed(() => props.plan?.package_duration === 'free_trial');

const activeSteps = computed(() => {
    if (isFreeTrial.value) {
        return [
            { key: 'plan', label: copy.value.steps.plan ?? 'প্ল্যান' },
            { key: 'contact', label: copy.value.steps.contact ?? 'আপনার তথ্য' },
        ];
    }

    return stepKeys.map((key) => ({
        key,
        label: copy.value.steps[key] ?? key,
    }));
});

const form = useForm({
    package_hub_id: null,
    website_url: '',
    customer_name: '',
    email: '',
    contact_number: '',
    whatsapp_number: '',
    address: '',
    order_limit: 100,
    total_amount: null,
    transaction_method: 'Bkash',
    transaction_id: '',
    account_number: '',
    transaction_charge: 0,
    note: '',
});

const gatewayMethods = [
    { label: 'bKash', value: 'Bkash' },
    { label: 'Rocket', value: 'Rocket' },
    { label: 'Nagad', value: 'Nagad' },
    { label: 'Bank', value: 'Bank' },
];

const resetWizard = () => {
    currentStep.value = 0;
    submittedSummary.value = null;
    attemptedAdvance.value = false;
    form.reset();
    form.clearErrors();
};

const initFormForPlan = (plan) => {
    if (!plan) {
        return;
    }

    form.package_hub_id = plan.id;
    form.order_limit = plan.order_rate_token || 100;
    form.total_amount = plan.package_price ?? 0;
    form.website_url = props.domains[0] ?? '';
    form.email = page.props.auth?.user?.email ?? '';
    form.contact_number = page.props.auth?.user?.phone ?? '';
    form.whatsapp_number = page.props.auth?.user?.phone ?? '';
};

watch(
    () => props.visible,
    (open) => {
        if (open) {
            resetWizard();
            initFormForPlan(props.plan);
        }
    },
);

watch(
    () => page.props.flash?.subscription_submitted,
    (payload) => {
        if (payload) {
            submittedSummary.value = payload;
            currentStep.value = activeSteps.value.length;
            emit('submitted', payload);
        }
    },
);

const close = () => emit('update:visible', false);

const stepLabel = (index) => activeSteps.value[index]?.label ?? '';

const contactFieldsValid = computed(() => Boolean(
    form.website_url
    && form.email
    && form.contact_number
    && form.whatsapp_number
    && form.address,
));

const paymentFieldsValid = computed(() => Boolean(
    form.transaction_method
    && form.transaction_id?.trim()
    && form.account_number?.trim(),
));

const canGoNext = computed(() => {
    if (currentStep.value === 0) {
        return Boolean(props.plan);
    }

    if (currentStep.value === 1) {
        return contactFieldsValid.value;
    }

    if (currentStep.value === 3 && !isFreeTrial.value) {
        return paymentFieldsValid.value;
    }

    return true;
});

const validationHint = computed(() => {
    if (!attemptedAdvance.value || canGoNext.value) {
        return null;
    }

    if (currentStep.value === 1) {
        return 'সব * চিহ্নিত ফিল্ড পূরণ করুন।';
    }

    if (currentStep.value === 3 && !isFreeTrial.value) {
        return 'পেমেন্ট পদ্ধতি, লেনদেন আইডি ও পাঠানোর নম্বর অবশ্যই দিন।';
    }

    return null;
});

const nextStep = () => {
    attemptedAdvance.value = true;

    if (!canGoNext.value) {
        return;
    }

    attemptedAdvance.value = false;

    if (currentStep.value < activeSteps.value.length - 1) {
        currentStep.value += 1;
    }
};

const prevStep = () => {
    if (currentStep.value > 0) {
        attemptedAdvance.value = false;
        currentStep.value -= 1;
    }
};

const submitInquiry = () => {
    attemptedAdvance.value = true;

    if (!canGoNext.value) {
        return;
    }

    form.post(route('pricing.subscribe'), {
        preserveScroll: true,
        onSuccess: () => {
            attemptedAdvance.value = false;
        },
    });
};

const fieldClass = (error) => [
    'mt-1 w-full rounded-lg border bg-white/5 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400/50',
    error ? 'border-red-400/60' : 'border-white/15',
];
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        dismissable-mask
        :style="{ width: 'min(100vw - 1.5rem, 44rem)' }"
        :header="copy.title"
        @update:visible="(value) => emit('update:visible', value)"
    >
        <div v-if="submittedSummary || currentStep >= activeSteps.length" class="space-y-5 py-2 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="text-xl font-bold text-white">{{ copy.successTitle }}</h3>
            <p class="text-sm leading-relaxed text-slate-400">{{ copy.successMessage }}</p>
            <p v-if="submittedSummary?.plan_title" class="text-sm text-amber-300">
                প্ল্যান: {{ submittedSummary.plan_title }}
            </p>
            <WhatsAppSupportBar
                v-if="whatsappSupportUrl"
                :url="whatsappSupportUrl"
                :phone="whatsappDisplayPhone"
            />
            <button
                type="button"
                class="w-full rounded-xl bg-amber-500 py-3 text-sm font-bold text-black"
                @click="close"
            >
                বন্ধ করুন
            </button>
        </div>

        <div v-else class="space-y-5">
            <WhatsAppSupportBar
                v-if="whatsappSupportUrl"
                :url="whatsappSupportUrl"
                :phone="whatsappDisplayPhone"
            />
            <p class="text-center text-xs text-slate-500">{{ copy.supportHint }}</p>

            <p
                v-if="validationHint"
                class="rounded-lg border border-red-400/30 bg-red-500/10 px-3 py-2 text-center text-sm text-red-300"
                role="alert"
            >
                {{ validationHint }}
            </p>

            <ol class="flex flex-wrap items-center justify-center gap-2 text-xs">
                <li
                    v-for="(step, index) in activeSteps"
                    :key="step.key"
                    class="flex items-center gap-2"
                >
                    <span
                        class="flex h-7 min-w-7 items-center justify-center rounded-full px-2 font-bold"
                        :class="index === currentStep
                            ? 'bg-amber-500 text-black'
                            : index < currentStep
                                ? 'bg-emerald-500/20 text-emerald-300'
                                : 'bg-white/10 text-slate-400'"
                    >
                        {{ index + 1 }}
                    </span>
                    <span :class="index === currentStep ? 'font-semibold text-white' : 'text-slate-500'">
                        {{ step.label }}
                    </span>
                    <span v-if="index < activeSteps.length - 1" class="text-slate-600">→</span>
                </li>
            </ol>

            <!-- Step 1: Plan -->
            <div v-if="currentStep === 0 && plan" class="space-y-4">
                <div class="rounded-xl border border-amber-400/30 bg-amber-500/10 p-4">
                    <p class="text-sm font-semibold text-amber-300">{{ plan.duration_label }}</p>
                    <h3 class="mt-1 text-lg font-bold text-white">{{ plan.title }}</h3>
                    <p class="mt-2 text-3xl font-extrabold text-white">{{ plan.price_label }}</p>
                    <p class="text-sm text-slate-400">{{ plan.token_label }}</p>
                    <p v-if="plan.website_label" class="text-sm text-slate-400">{{ plan.website_label }}</p>
                </div>
                <PlanFeatureList :plan="plan" compact :show-count="false" />
                <p v-if="isFreeTrial" class="rounded-lg border border-white/10 bg-white/5 p-3 text-sm text-slate-300">
                    লগইন ছাড়াই অনুরোধ জমা দিতে পারবেন — পরবর্তী ধাপে তথ্য দিন। প্রয়োজনে WhatsApp-এ যোগাযোগ করুন।
                </p>
            </div>

            <!-- Step 2: Contact -->
            <div v-else-if="currentStep === 1" class="space-y-4">
                <p class="text-sm text-slate-400">
                    আপনার তথ্য দিন — আমরা প্ল্যান সক্রিয় করতে যোগাযোগ করব।
                </p>

                <div v-if="domains.length" class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">ওয়েবসাইট (নিবন্ধিত)</label>
                    <select
                        v-model="form.website_url"
                        :class="fieldClass(form.errors.website_url)"
                    >
                        <option v-for="domain in domains" :key="domain" :value="domain">{{ domain }}</option>
                    </select>
                </div>
                <div v-else class="space-y-1">
                    <label class="text-sm font-medium text-slate-300">ওয়েবসাইট URL *</label>
                    <input
                        v-model="form.website_url"
                        type="text"
                        placeholder="যেমন: myshop.com"
                        :class="fieldClass(form.errors.website_url)"
                    >
                    <p class="text-xs text-slate-500">WooCommerce সাইটের ডোমেইন লিখুন</p>
                </div>
                <small v-if="form.errors.website_url" class="text-red-400">{{ form.errors.website_url }}</small>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2 space-y-1">
                        <label class="text-sm font-medium text-slate-300">নাম (ঐচ্ছিক)</label>
                        <input v-model="form.customer_name" type="text" placeholder="আপনার নাম" :class="fieldClass()">
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">ইমেইল *</label>
                        <input v-model="form.email" type="email" placeholder="email@example.com" :class="fieldClass(form.errors.email)">
                        <small v-if="form.errors.email" class="text-red-400">{{ form.errors.email }}</small>
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">মোবাইল নম্বর *</label>
                        <input v-model="form.contact_number" type="tel" placeholder="01XXXXXXXXX" :class="fieldClass(form.errors.contact_number)">
                        <small v-if="form.errors.contact_number" class="text-red-400">{{ form.errors.contact_number }}</small>
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">WhatsApp নম্বর *</label>
                        <input v-model="form.whatsapp_number" type="tel" placeholder="01XXXXXXXXX" :class="fieldClass(form.errors.whatsapp_number)">
                        <small v-if="form.errors.whatsapp_number" class="text-red-400">{{ form.errors.whatsapp_number }}</small>
                    </div>
                    <div class="sm:col-span-2 space-y-1">
                        <label class="text-sm font-medium text-slate-300">ঠিকানা *</label>
                        <textarea
                            v-model="form.address"
                            rows="2"
                            placeholder="জেলা, উপজেলা, বিস্তারিত ঠিকানা"
                            :class="fieldClass(form.errors.address)"
                        />
                        <small v-if="form.errors.address" class="text-red-400">{{ form.errors.address }}</small>
                    </div>
                </div>
            </div>

            <!-- Step 3: Payment guide -->
            <div v-else-if="currentStep === 2 && !isFreeTrial" class="space-y-4">
                <div class="rounded-lg border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-center">
                    <p class="text-sm text-slate-300">পরিশোধযোগ্য পরিমাণ</p>
                    <p class="text-3xl font-extrabold text-white">{{ plan?.price_label }}</p>
                </div>
                <p class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-300">
                    নিচের নির্দেশ অনুযায়ী bKash/Rocket-এ পেমেন্ট সম্পন্ন করুন, তারপর «পরবর্তী ধাপ»-এ ক্লিক করুন।
                </p>
                <SubscriptionPaymentGuideBn :methods="paymentMethods" />
            </div>

            <!-- Step 4: Payment details -->
            <div v-else-if="currentStep === 3 && !isFreeTrial" class="space-y-4">
                <p class="text-sm text-slate-400">
                    পেমেন্ট করার পর নিচে লেনদেন আইডি ও আপনার নম্বর লিখুন।
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">পেমেন্ট পদ্ধতি *</label>
                        <select v-model="form.transaction_method" :class="fieldClass(form.errors.transaction_method)">
                            <option v-for="m in gatewayMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">লেনদেন আইডি *</label>
                        <input v-model="form.transaction_id" type="text" placeholder="যেমন: 8N7A2XX" :class="fieldClass(form.errors.transaction_id)">
                        <small v-if="form.errors.transaction_id" class="text-red-400">{{ form.errors.transaction_id }}</small>
                    </div>
                    <div class="sm:col-span-2 space-y-1">
                        <label class="text-sm font-medium text-slate-300">যে নম্বর থেকে পাঠিয়েছেন *</label>
                        <input v-model="form.account_number" type="tel" placeholder="01XXXXXXXXX" :class="fieldClass(form.errors.account_number)">
                        <small v-if="form.errors.account_number" class="text-red-400">{{ form.errors.account_number }}</small>
                    </div>
                    <div class="sm:col-span-2 space-y-1">
                        <label class="text-sm font-medium text-slate-300">অতিরিক্ত নোট (ঐচ্ছিক)</label>
                        <textarea v-model="form.note" rows="2" placeholder="কিছু জানাতে চাইলে লিখুন" :class="fieldClass()" />
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-white/10 pt-4">
                <button
                    v-if="currentStep > 0 && !(submittedSummary || currentStep >= activeSteps.length)"
                    type="button"
                    class="rounded-xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    @click="prevStep"
                >
                    পেছনে
                </button>
                <div class="flex-1" />
                <button
                    v-if="currentStep < activeSteps.length - 1"
                    type="button"
                    class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-bold text-black disabled:opacity-50"
                    @click="nextStep"
                >
                    পরবর্তী ধাপ
                </button>
                <button
                    v-else-if="!(submittedSummary || currentStep >= activeSteps.length)"
                    type="button"
                    class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-bold text-black disabled:opacity-50"
                    :disabled="form.processing"
                    @click="submitInquiry"
                >
                    {{ form.processing ? 'জমা হচ্ছে...' : (isFreeTrial ? 'অনুরোধ জমা দিন' : 'পেমেন্ট তথ্য জমা দিন') }}
                </button>
            </div>
        </div>
    </Dialog>
</template>
