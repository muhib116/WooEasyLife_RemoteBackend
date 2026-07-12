<script setup>
import { computed, ref, watch, onUnmounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import WhatsAppSupportBar from '@/components/marketing/WhatsAppSupportBar.vue';
import SubscriptionPaymentGuideBn from '@/components/marketing/SubscriptionPaymentGuideBn.vue';
import PlanFeatureList from '@/components/marketing/PlanFeatureList.vue';
import { isValidDomainHost, normalizeDomainInput, validateDomainInput } from '@/utils/domain';
import { isValidBdMobile, isValidEmail, validateBdMobile, validateEmail } from '@/utils/contactValidation';

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
    pendingInquiry: { type: Object, default: null },
});

const emit = defineEmits(['update:visible', 'submitted']);

const page = usePage();
const toast = useToast();
const currentStep = ref(0);
const submittedSummary = ref(null);
const domainFieldError = ref(null);
const emailFieldError = ref(null);
const mobileFieldError = ref(null);
const whatsappFieldError = ref(null);
const senderNumberFieldError = ref(null);

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
];

const partnerToGatewayValue = (partner) => {
    const normalized = String(partner ?? '').trim().toLowerCase();

    if (normalized === 'bkash') {
        return 'Bkash';
    }

    if (normalized === 'rocket') {
        return 'Rocket';
    }

    if (normalized === 'nagad') {
        return 'Nagad';
    }

    return gatewayMethods.find((method) => method.label.toLowerCase() === normalized)?.value
        ?? form.transaction_method
        ?? 'Bkash';
};

const selectGatewayMethod = (value) => {
    form.transaction_method = value;
};

const onPaymentGuideSelect = (partner) => {
    form.transaction_method = partnerToGatewayValue(partner);
};

const gatewayMethodClass = (value) => [
    'rounded-lg px-2 py-2.5 text-center text-sm font-semibold transition',
    form.transaction_method === value
        ? 'bg-amber-500 text-black shadow-sm shadow-amber-900/30 ring-2 ring-amber-300/40'
        : 'bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white',
];

const resetWizard = () => {
    currentStep.value = 0;
    submittedSummary.value = null;
    domainFieldError.value = null;
    emailFieldError.value = null;
    mobileFieldError.value = null;
    whatsappFieldError.value = null;
    senderNumberFieldError.value = null;
    form.clearErrors();
    form.reset();
    applyPlanToForm(props.plan);
};

const applyPlanToForm = (plan) => {
    if (!plan?.id) {
        return;
    }

    form.package_hub_id = Number(plan.id);
    form.order_limit = plan.order_rate_token || 100;
    form.total_amount = plan.package_price ?? 0;
};

const seedContactDefaults = () => {
    if (!form.website_url) {
        form.website_url = props.domains[0] ?? '';
    }
    if (!form.email) {
        form.email = page.props.auth?.user?.email ?? '';
    }
    if (!form.customer_name) {
        form.customer_name = page.props.auth?.user?.name ?? '';
    }
    if (!form.contact_number) {
        form.contact_number = page.props.auth?.user?.phone ?? '';
    }
    if (!form.whatsapp_number) {
        form.whatsapp_number = page.props.auth?.user?.phone ?? '';
    }
    if (!form.transaction_method) {
        form.transaction_method = 'Bkash';
    }
};

const TOAST_GROUP = 'subscription-wizard';

const showWizardToast = ({ severity = 'warn', summary, detail, missing = [] }) => {
    toast.removeGroup(TOAST_GROUP);
    toast.add({
        severity,
        summary,
        detail,
        missing,
        group: TOAST_GROUP,
        life: 5200,
        closable: true,
    });
};

const formatMissingList = (items) => {
    if (!items.length) {
        return '';
    }

    if (items.length === 1) {
        return items[0];
    }

    if (items.length === 2) {
        return `${items[0]} ও ${items[1]}`;
    }

    return `${items.slice(0, -1).join(', ')} এবং ${items[items.length - 1]}`;
};

const showValidationToast = (payload) => {
    if (typeof payload === 'string') {
        showWizardToast({
            summary: 'তথ্য যাচাই প্রয়োজন',
            detail: payload,
        });
        return;
    }

    const missing = payload?.missing ?? [];
    const summary = payload?.summary ?? 'প্রয়োজনীয় তথ্য অসম্পূর্ণ';
    const detail = payload?.detail
        ?? (missing.length
            ? `পরবর্তী ধাপে যেতে ${formatMissingList(missing)} পূরণ করুন।`
            : 'অনুগ্রহ করে চিহ্নিত ফিল্ডগুলো পূরণ করুন।');

    showWizardToast({ summary, detail, missing });
};

watch(
    () => [props.visible, props.plan?.id],
    ([open]) => {
        if (open) {
            if (props.pendingInquiry?.id) {
                emit('update:visible', false);
                showWizardToast({
                    severity: 'warn',
                    summary: 'অনুরোধ প্রক্রিয়াধীন',
                    detail: props.pendingInquiry.message
                        || 'আপনার একটি সাবস্ক্রিপশন অনুরোধ এখনও প্রক্রিয়াধীন।',
                });
                return;
            }

            resetWizard();
            seedContactDefaults();
            document.body.style.overflow = 'hidden';
        } else {
            toast.removeGroup(TOAST_GROUP);
            document.body.style.overflow = '';
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

const currentStepLabel = computed(() => activeSteps.value[currentStep.value]?.label ?? '');

const websiteUrlError = computed(() => domainFieldError.value || form.errors.website_url || null);
const emailError = computed(() => emailFieldError.value || form.errors.email || null);
const mobileError = computed(() => mobileFieldError.value || form.errors.contact_number || null);
const whatsappError = computed(() => whatsappFieldError.value || form.errors.whatsapp_number || null);
const senderNumberError = computed(() => senderNumberFieldError.value || form.errors.account_number || null);

const isDomainInputValid = computed(() => {
    if (props.domains.length) {
        return Boolean(form.website_url?.trim());
    }

    const domain = normalizeDomainInput(form.website_url);

    return Boolean(domain && isValidDomainHost(domain) && !domainFieldError.value);
});

const contactFieldsValid = computed(() => Boolean(
    isDomainInputValid.value
    && form.customer_name?.trim()
    && isValidEmail(form.email)
    && isValidBdMobile(form.contact_number)
    && isValidBdMobile(form.whatsapp_number)
    && form.address?.trim()
    && !emailFieldError.value
    && !mobileFieldError.value
    && !whatsappFieldError.value,
));

const onDomainInput = () => {
    if (domainFieldError.value) {
        domainFieldError.value = null;
    }

    if (form.errors.website_url) {
        form.clearErrors('website_url');
    }
};

const onDomainBlur = () => {
    if (props.domains.length) {
        return;
    }

    const result = validateDomainInput(form.website_url);
    domainFieldError.value = result.message;

    if (result.valid && result.domain) {
        form.website_url = result.domain;
    }
};

const onEmailInput = () => {
    emailFieldError.value = null;
    if (form.errors.email) {
        form.clearErrors('email');
    }
};

const onEmailBlur = () => {
    const result = validateEmail(form.email);
    emailFieldError.value = result.message;

    if (result.valid && result.value) {
        form.email = result.value;
    }
};

const onMobileInput = () => {
    mobileFieldError.value = null;
    if (form.errors.contact_number) {
        form.clearErrors('contact_number');
    }
};

const onMobileBlur = () => {
    const result = validateBdMobile(form.contact_number, 'মোবাইল নম্বর');
    mobileFieldError.value = result.message;

    if (result.valid && result.value) {
        form.contact_number = result.value;
    }
};

const onWhatsappInput = () => {
    whatsappFieldError.value = null;
    if (form.errors.whatsapp_number) {
        form.clearErrors('whatsapp_number');
    }
};

const onWhatsappBlur = () => {
    const result = validateBdMobile(form.whatsapp_number, 'WhatsApp নম্বর');
    whatsappFieldError.value = result.message;

    if (result.valid && result.value) {
        form.whatsapp_number = result.value;
    }
};

const onSenderNumberInput = () => {
    senderNumberFieldError.value = null;
    if (form.errors.account_number) {
        form.clearErrors('account_number');
    }
};

const onSenderNumberBlur = () => {
    const result = validateBdMobile(form.account_number, 'পাঠানোর নম্বর');
    senderNumberFieldError.value = result.message;

    if (result.valid && result.value) {
        form.account_number = result.value;
    }
};

const paymentFieldsValid = computed(() => Boolean(
    form.transaction_method
    && form.transaction_id?.trim()
    && isValidBdMobile(form.account_number)
    && !senderNumberFieldError.value,
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

const contactValidationMessage = () => {
    const missing = [];

    if (props.domains.length) {
        if (!form.website_url?.trim()) {
            missing.push('ডোমেইন নাম/ওয়েবসাইটের নাম');
        }
    } else {
        const domainResult = validateDomainInput(form.website_url);

        if (!domainResult.valid) {
            domainFieldError.value = domainResult.message;

            return {
                summary: 'ডোমেইন যাচাই প্রয়োজন',
                detail: domainResult.message,
                missing: ['ডোমেইন নাম/ওয়েবসাইটের নাম'],
            };
        }

        form.website_url = domainResult.domain;
        domainFieldError.value = null;
    }

    if (!form.customer_name?.trim()) {
        missing.push('নাম');
    }

    const emailResult = validateEmail(form.email);
    if (!emailResult.valid) {
        emailFieldError.value = emailResult.message;

        return {
            summary: 'ইমেইল যাচাই প্রয়োজন',
            detail: emailResult.message,
            missing: ['ইমেইল'],
        };
    }
    form.email = emailResult.value;
    emailFieldError.value = null;

    const mobileResult = validateBdMobile(form.contact_number, 'মোবাইল নম্বর');
    if (!mobileResult.valid) {
        mobileFieldError.value = mobileResult.message;

        return {
            summary: 'মোবাইল নম্বর যাচাই প্রয়োজন',
            detail: mobileResult.message,
            missing: ['মোবাইল নম্বর'],
        };
    }
    form.contact_number = mobileResult.value;
    mobileFieldError.value = null;

    const whatsappResult = validateBdMobile(form.whatsapp_number, 'WhatsApp নম্বর');
    if (!whatsappResult.valid) {
        whatsappFieldError.value = whatsappResult.message;

        return {
            summary: 'WhatsApp নম্বর যাচাই প্রয়োজন',
            detail: whatsappResult.message,
            missing: ['WhatsApp নম্বর'],
        };
    }
    form.whatsapp_number = whatsappResult.value;
    whatsappFieldError.value = null;

    if (!form.address?.trim()) {
        missing.push('ঠিকানা');
    }

    if (!missing.length) {
        return null;
    }

    return {
        summary: 'যোগাযোগের তথ্য অসম্পূর্ণ',
        detail: 'সাবস্ক্রিপশন অনুরোধ এগিয়ে নিতে নিচের প্রয়োজনীয় তথ্য দিন।',
        missing,
    };
};

const paymentValidationMessage = () => {
    const missing = [];

    if (!form.transaction_method) {
        missing.push('পেমেন্ট পদ্ধতি');
    }
    if (!form.transaction_id?.trim()) {
        missing.push('ট্রানজেকশন আইডি');
    }

    const senderResult = validateBdMobile(form.account_number, 'পাঠানোর নম্বর');
    if (!senderResult.valid) {
        senderNumberFieldError.value = senderResult.message;

        return {
            summary: 'পাঠানোর নম্বর যাচাই প্রয়োজন',
            detail: senderResult.message,
            missing: ['পাঠানোর নম্বর'],
        };
    }
    form.account_number = senderResult.value;
    senderNumberFieldError.value = null;

    if (!missing.length) {
        return null;
    }

    return {
        summary: 'পেমেন্ট তথ্য অসম্পূর্ণ',
        detail: 'যাচাইয়ের জন্য নিচের পেমেন্ট বিবরণ প্রদান করুন।',
        missing,
    };
};

const currentValidationMessage = () => {
    if (currentStep.value === 1) {
        return contactValidationMessage();
    }

    if (currentStep.value === 3 && !isFreeTrial.value) {
        return paymentValidationMessage();
    }

    if (currentStep.value === 0 && !props.plan) {
        return {
            summary: 'প্ল্যান নির্বাচন প্রয়োজন',
            detail: 'সাবস্ক্রিপশন চালিয়ে যেতে একটি প্ল্যান বেছে নিন।',
            missing: [],
        };
    }

    return {
        summary: 'তথ্য যাচাই প্রয়োজন',
        detail: 'অনুগ্রহ করে সব চিহ্নিত ফিল্ড পূরণ করুন।',
        missing: [],
    };
};

const nextStep = () => {
    if (!canGoNext.value) {
        showValidationToast(currentValidationMessage());
        return;
    }

    if (currentStep.value < activeSteps.value.length - 1) {
        currentStep.value += 1;
    }
};

const prevStep = () => {
    if (currentStep.value > 0) {
        currentStep.value -= 1;
    }
};

const submitInquiry = () => {
    if (!canGoNext.value) {
        showValidationToast(currentValidationMessage());
        return;
    }

    applyPlanToForm(props.plan);

    if (!form.package_hub_id) {
        showWizardToast({
            severity: 'error',
            summary: 'প্ল্যান পাওয়া যায়নি',
            detail: 'প্ল্যান নির্বাচন করা হয়নি। অনুগ্রহ করে মডাল বন্ধ করে আবার চেষ্টা করুন।',
        });
        return;
    }

    form
        .transform((data) => ({
            ...data,
            package_hub_id: Number(data.package_hub_id || props.plan?.id),
        }))
        .post(route('pricing.subscribe'), {
            preserveScroll: true,
            onError: (errors) => {
                const first = errors.subscription
                    || errors.website_url
                    || Object.values(errors).find(Boolean);
                showWizardToast({
                    severity: 'error',
                    summary: (errors.subscription || errors.website_url)
                        ? 'ডোমেইন যাচাই ব্যর্থ'
                        : 'অনুরোধ জমা দেওয়া যায়নি',
                    detail: first || 'তথ্য যাচাইয়ে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
                });

                if (errors.website_url) {
                    domainFieldError.value = errors.website_url;
                }
            },
        });
};

const toastAccentClass = (severity) => {
    if (severity === 'error') {
        return 'marketing-toast--error';
    }

    if (severity === 'success') {
        return 'marketing-toast--success';
    }

    return 'marketing-toast--warn';
};

const fieldClass = (error) => [
    'mt-1 w-full rounded-lg border bg-white/5 px-3 py-2.5 text-base text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400/50 sm:text-sm',
    error ? 'border-red-400/60' : 'border-white/15',
];

const stepProgressClass = (index) => {
    if (index === currentStep.value) {
        return 'bg-amber-500';
    }

    if (index < currentStep.value) {
        return 'bg-emerald-500/70';
    }

    return 'bg-white/15';
};

onUnmounted(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <Toast
        position="top-center"
        group="subscription-wizard"
        :baseZIndex="10000"
        class="marketing-toast-host"
    >
        <template #container="{ message, closeCallback }">
            <div
                class="marketing-toast"
                :class="toastAccentClass(message.severity)"
                role="alert"
                aria-live="polite"
            >
                <div class="marketing-toast__accent" aria-hidden="true" />
                <div class="marketing-toast__icon" aria-hidden="true">
                    <svg v-if="message.severity === 'error'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <svg v-else-if="message.severity === 'success'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <div class="marketing-toast__body">
                    <p class="marketing-toast__title">{{ message.summary }}</p>
                    <p v-if="message.detail" class="marketing-toast__detail">{{ message.detail }}</p>
                    <ul v-if="message.missing?.length" class="marketing-toast__tags">
                        <li v-for="item in message.missing" :key="item">{{ item }}</li>
                    </ul>
                </div>
                <button
                    type="button"
                    class="marketing-toast__close"
                    aria-label="বন্ধ করুন"
                    @click="closeCallback"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="marketing-toast__progress" aria-hidden="true" />
            </div>
        </template>
    </Toast>
    <Dialog
        :visible="visible"
        modal
        dismissable-mask
        block-scroll
        :draggable="false"
        class="marketing-dialog"
        :style="{ width: 'min(100vw - 1rem, 44rem)' }"
        :header="copy.title"
        @update:visible="(value) => emit('update:visible', value)"
    >
        <div class="subscription-wizard-body">
            <div class="subscription-wizard-scroll">
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

                <div v-else class="space-y-4 sm:space-y-5">
                    <WhatsAppSupportBar
                        v-if="whatsappSupportUrl"
                        :url="whatsappSupportUrl"
                        :phone="whatsappDisplayPhone"
                    />
                    <p class="text-center text-xs text-slate-500">{{ copy.supportHint }}</p>

                    <!-- Mobile step progress -->
                    <div class="sm:hidden">
                        <div class="flex items-center gap-1.5">
                            <span
                                v-for="(step, index) in activeSteps"
                                :key="step.key"
                                class="h-1.5 flex-1 rounded-full transition-colors"
                                :class="stepProgressClass(index)"
                                :aria-hidden="index !== currentStep"
                            />
                        </div>
                        <p class="mt-2 text-center text-sm font-semibold text-white">
                            ধাপ {{ currentStep + 1 }}/{{ activeSteps.length }} — {{ currentStepLabel }}
                        </p>
                    </div>

                    <!-- Desktop step labels -->
                    <ol class="hidden flex-wrap items-center justify-center gap-2 text-xs sm:flex">
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
                        <PlanFeatureList :plan="plan" compact scrollable :show-count="false" />
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
                            <label class="text-sm font-medium text-slate-300">ডোমেইন নাম/ওয়েবসাইটের নাম *</label>
                            <input
                                v-model="form.website_url"
                                type="text"
                                placeholder="যেমন: myshop.com"
                                autocomplete="url"
                                :class="fieldClass(websiteUrlError)"
                                @input="onDomainInput"
                                @blur="onDomainBlur"
                            >
                            <p class="text-xs text-slate-500">WooCommerce সাইটের ডোমেইন বা ওয়েবসাইটের নাম লিখুন</p>
                        </div>
                        <small v-if="websiteUrlError" class="text-red-400">{{ websiteUrlError }}</small>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2 space-y-1">
                                <label class="text-sm font-medium text-slate-300">নাম *</label>
                                <input v-model="form.customer_name" type="text" placeholder="আপনার নাম" autocomplete="name" :class="fieldClass(form.errors.customer_name)">
                                <small v-if="form.errors.customer_name" class="text-red-400">{{ form.errors.customer_name }}</small>
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">ইমেইল *</label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    placeholder="email@example.com"
                                    autocomplete="email"
                                    inputmode="email"
                                    :class="fieldClass(emailError)"
                                    @input="onEmailInput"
                                    @blur="onEmailBlur"
                                >
                                <small v-if="emailError" class="text-red-400">{{ emailError }}</small>
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">মোবাইল নম্বর *</label>
                                <input
                                    v-model="form.contact_number"
                                    type="tel"
                                    placeholder="01XXXXXXXXX"
                                    autocomplete="tel"
                                    inputmode="tel"
                                    :class="fieldClass(mobileError)"
                                    @input="onMobileInput"
                                    @blur="onMobileBlur"
                                >
                                <small v-if="mobileError" class="text-red-400">{{ mobileError }}</small>
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-300">WhatsApp নম্বর *</label>
                                <input
                                    v-model="form.whatsapp_number"
                                    type="tel"
                                    placeholder="01XXXXXXXXX"
                                    autocomplete="tel"
                                    inputmode="tel"
                                    :class="fieldClass(whatsappError)"
                                    @input="onWhatsappInput"
                                    @blur="onWhatsappBlur"
                                >
                                <small v-if="whatsappError" class="text-red-400">{{ whatsappError }}</small>
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
                    <div v-else-if="currentStep === 2 && !isFreeTrial" class="space-y-3">
                        <div class="rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-3">
                            <div class="flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-xs font-medium text-amber-200/80">পরিশোধযোগ্য পরিমাণ</p>
                                    <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
                                        {{ plan?.price_label }}
                                    </p>
                                </div>
                                <p class="max-w-[14rem] text-right text-[11px] leading-snug text-slate-400 sm:text-xs">
                                    bKash / Rocket / Nagad-এ Send Money করুন, তারপর পরবর্তী ধাপে যান।
                                </p>
                            </div>
                        </div>
                        <SubscriptionPaymentGuideBn
                            :methods="paymentMethods"
                            @select="onPaymentGuideSelect"
                        />
                    </div>

                    <!-- Step 4: Payment details -->
                    <div v-else-if="currentStep === 3 && !isFreeTrial" class="space-y-4">
                        <p class="text-sm text-slate-400">
                            পেমেন্ট করার পর নিচে ট্রানজেকশন আইডি ও আপনার নম্বর লিখুন।
                        </p>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-slate-300">পেমেন্ট পদ্ধতি *</label>
                            <div
                                class="grid gap-1.5"
                                :class="gatewayMethods.length === 1
                                    ? 'grid-cols-1'
                                    : gatewayMethods.length === 2
                                        ? 'grid-cols-2'
                                        : 'grid-cols-3'"
                                role="radiogroup"
                                aria-label="পেমেন্ট পদ্ধতি"
                            >
                                <button
                                    v-for="method in gatewayMethods"
                                    :key="method.value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="form.transaction_method === method.value"
                                    :class="gatewayMethodClass(method.value)"
                                    @click="selectGatewayMethod(method.value)"
                                >
                                    {{ method.label }}
                                </button>
                            </div>
                            <small v-if="form.errors.transaction_method" class="text-red-400">
                                {{ form.errors.transaction_method }}
                            </small>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-sm font-medium text-slate-300">ট্রানজেকশন আইডি *</label>
                                <input v-model="form.transaction_id" type="text" placeholder="যেমন: 8N7A2XX" :class="fieldClass(form.errors.transaction_id)">
                                <small v-if="form.errors.transaction_id" class="text-red-400">{{ form.errors.transaction_id }}</small>
                            </div>
                            <div class="sm:col-span-2 space-y-1">
                                <label class="text-sm font-medium text-slate-300">যে নম্বর থেকে পাঠিয়েছেন *</label>
                                <input
                                    v-model="form.account_number"
                                    type="tel"
                                    placeholder="01XXXXXXXXX"
                                    inputmode="tel"
                                    :class="fieldClass(senderNumberError)"
                                    @input="onSenderNumberInput"
                                    @blur="onSenderNumberBlur"
                                >
                                <small v-if="senderNumberError" class="text-red-400">{{ senderNumberError }}</small>
                            </div>
                            <div class="sm:col-span-2 space-y-1">
                                <label class="text-sm font-medium text-slate-300">অতিরিক্ত নোট (ঐচ্ছিক)</label>
                                <textarea v-model="form.note" rows="2" placeholder="কিছু জানাতে চাইলে লিখুন" :class="fieldClass()" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="!(submittedSummary || currentStep >= activeSteps.length)"
                class="subscription-wizard-footer flex flex-col-reverse gap-2 sm:flex-row"
            >
                <button
                    v-if="currentStep > 0"
                    type="button"
                    class="w-full rounded-xl border border-white/15 px-4 py-3 text-sm font-semibold text-white hover:bg-white/10 sm:w-auto sm:py-2.5"
                    @click="prevStep"
                >
                    পেছনে
                </button>
                <div class="hidden flex-1 sm:block" />
                <button
                    v-if="currentStep < activeSteps.length - 1"
                    type="button"
                    class="w-full rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black sm:w-auto sm:py-2.5"
                    @click="nextStep"
                >
                    পরবর্তী ধাপ
                </button>
                <button
                    v-else
                    type="button"
                    class="w-full rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black disabled:opacity-50 sm:w-auto sm:py-2.5"
                    :disabled="form.processing"
                    @click="submitInquiry"
                >
                    {{ form.processing ? 'জমা হচ্ছে...' : (isFreeTrial ? 'অনুরোধ জমা দিন' : 'পেমেন্ট তথ্য জমা দিন') }}
                </button>
            </div>
        </div>
    </Dialog>
</template>
