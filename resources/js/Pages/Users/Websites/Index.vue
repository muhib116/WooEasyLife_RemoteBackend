<template>
    <UserLayout
        title="Websites"
        section="Websites"
        subtitle="Manage plans and license keys per website domain"
        :user="user"
    >
        <template #actions>
            <Button
                label="Add Website"
                icon="pi pi-plus"
                size="small"
                @click="openAssignPlan()"
            />
        </template>

        <WebsiteHealthLegend class="mb-2" />

        <EmptyState
            v-if="!websites.length"
            title="No websites yet"
            description="Assign a subscription plan with a domain to create the first website entry."
            icon="PhGlobe"
        >
            <Button
                label="Assign First Plan"
                icon="pi pi-plus"
                size="small"
                @click="openAssignPlan()"
            />
        </EmptyState>

        <div v-else class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <PageCard
                v-for="website in websites"
                :key="website.domain"
                no-padding
            >
                <div class="border-b border-gray-100 p-5 dark:border-gray-700/80">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <Icon
                                    name="PhGlobe"
                                    class="text-primary-500"
                                />
                                <h3
                                    class="truncate font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ website.domain }}
                                </h3>
                                <StatusBadge
                                    :label="healthLabel(website.health.status)"
                                    :variant="healthVariant(website.health.status)"
                                    format="none"
                                />
                            </div>
                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ website.display_url }}
                            </p>
                        </div>
                    </div>

                    <ul
                        v-if="website.health.issues?.length"
                        class="mt-3 space-y-1 text-xs"
                        :class="
                            website.health.status === 'connected'
                                ? 'text-amber-700 dark:text-amber-300'
                                : 'text-amber-700 dark:text-amber-300'
                        "
                    >
                        <li
                            v-for="issue in website.health.issues"
                            :key="issue"
                            class="flex items-start gap-2"
                        >
                            <Icon name="PhWarning" class="mt-0.5 shrink-0" />
                            <span>{{ issue }}</span>
                        </li>
                    </ul>
                </div>

                <div class="space-y-4 p-5">
                    <div
                        class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/40"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            Subscription
                        </p>
                        <template v-if="website.subscription">
                            <p
                                class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ website.subscription.title }}
                            </p>
                            <div
                                class="mt-2 grid grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-300"
                            >
                                <span>
                                    Remaining:
                                    <strong>{{
                                        website.subscription.remaining_order
                                    }}</strong>
                                </span>
                                <span>
                                    Quota:
                                    <strong>{{
                                        website.subscription.total_order_can_handle
                                    }}</strong>
                                </span>
                                <span>
                                    Used:
                                    <strong>{{
                                        website.subscription.total_order_handled
                                    }}</strong>
                                </span>
                                <span>
                                    Cost:
                                    <strong
                                        >{{ website.subscription.total_cost }}
                                        TK</strong
                                    >
                                </span>
                                <span v-if="website.subscription.expires_at">
                                    Expires:
                                    <strong>{{
                                        formatDate(website.subscription.expires_at)
                                    }}</strong>
                                </span>
                            </div>
                        </template>
                        <p
                            v-else
                            class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                        >
                            No plan assigned for this domain.
                        </p>
                    </div>

                    <div
                        class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/40"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            License Keys
                        </p>
                        <div
                            v-if="website.licenses?.length"
                            class="mt-2 space-y-2"
                        >
                            <div
                                v-for="license in website.licenses"
                                :key="license.id"
                                class="flex flex-col gap-2 rounded-lg border border-gray-100 bg-white p-3 dark:border-gray-700 dark:bg-slate-800"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span
                                        class="text-sm font-medium text-gray-800 dark:text-gray-100"
                                    >
                                        {{ license.title || "License" }}
                                    </span>
                                    <StatusBadge
                                        :label="
                                            license.status
                                                ? 'Enabled'
                                                : 'Disabled'
                                        "
                                        :variant="
                                            license.status
                                                ? 'success'
                                                : 'neutral'
                                        "
                                        format="none"
                                    />
                                </div>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Last used:
                                    {{
                                        license.last_used_ago || "Never"
                                    }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-if="license.has_token"
                                        label="Copy Key"
                                        icon="pi pi-copy"
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        :loading="isRevealing(license.id)"
                                        @click="revealAndCopy(license.id)"
                                    />
                                    <Button
                                        label="Edit"
                                        icon="pi pi-pencil"
                                        size="small"
                                        severity="secondary"
                                        text
                                        @click="handleEditLicense(license, website.domain)"
                                    />
                                    <Button
                                        label="Delete"
                                        icon="pi pi-trash"
                                        size="small"
                                        severity="danger"
                                        text
                                        @click="handleDeleteLicense(license)"
                                    />
                                </div>
                            </div>
                        </div>
                        <p
                            v-else
                            class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                        >
                            No license key for this domain.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-if="website.subscription"
                            :href="route('users.billing', user.id)"
                        >
                            <Button
                                label="Manage Billing"
                                icon="pi pi-credit-card"
                                size="small"
                                severity="warning"
                                outlined
                                as="span"
                            />
                        </Link>
                        <Button
                            v-if="website.subscription"
                            label="Edit Plan"
                            icon="pi pi-pencil"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="openEditPlan(website)"
                        />
                        <Button
                            :label="website.subscription ? 'Add Plan' : 'Assign Plan'"
                            icon="pi pi-plus"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="openAssignPlan(website.domain)"
                        />
                        <Button
                            label="Generate License"
                            icon="pi pi-key"
                            size="small"
                            @click="openGenerateLicense(website.domain)"
                        />
                        <Link
                            :href="
                                route('users.packagesOrders', {
                                    user_id: user.id,
                                    domain: website.domain,
                                })
                            "
                        >
                            <Button
                                label="Order History"
                                icon="pi pi-chart-line"
                                size="small"
                                severity="secondary"
                                text
                                as="span"
                            />
                        </Link>
                        <Button
                            v-if="website.subscription"
                            label="Usage Breakdown"
                            icon="pi pi-list"
                            size="small"
                            severity="secondary"
                            text
                            @click="showUseDetails = website.subscription.id"
                        />
                    </div>
                </div>
            </PageCard>
        </div>

        <Dialog
            v-model:visible="showPlanForm"
            :header="planForm.id ? 'Edit Plan' : 'Assign Plan'"
            modal
            :style="{ width: '35rem' }"
            draggable
            dismissable-mask
            @hide="planForm.reset()"
        >
            <PackageForm
                :form="planForm"
                :packages="packages"
                @on-close="showPlanForm = false"
                @handle-save="handleSavePlan"
            />
        </Dialog>

        <Dialog
            v-model:visible="showLicenseForm"
            :header="tokenForm.id ? 'Edit License' : 'Generate License'"
            modal
            :style="{ width: '35rem' }"
            draggable
            dismissable-mask
            @hide="tokenForm.reset()"
        >
            <TokenForm
                :token-form="tokenForm"
                :user_packages="filteredUserPackages"
                show-summary
                @on-close="showLicenseForm = false"
                @handle-save="handleSaveLicense"
            />
        </Dialog>

        <Dialog
            v-model:visible="showUseDetails"
            header="Package Use Details"
            modal
            maximizable
            :style="{ width: '90%' }"
            draggable
            dismissable-mask
        >
            <UseDetails
                v-if="showUseDetails"
                :user="user"
                :id="showUseDetails"
            />
        </Dialog>

        <Toast />
        <ConfirmDialog id="confirm" />
    </UserLayout>
</template>

<script setup lang="ts">
import UserLayout from "../UserLayout.vue";
import PageCard from "../fragments/PageCard.vue";
import StatusBadge from "../fragments/StatusBadge.vue";
import EmptyState from "../fragments/EmptyState.vue";
import PackageForm from "../fragments/PackageForm.vue";
import TokenForm from "../fragments/TokenForm.vue";
import UseDetails from "../fragments/UseDetails.vue";
import WebsiteHealthLegend from "@/components/WebsiteHealthLegend.vue";
import { Icon } from "@/plugins";
import { Link, router, useForm } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { useConfirm } from "primevue";
import { useToast } from "primevue/usetoast";
import { format, parseISO } from "date-fns";
import { useLicenseTokenReveal } from "@/composables/useLicenseTokenReveal";

defineOptions({
    name: "WebsitesIndex",
});

const props = defineProps<{
    user: any;
    websites: any[];
    packages: any[];
    user_packages: any[];
    action?: string | null;
    domain?: string | null;
}>();

const { revealAndCopy, isRevealing } = useLicenseTokenReveal();
const confirm = useConfirm();
const toast = useToast();

const showPlanForm = ref(false);
const showLicenseForm = ref(false);
const showUseDetails = ref<number | false>(false);
const licenseDomain = ref<string | null>(null);

const planForm = useForm({
    id: null,
    package_id: null,
    transaction_number: null,
    transaction_id: null,
    transaction_method: "Cash",
    transaction_charge: 0,
    domain: null as string | null,
    note: null,
    limit: 300,
    remaining_order: null as number | null,
    total_order_can_handle: null as number | null,
    expires_at: null as Date | null,
    is_active: true,
});

const tokenForm = useForm({
    id: null,
    title: null,
    package: null,
    tokenable_id: null,
    user_package_id: null,
    expires_at: null as Date | null,
    abilities: null,
    description: null,
    domain: null as string | null,
    status: true,
    referred_by: null,
});

const filteredUserPackages = computed(() => {
    if (!licenseDomain.value) {
        return props.user_packages;
    }

    return props.user_packages.filter(
        (item) =>
            normalizeDomain(item.domain) === normalizeDomain(licenseDomain.value),
    );
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

const healthLabel = (status: string) => {
    if (status === "connected") return "Connected";
    if (status === "configured") return "Configured";
    if (status === "ready") return "Connected";
    if (status === "disabled") return "Disabled";
    return "Incomplete";
};

const healthVariant = (status: string) => {
    if (status === "connected" || status === "ready") return "success";
    if (status === "configured") return "info";
    if (status === "disabled") return "danger";
    return "warning";
};

const formatDate = (value?: string | null) => {
    if (!value) {
        return "—";
    }

    try {
        return format(parseISO(value), "yyyy-MM-dd");
    } catch {
        return value;
    }
};

const openAssignPlan = (domain?: string) => {
    planForm.reset();
    planForm.is_active = true;
    planForm.domain = domain ?? null;
    showPlanForm.value = true;
};

const openEditPlan = (website: any) => {
    const subscription = website.subscription;
    if (!subscription) {
        openAssignPlan(website.domain);
        return;
    }

    const source = props.user_packages.find(
        (item) => item.id === subscription.id,
    );

    planForm.reset();
    planForm.id = subscription.id;
    planForm.domain = website.domain;
    planForm.note = source?.note ?? null;
    planForm.remaining_order = subscription.remaining_order;
    planForm.total_order_can_handle = subscription.total_order_can_handle;
    planForm.is_active = Boolean(subscription.is_active);
    planForm.expires_at = subscription.expires_at
        ? parseISO(subscription.expires_at)
        : null;
    showPlanForm.value = true;
};

const openGenerateLicense = (domain: string) => {
    licenseDomain.value = domain;
    tokenForm.reset();
    tokenForm.status = true;
    tokenForm.tokenable_id = props.user.id;

    const matchingPackage = props.user_packages.find(
        (item) => normalizeDomain(item.domain) === normalizeDomain(domain),
    );

    if (matchingPackage) {
        tokenForm.user_package_id = matchingPackage.id;
        tokenForm.domain = matchingPackage.domain;
    } else {
        toast.add({
            severity: "warn",
            summary: "Plan required",
            detail: "Assign a subscription plan for this domain before generating a license.",
            life: 4000,
        });
        openAssignPlan(domain);
        return;
    }

    showLicenseForm.value = true;
};

const handleSavePlan = () => {
    if (planForm.id) {
        planForm.post(route("users.updatePurchasePackage", props.user.id), {
            onFinish() {
                if (!planForm.hasErrors) {
                    showPlanForm.value = false;
                    planForm.reset();
                }
            },
        });
        return;
    }

    planForm.post(route("users.purchasePackage", props.user.id), {
        onFinish() {
            if (!planForm.hasErrors) {
                showPlanForm.value = false;
                planForm.reset();
            }
        },
    });
};

const handleEditLicense = (license: any, domain: string) => {
    licenseDomain.value = domain;
    tokenForm.id = license.id;
    tokenForm.title = license.title;
    tokenForm.expires_at = license.expires_at
        ? parseISO(license.expires_at)
        : null;
    tokenForm.tokenable_id = props.user.id;
    tokenForm.domain = domain;
    tokenForm.status = Boolean(license.status);

    const matchingPackage = props.user_packages.find(
        (item) => normalizeDomain(item.domain) === normalizeDomain(domain),
    );

    if (matchingPackage) {
        tokenForm.user_package_id = matchingPackage.id;
    }

    showLicenseForm.value = true;
};

const handleSaveLicense = () => {
    if (!tokenForm.user_package_id) {
        toast.add({
            severity: "error",
            summary: "Website required",
            detail: "Select a website domain before saving the license.",
            life: 3000,
        });
        return;
    }

    const selectedPackage = props.user_packages.find(
        (item) => item.id == tokenForm.user_package_id,
    );

    if (selectedPackage?.domain) {
        tokenForm.domain = selectedPackage.domain;
    }

    if (tokenForm.id) {
        tokenForm.post(route("apiKeys.update", tokenForm.id), {
            onSuccess() {
                showLicenseForm.value = false;
                tokenForm.reset();
            },
        });
    } else {
        tokenForm.tokenable_id = props.user.id;
        tokenForm.post(route("apiKeys.create"), {
            onSuccess() {
                showLicenseForm.value = false;
                tokenForm.reset();
            },
        });
    }
};

const handleDeleteLicense = (license: any) => {
    confirm.require({
        message: "Are you sure you want to delete this license key?",
        header: "Delete License",
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Delete",
            severity: "danger",
            size: "small",
        },
        accept: () => {
            router.post(route("apiKeys.delete", license.id));
        },
    });
};

onMounted(() => {
    if (!props.action || !props.domain) {
        return;
    }

    if (props.action === "assign") {
        openAssignPlan(props.domain);
    }

    if (props.action === "license") {
        openGenerateLicense(props.domain);
    }
});
</script>
