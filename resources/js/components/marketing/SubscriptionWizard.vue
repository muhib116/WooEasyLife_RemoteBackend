<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import WhatsAppSupportBar from '@/components/marketing/WhatsAppSupportBar.vue';
import SubscriptionPaymentGuideBn from '@/components/marketing/SubscriptionPaymentGuideBn.vue';
import PlanFeatureList from '@/components/marketing/PlanFeatureList.vue';
import { isValidDomainHost, normalizeDomainInput, validateDomainInput } from '@/utils/domain';
import { isValidBdMobile, isValidEmail, validateBdMobile, validateEmail } from '@/utils/contactValidation';
import {
    inquiryEventId,
    planContentParams,
    trackAddPaymentInfo,
    trackInitiateCheckout,
    trackLead,
    trackOnce,
    trackStartTrial,
    trackSubscribe,
    trackWizardStep,
} from '@/utils/metaPixel';

const props = defineProps({
    visible: { type: Boolean, default: false },
    plan: { type: Object, default: null },
    plans: { type: Array, default: () => [] },
    domains: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    subscriptionWizard: { type: Object, default: () => ({}) },
    whatsappSupportUrl: { type: String, default: null },
    whatsappDisplayPhone: { type: String, default: null },
    canLogin: { type: Boolean, default: false },
    pendingInquiry: { type: Object, default: null },
});

const emit = defineEmits(['update:visible', 'submitted']);

const page = usePage();
const toast = useToast();

const resolvedPaymentMethods = computed(() => {
    const fromProp = Array.isArray(props.paymentMethods) ? props.paymentMethods : [];
    if (fromProp.length) {
        return fromProp.filter((method) => method?.payment_partner && method?.account);
    }

    const fromPage = page.props.subscriptionPaymentMethods;
    if (!Array.isArray(fromPage)) {
        return [];
    }

    return fromPage.filter((method) => method?.payment_partner && method?.account);
});

const resolvedWhatsappDisplayPhone = computed(() => {
    return props.whatsappDisplayPhone
        || page.props.marketing?.admin_whatsapp
        || null;
});

const resolvedWhatsappSupportUrl = computed(() => {
    return props.whatsappSupportUrl
        || page.props.marketing?.whatsapp_contact_url
        || page.props.marketing?.whatsapp_url
        || null;
});

const currentStep = ref(0);
const submittedSummary = ref(null);
const domainFieldError = ref(null);
const nameFieldError = ref(null);
const emailFieldError = ref(null);
const mobileFieldError = ref(null);
const whatsappFieldError = ref(null);
const addressFieldError = ref(null);
const senderNumberFieldError = ref(null);
const transactionIdFieldError = ref(null);
const domainChecking = ref(false);
/** Normalized hostname that passed live DNS A-record check (null = not verified). */
const domainDnsVerifiedFor = ref(null);
const touched = ref({
    website_url: false,
    customer_name: false,
    email: false,
    contact_number: false,
    whatsapp_number: false,
    address: false,
    transaction_id: false,
    account_number: false,
});

let localValidateTimer = null;
let serverValidateTimer = null;
let serverValidateSerial = 0;

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
    transaction_method: '',
    transaction_id: '',
    account_number: '',
    transaction_charge: 0,
    note: '',
});

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

    return String(partner ?? '').trim() || form.transaction_method || '';
};

const gatewayMethods = computed(() => {
    const methods = resolvedPaymentMethods.value;

    if (!methods.length) {
        return [];
    }

    return methods.map((method) => {
        const partner = String(method.payment_partner ?? '').trim();
        const value = partnerToGatewayValue(partner);

        return {
            label: partner || value,
            value,
            account: method.account,
        };
    });
});

watch(
    gatewayMethods,
    (methods) => {
        if (!methods.length) {
            form.transaction_method = '';
            return;
        }

        const allowed = methods.map((method) => method.value);
        if (!allowed.includes(form.transaction_method)) {
            form.transaction_method = methods[0].value;
        }
    },
    { immediate: true },
);

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
    nameFieldError.value = null;
    emailFieldError.value = null;
    mobileFieldError.value = null;
    whatsappFieldError.value = null;
    addressFieldError.value = null;
    senderNumberFieldError.value = null;
    transactionIdFieldError.value = null;
    domainChecking.value = false;
    domainDnsVerifiedFor.value = null;
    touched.value = {
        website_url: false,
        customer_name: false,
        email: false,
        contact_number: false,
        whatsapp_number: false,
        address: false,
        transaction_id: false,
        account_number: false,
    };
    form.clearErrors();
    form.reset();
    lastLeadFingerprint = null;
    leadInquiryId.value = null;
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
    ([open], [wasOpen] = [false]) => {
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

            trackInitiateCheckout(planContentParams(props.plan));
            trackWizardStep('opened', props.plan);
        } else {
            if (wasOpen) {
                flushLeadSaveOnExit();
            }
            toast.removeGroup(TOAST_GROUP);
            document.body.style.overflow = '';
        }
    },
);

watch(
    () => page.props.flash?.subscription_submitted,
    (payload) => {
        if (!payload) {
            return;
        }

        submittedSummary.value = payload;
        currentStep.value = activeSteps.value.length;
        emit('submitted', payload);

        const contentName = payload.plan_title || props.plan?.title || null;
        const value = Number(payload.value ?? planContentParams(props.plan).value ?? 0);
        const currency = payload.currency || 'BDT';
        const orderId = payload.inquiry_id ?? null;
        const eventID = inquiryEventId(orderId);
        const contentIds = props.plan?.id != null ? [String(props.plan.id)] : undefined;
        const isTrial = Boolean(payload.is_free_trial || isFreeTrial.value);
        const conversionKey = eventID || `anon_${Date.now()}`;

        // Solid conversion signals: only after successful server submit.
        // Lead = qualified inquiry; StartTrial = free trial; Subscribe = paid pending review.
        // Purchase is intentionally omitted until payment is confirmed.
        trackOnce(`meta:lead:${conversionKey}`, () =>
            trackLead({
                ...planContentParams(props.plan),
                content_name: contentName,
                content_category: isTrial ? 'free_trial' : 'paid',
                value,
                currency,
                order_id: orderId != null ? String(orderId) : undefined,
            }, { eventID: eventID ? `${eventID}_lead` : undefined }),
        );

        if (isTrial) {
            trackOnce(`meta:starttrial:${conversionKey}`, () =>
                trackStartTrial({
                    value,
                    currency,
                    content_name: contentName,
                    content_ids: contentIds,
                    order_id: orderId,
                }, { eventID }),
            );
        } else {
            trackOnce(`meta:subscribe:${conversionKey}`, () =>
                trackSubscribe({
                    value,
                    currency,
                    content_name: contentName,
                    content_ids: contentIds,
                    content_type: 'product',
                    order_id: orderId != null ? String(orderId) : undefined,
                    predicted_ltv: value,
                }, { eventID }),
            );
        }

        trackWizardStep(isTrial ? 'submitted_trial' : 'submitted_paid', props.plan);
    },
);

const currentStepLabel = computed(() => activeSteps.value[currentStep.value]?.label ?? '');

const websiteUrlError = computed(() => domainFieldError.value || form.errors.website_url || null);
const nameError = computed(() => nameFieldError.value || form.errors.customer_name || null);
const emailError = computed(() => emailFieldError.value || form.errors.email || null);
const mobileError = computed(() => mobileFieldError.value || form.errors.contact_number || null);
const whatsappError = computed(() => whatsappFieldError.value || form.errors.whatsapp_number || null);
const addressError = computed(() => addressFieldError.value || form.errors.address || null);
const senderNumberError = computed(() => senderNumberFieldError.value || form.errors.account_number || null);
const transactionIdError = computed(() => transactionIdFieldError.value || form.errors.transaction_id || null);

const isDomainDnsVerified = computed(() => {
    if (props.domains.length) {
        return Boolean(form.website_url?.trim());
    }

    const domain = normalizeDomainInput(form.website_url);

    return Boolean(domain && domainDnsVerifiedFor.value === domain);
});

const isDomainInputValid = computed(() => {
    if (props.domains.length) {
        return Boolean(form.website_url?.trim());
    }

    const domain = normalizeDomainInput(form.website_url);

    return Boolean(
        domain
        && isValidDomainHost(domain)
        && !domainFieldError.value
        && !domainChecking.value
        && domainDnsVerifiedFor.value === domain,
    );
});

const contactFieldsValid = computed(() => Boolean(
    isDomainInputValid.value
    && form.customer_name?.trim()
    && isValidEmail(form.email)
    && isValidBdMobile(form.contact_number)
    && isValidBdMobile(form.whatsapp_number)
    && form.address?.trim()
    && !nameFieldError.value
    && !emailFieldError.value
    && !mobileFieldError.value
    && !whatsappFieldError.value
    && !addressFieldError.value
    && !domainFieldError.value
));

/** Contact identity is enough to capture a sales lead (DNS optional). */
const leadFieldsReady = computed(() => {
    const domain = normalizeDomainInput(form.website_url);

    return Boolean(
        form.package_hub_id
        && domain
        && isValidDomainHost(domain)
        && form.customer_name?.trim()
        && isValidEmail(form.email)
        && isValidBdMobile(form.contact_number)
        && isValidBdMobile(form.whatsapp_number)
    );
});

let leadSaveTimer = null;
let leadSaveSerial = 0;
let lastLeadFingerprint = null;
const leadInquiryId = ref(null);

const leadFingerprint = () => [
    form.package_hub_id,
    normalizeDomainInput(form.website_url) || '',
    form.customer_name?.trim() || '',
    String(form.email || '').trim().toLowerCase(),
    form.contact_number || '',
    form.whatsapp_number || '',
    form.address?.trim() || '',
    isDomainDnsVerified.value ? '1' : '0',
].join('|');

const saveLeadDraft = async ({ force = false } = {}) => {
    if (!leadFieldsReady.value || !props.plan?.id) {
        return null;
    }

    applyPlanToForm(props.plan);

    const fingerprint = leadFingerprint();
    if (!force && fingerprint === lastLeadFingerprint) {
        return leadInquiryId.value;
    }

    const serial = ++leadSaveSerial;

    try {
        const { data } = await axios.post(route('pricing.subscribe.lead'), {
            package_hub_id: Number(form.package_hub_id || props.plan?.id),
            website_url: form.website_url,
            customer_name: form.customer_name,
            email: form.email,
            contact_number: form.contact_number,
            whatsapp_number: form.whatsapp_number,
            address: form.address || '',
            order_limit: form.order_limit,
            total_amount: form.total_amount,
            dns_verified: isDomainDnsVerified.value,
        });

        if (serial !== leadSaveSerial) {
            return null;
        }

        if (data?.ok) {
            lastLeadFingerprint = fingerprint;
            leadInquiryId.value = data.inquiry_id ?? leadInquiryId.value;
            return data.inquiry_id;
        }
    } catch {
        // Soft-save must never block the wizard.
    }

    return null;
};

const scheduleLeadSave = () => {
    if (leadSaveTimer) {
        clearTimeout(leadSaveTimer);
    }

    leadSaveTimer = setTimeout(() => {
        void saveLeadDraft();
    }, 1200);
};

/** Cancel debounce and persist immediately (modal close / tab hide). */
const flushLeadSaveOnExit = () => {
    if (leadSaveTimer) {
        clearTimeout(leadSaveTimer);
        leadSaveTimer = null;
    }

    if (!leadFieldsReady.value || submittedSummary.value) {
        return;
    }

    // force:false — skip if this exact payload was already saved (avoids triple POST on close).
    void saveLeadDraft({ force: false });
};

const close = () => {
    flushLeadSaveOnExit();
    emit('update:visible', false);
};

const onWizardVisibleUpdate = (value) => {
    if (!value) {
        flushLeadSaveOnExit();
    }
    emit('update:visible', value);
};

const onDocumentVisibilityChange = () => {
    if (document.visibilityState === 'hidden' && props.visible) {
        flushLeadSaveOnExit();
    }
};

const onPageHide = () => {
    if (props.visible) {
        flushLeadSaveOnExit();
    }
};

watch(leadFieldsReady, (ready) => {
    if (ready && props.visible) {
        scheduleLeadSave();
    }
});

watch(
    () => [
        form.website_url,
        form.customer_name,
        form.email,
        form.contact_number,
        form.whatsapp_number,
        form.address,
        form.package_hub_id,
        isDomainDnsVerified.value,
    ],
    () => {
        if (leadFieldsReady.value && props.visible) {
            scheduleLeadSave();
        }
    },
);

const markTouched = (field) => {
    touched.value[field] = true;
};

const clearServerFieldError = (field) => {
    if (form.errors[field]) {
        form.clearErrors(field);
    }
};

const validateNameLocal = (force = false) => {
    if (!force && !touched.value.customer_name && !form.customer_name?.trim()) {
        nameFieldError.value = null;
        return true;
    }

    if (!form.customer_name?.trim()) {
        nameFieldError.value = 'আপনার নাম লিখুন।';
        return false;
    }

    nameFieldError.value = null;
    return true;
};

const validateEmailLocal = (force = false) => {
    const value = form.email?.trim() ?? '';
    if (!force && !touched.value.email && value === '') {
        emailFieldError.value = null;
        return true;
    }

    const result = validateEmail(form.email);
    emailFieldError.value = result.message;
    return result.valid;
};

const validateMobileLocal = (force = false) => {
    const value = form.contact_number?.trim() ?? '';
    if (!force && !touched.value.contact_number && value === '') {
        mobileFieldError.value = null;
        return true;
    }

    // Avoid noisy errors while user is still typing a short number.
    if (!force && value !== '' && value.replace(/\D/g, '').length < 11) {
        mobileFieldError.value = null;
        return false;
    }

    const result = validateBdMobile(form.contact_number, 'মোবাইল নম্বর');
    mobileFieldError.value = result.message;
    if (result.valid && result.value) {
        form.contact_number = result.value;
    }
    return result.valid;
};

const validateWhatsappLocal = (force = false) => {
    const value = form.whatsapp_number?.trim() ?? '';
    if (!force && !touched.value.whatsapp_number && value === '') {
        whatsappFieldError.value = null;
        return true;
    }

    if (!force && value !== '' && value.replace(/\D/g, '').length < 11) {
        whatsappFieldError.value = null;
        return false;
    }

    const result = validateBdMobile(form.whatsapp_number, 'WhatsApp নম্বর');
    whatsappFieldError.value = result.message;
    if (result.valid && result.value) {
        form.whatsapp_number = result.value;
    }
    return result.valid;
};

const validateAddressLocal = (force = false) => {
    if (!force && !touched.value.address && !form.address?.trim()) {
        addressFieldError.value = null;
        return true;
    }

    if (!form.address?.trim()) {
        addressFieldError.value = 'ঠিকানা লিখুন।';
        return false;
    }

    addressFieldError.value = null;
    return true;
};

const validateDomainLocal = (force = false) => {
    if (props.domains.length) {
        domainFieldError.value = null;
        return true;
    }

    const value = form.website_url?.trim() ?? '';
    if (!force && !touched.value.website_url && value === '') {
        domainFieldError.value = null;
        return true;
    }

    const result = validateDomainInput(form.website_url);
    domainFieldError.value = result.message;
    return result.valid;
};

const validateTransactionIdLocal = (force = false) => {
    const value = form.transaction_id?.trim() ?? '';
    if (!force && !touched.value.transaction_id && value === '') {
        transactionIdFieldError.value = null;
        return true;
    }

    if (!value) {
        transactionIdFieldError.value = 'ট্রানজেকশন আইডি লিখুন।';
        return false;
    }

    if (value.length < 4) {
        transactionIdFieldError.value = 'সঠিক ট্রানজেকশন আইডি লিখুন।';
        return false;
    }

    transactionIdFieldError.value = null;
    return true;
};

const validateSenderLocal = (force = false) => {
    const value = form.account_number?.trim() ?? '';
    if (!force && !touched.value.account_number && value === '') {
        senderNumberFieldError.value = null;
        return true;
    }

    if (!force && value !== '' && value.replace(/\D/g, '').length < 11) {
        senderNumberFieldError.value = null;
        return false;
    }

    const result = validateBdMobile(form.account_number, 'পাঠানোর নম্বর');
    senderNumberFieldError.value = result.message;
    if (result.valid && result.value) {
        form.account_number = result.value;
    }
    return result.valid;
};

const runLocalContactValidation = (force = false) => {
    validateDomainLocal(force);
    validateNameLocal(force);
    validateEmailLocal(force);
    validateMobileLocal(force);
    validateWhatsappLocal(force);
    validateAddressLocal(force);
};

const scheduleLocalValidation = () => {
    if (localValidateTimer) {
        clearTimeout(localValidateTimer);
    }

    localValidateTimer = setTimeout(() => {
        runLocalContactValidation(false);
    }, 280);
};

const scheduleServerValidation = () => {
    if (serverValidateTimer) {
        clearTimeout(serverValidateTimer);
    }

    serverValidateTimer = setTimeout(() => {
        void runServerValidation();
    }, 550);
};

const runServerValidation = async () => {
    if (props.domains.length) {
        domainDnsVerifiedFor.value = form.website_url?.trim() || null;
        return true;
    }

    const domainResult = validateDomainInput(form.website_url);
    if (!domainResult.valid) {
        domainDnsVerifiedFor.value = null;
        return false;
    }

    const expectedDomain = domainResult.domain || normalizeDomainInput(form.website_url);
    const serial = ++serverValidateSerial;
    domainChecking.value = true;

    try {
        const { data } = await axios.post(
            route('pricing.subscribe.validate'),
            {
                website_url: expectedDomain || form.website_url,
                email: form.email,
                contact_number: form.contact_number,
                whatsapp_number: form.whatsapp_number,
            },
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (serial !== serverValidateSerial) {
            return false;
        }

        const normalizedDomain = data.normalized?.website_url || expectedDomain;
        if (data.normalized?.website_url) {
            form.website_url = data.normalized.website_url;
        }

        if (data.errors?.website_url) {
            domainFieldError.value = data.errors.website_url;
            domainDnsVerifiedFor.value = null;
        } else {
            if (!validateDomainInput(form.website_url).message) {
                domainFieldError.value = null;
            }
            domainDnsVerifiedFor.value = normalizedDomain;
        }

        if (data.errors?.email && touched.value.email) {
            emailFieldError.value = data.errors.email;
        }
        if (data.errors?.contact_number && touched.value.contact_number) {
            mobileFieldError.value = data.errors.contact_number;
        }
        if (data.errors?.whatsapp_number && touched.value.whatsapp_number) {
            whatsappFieldError.value = data.errors.whatsapp_number;
        }

        return domainDnsVerifiedFor.value === (normalizeDomainInput(form.website_url) || normalizedDomain);
    } catch (error) {
        if (serial !== serverValidateSerial) {
            return false;
        }

        domainDnsVerifiedFor.value = null;

        if (error?.response?.status === 429) {
            domainFieldError.value = 'একটু পর আবার চেষ্টা করুন।';
        } else {
            domainFieldError.value = 'DNS যাচাই করা যায়নি। লাইভ ডোমেইন দিয়ে আবার চেষ্টা করুন — ছাড়া পরবর্তী ধাপে যেতে পারবেন না।';
        }

        return false;
    } finally {
        if (serial === serverValidateSerial) {
            domainChecking.value = false;
        }
    }
};

const onDomainInput = () => {
    markTouched('website_url');
    clearServerFieldError('website_url');
    domainDnsVerifiedFor.value = null;
    scheduleLocalValidation();
    scheduleServerValidation();
};

const onDomainBlur = () => {
    markTouched('website_url');
    validateDomainLocal(true);
    scheduleServerValidation();
};

const onNameInput = () => {
    markTouched('customer_name');
    clearServerFieldError('customer_name');
    scheduleLocalValidation();
};

const onNameBlur = () => {
    markTouched('customer_name');
    validateNameLocal(true);
};

const onEmailInput = () => {
    markTouched('email');
    clearServerFieldError('email');
    scheduleLocalValidation();
};

const onEmailBlur = () => {
    markTouched('email');
    const result = validateEmail(form.email);
    emailFieldError.value = result.message;
    if (result.valid && result.value) {
        form.email = result.value;
    }
    scheduleServerValidation();
};

const onMobileInput = () => {
    markTouched('contact_number');
    clearServerFieldError('contact_number');
    scheduleLocalValidation();
};

const onMobileBlur = () => {
    markTouched('contact_number');
    validateMobileLocal(true);
    scheduleServerValidation();
};

const onWhatsappInput = () => {
    markTouched('whatsapp_number');
    clearServerFieldError('whatsapp_number');
    scheduleLocalValidation();
};

const onWhatsappBlur = () => {
    markTouched('whatsapp_number');
    validateWhatsappLocal(true);
    scheduleServerValidation();
};

const onAddressInput = () => {
    markTouched('address');
    clearServerFieldError('address');
    scheduleLocalValidation();
};

const onAddressBlur = () => {
    markTouched('address');
    validateAddressLocal(true);
};

const onTransactionIdInput = () => {
    markTouched('transaction_id');
    clearServerFieldError('transaction_id');
    validateTransactionIdLocal(false);
};

const onTransactionIdBlur = () => {
    markTouched('transaction_id');
    validateTransactionIdLocal(true);
};

const onSenderNumberInput = () => {
    markTouched('account_number');
    clearServerFieldError('account_number');
    validateSenderLocal(false);
};

const onSenderNumberBlur = () => {
    markTouched('account_number');
    validateSenderLocal(true);
};

const paymentFieldsValid = computed(() => {
    const allowed = gatewayMethods.value.map((method) => method.value);

    return Boolean(
        form.transaction_method
        && allowed.includes(form.transaction_method)
        && form.transaction_id?.trim()
        && form.transaction_id.trim().length >= 4
        && isValidBdMobile(form.account_number)
        && !senderNumberFieldError.value
        && !transactionIdFieldError.value,
    );
});

const canGoNext = computed(() => {
    if (currentStep.value === 0) {
        return Boolean(props.plan);
    }

    if (currentStep.value === 1) {
        return contactFieldsValid.value;
    }

    if (currentStep.value === 2 && !isFreeTrial.value) {
        return resolvedPaymentMethods.value.length > 0;
    }

    if (currentStep.value === 3 && !isFreeTrial.value) {
        return paymentFieldsValid.value;
    }

    return true;
});

const contactValidationMessage = () => {
    ['website_url', 'customer_name', 'email', 'contact_number', 'whatsapp_number', 'address']
        .forEach((key) => { touched.value[key] = true; });

    runLocalContactValidation(true);

    if (!props.domains.length && (domainFieldError.value || !isDomainInputValid.value || !isDomainDnsVerified.value)) {
        return {
            summary: 'লাইভ ডোমেইন যাচাই প্রয়োজন',
            detail: domainFieldError.value
                || 'DNS A রেকর্ড ছাড়া পরবর্তী ধাপে যেতে পারবেন না। লাইভ WooCommerce ডোমেইন দিন।',
            missing: ['ডোমেইন নাম/ওয়েবসাইটের নাম'],
        };
    }

    if (nameFieldError.value || !form.customer_name?.trim()) {
        validateNameLocal(true);
    }

    if (emailFieldError.value || !isValidEmail(form.email)) {
        return {
            summary: 'ইমেইল যাচাই প্রয়োজন',
            detail: emailFieldError.value || 'সঠিক ইমেইল ঠিকানা লিখুন।',
            missing: ['ইমেইল'],
        };
    }

    if (mobileFieldError.value || !isValidBdMobile(form.contact_number)) {
        return {
            summary: 'মোবাইল নম্বর যাচাই প্রয়োজন',
            detail: mobileFieldError.value || 'সঠিক বাংলাদেশি মোবাইল নম্বর লিখুন।',
            missing: ['মোবাইল নম্বর'],
        };
    }

    if (whatsappFieldError.value || !isValidBdMobile(form.whatsapp_number)) {
        return {
            summary: 'WhatsApp নম্বর যাচাই প্রয়োজন',
            detail: whatsappFieldError.value || 'সঠিক বাংলাদেশি WhatsApp নম্বর লিখুন।',
            missing: ['WhatsApp নম্বর'],
        };
    }

    const missing = [];
    if (props.domains.length && !form.website_url?.trim()) {
        missing.push('ডোমেইন নাম/ওয়েবসাইটের নাম');
    }
    if (nameFieldError.value || !form.customer_name?.trim()) {
        missing.push('নাম');
    }
    if (addressFieldError.value || !form.address?.trim()) {
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
    if (!resolvedPaymentMethods.value.length) {
        return {
            summary: 'পেমেন্ট উপলব্ধ নেই',
            detail: 'পেমেন্ট নম্বর এখনো সেট করা হয়নি। WhatsApp সাপোর্টে যোগাযোগ করুন।',
            missing: ['পেমেন্ট পদ্ধতি'],
        };
    }

    touched.value.transaction_id = true;
    touched.value.account_number = true;
    validateTransactionIdLocal(true);
    validateSenderLocal(true);

    const missing = [];
    const allowed = gatewayMethods.value.map((method) => method.value);

    if (!form.transaction_method || !allowed.includes(form.transaction_method)) {
        missing.push('পেমেন্ট পদ্ধতি');
    }
    if (transactionIdFieldError.value || !form.transaction_id?.trim()) {
        missing.push('ট্রানজেকশন আইডি');
    }
    if (senderNumberFieldError.value || !isValidBdMobile(form.account_number)) {
        return {
            summary: 'পাঠানোর নম্বর যাচাই প্রয়োজন',
            detail: senderNumberFieldError.value || 'সঠিক বাংলাদেশি পাঠানোর নম্বর লিখুন।',
            missing: ['পাঠানোর নম্বর'],
        };
    }

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

    if (currentStep.value === 2 && !isFreeTrial.value) {
        if (!resolvedPaymentMethods.value.length) {
            return paymentValidationMessage();
        }

        return null;
    }

    if (currentStep.value === 3 && !isFreeTrial.value) {
        return paymentValidationMessage();
    }

    return {
        summary: 'প্রয়োজনীয় তথ্য অসম্পূর্ণ',
        detail: 'অনুগ্রহ করে চিহ্নিত ফিল্ডগুলো পূরণ করুন।',
        missing: [],
    };
};

const nextStep = async () => {
    if (currentStep.value === 1) {
        ['website_url', 'customer_name', 'email', 'contact_number', 'whatsapp_number', 'address']
            .forEach((key) => { touched.value[key] = true; });
        runLocalContactValidation(true);
        await runServerValidation();

        // Capture lead even when DNS blocks the next step.
        if (leadFieldsReady.value) {
            void saveLeadDraft({ force: true });
        }

        if (!props.domains.length && !isDomainDnsVerified.value) {
            if (!domainFieldError.value) {
                domainFieldError.value = 'DNS A রেকর্ড পাওয়া যায়নি। লাইভ ডোমেইন ছাড়া এগোতে পারবেন না।';
            }
            showValidationToast(currentValidationMessage());
            document.getElementById('subscription-domain-input')?.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                block: 'center',
            });
            return;
        }
    }

    if (!canGoNext.value) {
        showValidationToast(currentValidationMessage());
        return;
    }

    if (currentStep.value < activeSteps.value.length - 1) {
        const leavingStep = activeSteps.value[currentStep.value]?.key;
        currentStep.value += 1;

        if (leavingStep === 'contact') {
            void saveLeadDraft({ force: true });
            trackWizardStep('contact_complete', props.plan);
        }

        if (leavingStep === 'payment') {
            trackAddPaymentInfo({
                ...planContentParams(props.plan),
                payment_type: form.transaction_method || undefined,
            });
            trackWizardStep('payment_info', props.plan);
        }
    }
};

const prevStep = () => {
    if (currentStep.value > 0) {
        currentStep.value -= 1;
    }
};

const submitInquiry = async () => {
    if (!isFreeTrial.value && !resolvedPaymentMethods.value.length) {
        showWizardToast({
            severity: 'warn',
            summary: 'পেমেন্ট উপলব্ধ নেই',
            detail: 'পেমেন্ট নম্বর এখনো সেট করা হয়নি। WhatsApp সাপোর্টে যোগাযোগ করুন।',
        });
        return;
    }

    if (!props.domains.length) {
        await runServerValidation();

        if (!isDomainDnsVerified.value) {
            showValidationToast({
                summary: 'লাইভ ডোমেইন যাচাই প্রয়োজন',
                detail: domainFieldError.value
                    || 'DNS A রেকর্ড ছাড়া অনুরোধ জমা দেওয়া যাবে না।',
                missing: ['ডোমেইন নাম/ওয়েবসাইটের নাম'],
            });
            return;
        }
    }

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

onMounted(() => {
    document.addEventListener('visibilitychange', onDocumentVisibilityChange);
    window.addEventListener('pagehide', onPageHide);
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', onDocumentVisibilityChange);
    window.removeEventListener('pagehide', onPageHide);
    flushLeadSaveOnExit();
    if (localValidateTimer) {
        clearTimeout(localValidateTimer);
    }
    if (serverValidateTimer) {
        clearTimeout(serverValidateTimer);
    }
    if (leadSaveTimer) {
        clearTimeout(leadSaveTimer);
    }
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
        @update:visible="onWizardVisibleUpdate"
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
                        v-if="resolvedWhatsappSupportUrl"
                        :url="resolvedWhatsappSupportUrl"
                        :phone="resolvedWhatsappDisplayPhone"
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
                        v-if="resolvedWhatsappSupportUrl"
                        :url="resolvedWhatsappSupportUrl"
                        :phone="resolvedWhatsappDisplayPhone"
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
                                id="subscription-domain-input"
                                v-model="form.website_url"
                                type="text"
                                placeholder="যেমন: myshop.com"
                                autocomplete="url"
                                :class="fieldClass(websiteUrlError)"
                                @input="onDomainInput"
                                @blur="onDomainBlur"
                            >
                            <p
                                class="text-xs"
                                :class="{
                                    'text-amber-300': domainChecking,
                                    'text-emerald-400': !domainChecking && isDomainDnsVerified,
                                    'text-slate-500': !domainChecking && !isDomainDnsVerified && !websiteUrlError,
                                    'text-red-400': !domainChecking && !!websiteUrlError,
                                }"
                            >
                                <template v-if="domainChecking">DNS যাচাই করা হচ্ছে...</template>
                                <template v-else-if="isDomainDnsVerified">✓ লাইভ ডোমেইন যাচাই হয়েছে (DNS A রেকর্ড পাওয়া গেছে)।</template>
                                <template v-else>লাইভ WooCommerce ডোমেইন দিন — DNS A রেকর্ড ছাড়া পরবর্তী ধাপে যেতে পারবেন না।</template>
                            </p>
                            <small v-if="websiteUrlError" class="block text-red-400">{{ websiteUrlError }}</small>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2 space-y-1">
                                <label class="text-sm font-medium text-slate-300">নাম *</label>
                                <input
                                    v-model="form.customer_name"
                                    type="text"
                                    placeholder="আপনার নাম"
                                    autocomplete="name"
                                    :class="fieldClass(nameError)"
                                    @input="onNameInput"
                                    @blur="onNameBlur"
                                >
                                <small v-if="nameError" class="block text-red-400">{{ nameError }}</small>
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
                                <small v-if="emailError" class="block text-red-400">{{ emailError }}</small>
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
                                <small v-if="mobileError" class="block text-red-400">{{ mobileError }}</small>
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
                                <small v-if="whatsappError" class="block text-red-400">{{ whatsappError }}</small>
                            </div>
                            <div class="sm:col-span-2 space-y-1">
                                <label class="text-sm font-medium text-slate-300">ঠিকানা *</label>
                                <textarea
                                    v-model="form.address"
                                    rows="2"
                                    placeholder="জেলা, উপজেলা, বিস্তারিত ঠিকানা"
                                    :class="fieldClass(addressError)"
                                    @input="onAddressInput"
                                    @blur="onAddressBlur"
                                />
                                <small v-if="addressError" class="block text-red-400">{{ addressError }}</small>
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
                                    উপলব্ধ পেমেন্ট পদ্ধতিতে Send Money করুন, তারপর পরবর্তী ধাপে যান।
                                </p>
                            </div>
                        </div>
                        <SubscriptionPaymentGuideBn
                            v-if="resolvedPaymentMethods.length"
                            :methods="resolvedPaymentMethods"
                            @select="onPaymentGuideSelect"
                        />
                        <p
                            v-else
                            class="rounded-xl border border-amber-400/20 bg-amber-500/5 px-4 py-3 text-sm text-amber-100/90"
                        >
                            পেমেন্ট নম্বর এখনো সেট করা হয়নি। সাহায্যের জন্য WhatsApp সাপোর্টে যোগাযোগ করুন।
                        </p>
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
                                <input
                                    v-model="form.transaction_id"
                                    type="text"
                                    placeholder="যেমন: 8N7A2XX"
                                    :class="fieldClass(transactionIdError)"
                                    @input="onTransactionIdInput"
                                    @blur="onTransactionIdBlur"
                                >
                                <small v-if="transactionIdError" class="block text-red-400">{{ transactionIdError }}</small>
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
                                <small v-if="senderNumberError" class="block text-red-400">{{ senderNumberError }}</small>
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
                    class="w-full rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto sm:py-2.5"
                    :disabled="domainChecking || (currentStep === 1 && !canGoNext)"
                    @click="nextStep"
                >
                    {{ domainChecking && currentStep === 1 ? 'DNS যাচাই হচ্ছে...' : 'পরবর্তী ধাপ' }}
                </button>
                <button
                    v-else
                    type="button"
                    class="w-full rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-black disabled:opacity-50 sm:w-auto sm:py-2.5"
                    :disabled="form.processing || domainChecking || (!domains.length && !isDomainDnsVerified)"
                    @click="submitInquiry"
                >
                    {{ form.processing ? 'জমা হচ্ছে...' : (isFreeTrial ? 'অনুরোধ জমা দিন' : 'পেমেন্ট তথ্য জমা দিন') }}
                </button>
            </div>
        </div>
    </Dialog>
</template>
