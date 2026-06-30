<template>
    <UserLayout
        title="Setup Wizard"
        section="Setup Wizard"
        subtitle="Onboard a merchant website with plan and license in one flow"
        :user="user"
    >
        <div class="space-y-5">
            <div
                v-if="setup?.needs_wizard && !setup?.ready_for_plugin"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
            >
                Complete all steps below so this merchant can connect the
                WooCommerce plugin.
            </div>

            <PageCard title="Setup steps">
                <div class="mb-6 flex flex-wrap gap-2">
                    <span
                        v-for="(item, index) in stepLabels"
                        :key="item.key"
                        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold"
                        :class="
                            currentStep === item.key
                                ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300'
                                : isStepComplete(item.key)
                                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                  : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                        "
                    >
                        <span>{{ index + 1 }}.</span>
                        {{ item.label }}
                    </span>
                </div>

                <div v-if="currentStep === 'website'" class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Enter the WooCommerce store domain. It must resolve to a
                        DNS A record (same hostname the plugin will send in
                        Origin headers).
                    </p>
                    <DomainFieldHint />
                    <div class="max-w-xl space-y-3">
                        <label class="block text-sm font-medium">Website domain</label>
                        <InputText
                            v-model="domainInput"
                            placeholder="shop.example.com"
                            class="w-full"
                            :invalid="Boolean(domainError)"
                        />
                        <DomainValidationAlert
                            v-if="validatingDomain"
                            status="loading"
                            title="Validating domain…"
                            message="Checking format, DNS, and whether this store is available on WooEasyLife."
                        />
                        <DomainValidationAlert
                            v-else-if="domainError"
                            status="error"
                            :title="domainValidationErrorTitle(domainError)"
                            :message="domainError"
                        />
                        <DomainValidationAlert
                            v-else-if="validatedDomain"
                            status="success"
                            title="Domain verified"
                            :message="`${validatedDomain} is ready to use.`"
                            hint="Click Continue to assign a subscription plan."
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Link :href="route('users.view', user.id)">
                            <Button
                                label="Cancel"
                                severity="secondary"
                                outlined
                                as="span"
                            />
                        </Link>
                        <Button
                            label="Continue"
                            icon="pi pi-arrow-right"
                            :loading="validatingDomain"
                            @click="validateDomain"
                        />
                    </div>
                </div>

                <div v-else-if="currentStep === 'plan'" class="space-y-4">
                    <PackageForm
                        :form="planForm"
                        :packages="packages"
                        mode="assign"
                        simplified
                        hide-domain
                        @on-close="goToOverview"
                        @handle-save="handleSavePlan"
                    />
                </div>

                <div v-else-if="currentStep === 'license'" class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Generate the plugin license key for
                        <strong>{{ activeDomain }}</strong>
                    </p>
                    <div
                        v-if="!matchingPackage"
                        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        No active plan found for this domain. Go back and assign
                        a plan first.
                    </div>
                    <template v-else>
                        <div
                            class="rounded-xl border border-gray-100 bg-slate-50 p-4 text-sm dark:border-gray-700 dark:bg-slate-900/40"
                        >
                            <p class="font-medium">{{ matchingPackage.title }}</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-300">
                                Remaining orders:
                                {{ matchingPackage.remaining_order }} /
                                {{ matchingPackage.total_order_can_handle }}
                            </p>
                        </div>
                        <form @submit.prevent="handleGenerateLicense">
                            <div class="mb-4 flex flex-col gap-1">
                                <label class="font-semibold">License title</label>
                                <InputText
                                    v-model="licenseForm.title"
                                    placeholder="e.g. Main store license (optional)"
                                    class="w-full"
                                />
                            </div>
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    label="Back"
                                    severity="secondary"
                                    outlined
                                    @click="currentStep = 'plan'"
                                />
                                <Button
                                    type="submit"
                                    label="Generate License"
                                    icon="pi pi-key"
                                    :loading="licenseForm.processing"
                                />
                            </div>
                        </form>
                    </template>
                </div>

                <div v-else-if="currentStep === 'complete'" class="space-y-5">
                    <div
                        class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100"
                    >
                        <Icon name="PhCheckCircle" class="mt-0.5 text-xl" />
                        <div>
                            <p class="font-semibold">Setup complete</p>
                            <p class="mt-1">
                                This merchant is ready to connect the plugin.
                                Copy the license key below into the WooCommerce
                                plugin settings.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="displayToken"
                        class="rounded-xl border border-gray-100 bg-white p-4 dark:border-gray-700 dark:bg-slate-800"
                    >
                        <p class="text-xs font-semibold uppercase text-gray-500">
                            License key
                        </p>
                        <p
                            class="mt-2 break-all rounded-lg bg-slate-100 p-3 font-mono text-sm dark:bg-slate-900"
                        >
                            {{ displayToken }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button
                                label="Copy Key"
                                icon="pi pi-copy"
                                size="small"
                                @click="copyToken"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-900/40">
                        <p class="font-semibold">Plugin instructions</p>
                        <ol class="mt-2 list-decimal space-y-1 pl-5 text-gray-600 dark:text-gray-300">
                            <li>Install WooEasyLife on the WordPress site.</li>
                            <li>Open plugin settings and paste the license key.</li>
                            <li>
                                Ensure the site URL hostname matches
                                <strong>{{ activeDomain }}</strong>.
                            </li>
                            <li>Save settings — the plugin will call the API automatically.</li>
                        </ol>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('users.view', user.id)">
                            <Button label="Back to Overview" as="span" />
                        </Link>
                        <Link :href="route('users.websites', user.id)">
                            <Button
                                label="Manage Websites"
                                severity="secondary"
                                outlined
                                as="span"
                            />
                        </Link>
                    </div>
                </div>
            </PageCard>
        </div>

        <Toast />
    </UserLayout>
</template>

<script setup lang="ts">
import UserLayout from "../UserLayout.vue";
import PageCard from "../fragments/PageCard.vue";
import PackageForm from "../fragments/PackageForm.vue";
import DomainFieldHint from "@/components/DomainFieldHint.vue";
import DomainValidationAlert from "@/components/DomainValidationAlert.vue";
import { Icon } from "@/plugins";
import { extractDomainValidationError, domainValidationErrorTitle } from "@/utils/domainValidationMessages";
import { Link, router, useForm } from "@inertiajs/vue3";
import axios from "axios";
import { computed, onMounted, ref } from "vue";
import { useClipboard } from "@vueuse/core";
import { useToast } from "primevue/usetoast";

defineOptions({
    name: "SetupWizard",
});

const props = defineProps<{
    user: any;
    setup: any;
    packages: any[];
    user_packages: any[];
    default_package_id?: number | null;
    license_token?: string | null;
    step?: string | null;
    domain?: string | null;
}>();

const { copy } = useClipboard();
const toast = useToast();

const stepLabels = [
    { key: "website", label: "Website" },
    { key: "plan", label: "Assign Plan" },
    { key: "license", label: "License" },
    { key: "complete", label: "Done" },
];

const currentStep = ref<string>("website");
const domainInput = ref("");
const validatedDomain = ref<string | null>(null);
const domainError = ref<string | null>(null);
const validatingDomain = ref(false);
const localToken = ref<string | null>(null);

const activeDomain = computed(
    () => validatedDomain.value || props.domain || domainInput.value,
);

const displayToken = computed(
    () => localToken.value || props.license_token || null,
);

const matchingPackage = computed(() => {
    if (!activeDomain.value) {
        return null;
    }

    return props.user_packages.find(
        (item) =>
            normalizeDomain(item.domain) === normalizeDomain(activeDomain.value),
    );
});

const planForm = useForm({
    id: null,
    package_id: props.default_package_id ?? null,
    transaction_number: null,
    transaction_id: null,
    transaction_method: "Cash",
    transaction_charge: 0,
    domain: props.domain ?? null,
    note: null,
    limit: 300,
    redirect_to_setup: true,
});

const licenseForm = useForm({
    domain: "",
    user_package_id: null as number | null,
    title: null as string | null,
    status: true,
});

const normalizeDomain = (value?: string | null) => {
    if (!value) {
        return "";
    }

    try {
        const url = value.includes("://") ? value : `https://${value}`;
        return new URL(url).hostname.toLowerCase();
    } catch {
        return value.toLowerCase();
    }
};

const isStepComplete = (key: string) => {
    const order = ["website", "plan", "license", "complete"];
    const currentIndex = order.indexOf(currentStep.value);
    const stepIndex = order.indexOf(key);

    return stepIndex < currentIndex;
};

const resolveInitialStep = () => {
    if (props.step === "complete" || props.license_token) {
        currentStep.value = "complete";
        return;
    }

    if (props.step === "license" && props.domain) {
        validatedDomain.value = props.domain;
        domainInput.value = props.domain;
        currentStep.value = "license";
        return;
    }

    if (props.setup?.ready_for_plugin) {
        currentStep.value = "complete";
        return;
    }

    if (props.domain) {
        validatedDomain.value = props.domain;
        domainInput.value = props.domain;
        currentStep.value = props.setup?.steps?.find((s: any) => s.key === "plan" && !s.complete)
            ? "plan"
            : "website";
    }
};

const validateDomain = async () => {
    domainError.value = null;
    validatedDomain.value = null;
    validatingDomain.value = true;

    try {
        const { data } = await axios.post(
            route("users.setup.validateDomain", props.user.id),
            { domain: domainInput.value },
        );

        validatedDomain.value = data.domain;
        planForm.domain = data.domain;
        currentStep.value = "plan";
    } catch (error: unknown) {
        domainError.value = extractDomainValidationError(error);
    } finally {
        validatingDomain.value = false;
    }
};

const handleSavePlan = () => {
    planForm.domain = activeDomain.value;
    planForm.redirect_to_setup = true;

    planForm.post(route("users.purchasePackage", props.user.id), {
        preserveScroll: true,
    });
};

const handleGenerateLicense = () => {
    if (!matchingPackage.value) {
        return;
    }

    licenseForm.domain = activeDomain.value;
    licenseForm.user_package_id = matchingPackage.value.id;

    licenseForm.post(route("users.setup.generateLicense", props.user.id), {
        preserveScroll: true,
    });
};

const copyToken = () => {
    if (!displayToken.value) {
        return;
    }

    copy(displayToken.value);
    toast.add({
        severity: "success",
        summary: "Copied",
        detail: "License key copied to clipboard",
        life: 3000,
    });
};

const goToOverview = () => {
    router.visit(route("users.view", props.user.id));
};

onMounted(() => {
    if (props.domain) {
        domainInput.value = props.domain;
    }

    if (props.license_token) {
        localToken.value = props.license_token;
    }

    resolveInitialStep();
});
</script>
