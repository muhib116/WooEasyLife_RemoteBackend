<template>
    <UserLayout
        title="Websites"
        section="Websites"
        subtitle="Manage store domains, subscription plans, and plugin license keys"
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
            description="Add a store domain and assign a subscription plan to get started."
            icon="PhGlobe"
        >
            <Button
                label="Add Website"
                icon="pi pi-plus"
                size="small"
                @click="openAssignPlan()"
            />
        </EmptyState>

        <div v-else class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <WebsiteCard
                v-for="website in websites"
                :key="website.domain"
                :website="website"
                :is-revealing="isRevealing"
                :primary-action="primaryAction(website)"
                @menu="toggleMenu($event, website)"
                @renew-plan="confirmRenewPlan(website)"
                @renew-via-billing="goToBilling"
                @change-plan="openChangePlan(website)"
                @adjust-subscription="openAdjustPlan(website)"
                @copy-license="revealAndCopy"
                @edit-license="(license) => handleEditLicense(license, website.domain)"
                @delete-license="handleDeleteLicense"
            />
        </div>

        <Menu ref="actionMenu" :model="menuItems" :popup="true" />

        <Dialog
            v-model:visible="showPlanForm"
            modal
            :style="{ width: 'min(100vw - 2rem, 42rem)' }"
            :breakpoints="{ '960px': '92vw' }"
            draggable
            dismissable-mask
            @hide="planForm.reset()"
        >
            <template #header>
                <div class="pr-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ planDialogTitle }}
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ planDialogSubtitle }}
                    </p>
                </div>
            </template>
            <PackageForm
                :form="planForm"
                :packages="packages"
                :user-id="user.id"
                :mode="planFormMode"
                :hide-domain="planFormMode === 'change'"
                :current-plan="planCurrentPackage"
                @on-close="showPlanForm = false"
                @handle-save="handleSavePlan"
            />
        </Dialog>

        <Dialog
            v-model:visible="showLicenseForm"
            modal
            :style="{ width: 'min(100vw - 2rem, 42rem)' }"
            :breakpoints="{ '960px': '92vw' }"
            draggable
            dismissable-mask
            @hide="onLicenseFormHide"
        >
            <template #header>
                <div class="pr-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ licenseDialogTitle }}
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ licenseDialogSubtitle }}
                    </p>
                </div>
            </template>
            <TokenForm
                :token-form="tokenForm"
                :user_packages="filteredUserPackages"
                :hide-website-select="Boolean(licenseDomain)"
                :locked-domain="licenseDomain"
                @on-close="showLicenseForm = false"
                @handle-save="handleSaveLicense"
            />
        </Dialog>

        <Toast />
        <ConfirmDialog id="confirm" />
    </UserLayout>
</template>

<script setup lang="ts">
import UserLayout from "../UserLayout.vue";
import EmptyState from "../fragments/EmptyState.vue";
import PackageForm from "../fragments/PackageForm.vue";
import TokenForm from "../fragments/TokenForm.vue";
import WebsiteCard from "../fragments/WebsiteCard.vue";
import WebsiteHealthLegend from "@/components/WebsiteHealthLegend.vue";
import { router, useForm } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { useConfirm } from "primevue";
import { useToast } from "primevue/usetoast";
import { parseISO } from "date-fns";
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
const planFormMode = ref<"add" | "assign" | "adjust" | "change">("assign");
const planAdjustPackageHubId = ref<number | null>(null);
const planChangeFromHubId = ref<number | null>(null);
const showLicenseForm = ref(false);
const licenseDomain = ref<string | null>(null);
const actionMenu = ref();
const menuItems = ref<any[]>([]);

const planForm = useForm({
    id: null,
    user_package_id: null as number | null,
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
    plan_type: "legacy" as string,
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

const planDialogTitle = computed(() => {
    if (planFormMode.value === "adjust") {
        return "Adjust Subscription";
    }

    if (planFormMode.value === "change") {
        return "Change Plan";
    }

    return planFormMode.value === "add" ? "Add Website" : "Assign Plan";
});

const planDialogSubtitle = computed(() => {
    if (planFormMode.value === "adjust") {
        return "Override quota, expiry, or active status. Use Renew or Change plan for plan switches.";
    }

    if (planFormMode.value === "change") {
        return planForm.domain
            ? `Replace the current plan for ${planForm.domain}.`
            : "Replace the current subscription with a different plan.";
    }

    if (planFormMode.value === "add") {
        return "Enter the store domain and pick a subscription plan to onboard a new website.";
    }

    if (planForm.domain) {
        return `Choose a plan for ${planForm.domain}. Generate a license key after saving if needed.`;
    }

    return "Choose a subscription plan for this website.";
});

const planCurrentPackage = computed(() => {
    const hubId =
        planFormMode.value === "change"
            ? planChangeFromHubId.value
            : planAdjustPackageHubId.value;

    if (!hubId) {
        return null;
    }

    return props.packages.find((item) => item.id === hubId) ?? null;
});

const licenseDialogTitle = computed(() =>
    tokenForm.id ? "Edit License" : "Generate License",
);

const licenseDialogSubtitle = computed(() => {
    if (tokenForm.id) {
        return "Update license metadata or access settings for this website.";
    }

    if (licenseDomain.value) {
        return `Create a plugin license key for ${licenseDomain.value}. Subscription expiry controls access — custom key expiry is optional.`;
    }

    return "Link a license key to a website plan for WooCommerce plugin access.";
});

const onLicenseFormHide = () => {
    tokenForm.reset();
    licenseDomain.value = null;
};

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

const primaryAction = (website: any) => {
    if (!website.subscription) {
        return {
            label: "Assign plan",
            icon: "pi pi-plus",
            run: () => openAssignPlan(website.domain),
        };
    }

    const hasLicense = (website.licenses?.length ?? 0) > 0;

    return {
        label: hasLicense ? "Add license key" : "Generate license",
        icon: "pi pi-key",
        run: () => openGenerateLicense(website.domain),
    };
};

const buildMenuItems = (website: any) => {
    const items: any[] = [];

    if (website.subscription) {
        items.push({
            label: "Billing & payments",
            icon: "pi pi-credit-card",
            command: () => router.visit(route("users.billing", props.user.id)),
        });
    } else {
        items.push({
            label: "Assign plan",
            icon: "pi pi-plus",
            command: () => openAssignPlan(website.domain),
        });
    }

    items.push({ separator: true });
    items.push({
        label: "Delete website",
        icon: "pi pi-trash",
        class: "website-menu-danger",
        command: () => confirmDeleteWebsite(website),
    });

    return items;
};

const toggleMenu = (event: Event, website: any) => {
    menuItems.value = buildMenuItems(website);
    actionMenu.value.toggle(event);
};

const openAssignPlan = (domain?: string) => {
    planForm.reset();
    planForm.is_active = true;
    planForm.domain = domain ?? null;
    planAdjustPackageHubId.value = null;
    planChangeFromHubId.value = null;
    planFormMode.value = domain ? "assign" : "add";
    showPlanForm.value = true;
};

const openAdjustPlan = (website: any) => {
    const subscription = website.subscription;
    if (!subscription) {
        openAssignPlan(website.domain);
        return;
    }

    planFormMode.value = "adjust";
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
    planForm.plan_type = source?.plan_type ?? subscription.plan_type ?? "legacy";
    planAdjustPackageHubId.value =
        subscription.package_hub_id ?? source?.package_hub_id ?? null;
    planForm.expires_at = subscription.expires_at
        ? parseISO(subscription.expires_at)
        : null;
    showPlanForm.value = true;
};

const openChangePlan = (website: any) => {
    const subscription = website.subscription;
    if (!subscription) {
        openAssignPlan(website.domain);
        return;
    }

    planFormMode.value = "change";
    planAdjustPackageHubId.value = null;
    planChangeFromHubId.value = subscription.package_hub_id ?? null;
    planForm.reset();
    planForm.user_package_id = subscription.id;
    planForm.domain = website.domain;
    planForm.package_id = null;
    planForm.transaction_method = "Cash";
    planForm.is_active = true;
    planForm.limit = subscription.total_order_can_handle ?? 300;
    planForm.total_order_can_handle = subscription.total_order_can_handle ?? 300;
    showPlanForm.value = true;
};

const confirmRenewPlan = (website: any) => {
    const subscription = website.subscription;
    if (!subscription) {
        return;
    }

    confirm.require({
        message: `Renew ${website.domain}? This resets order tokens and extends expiry for a new plan period.`,
        header: "Renew Plan",
        icon: "pi pi-refresh",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Renew plan",
            size: "small",
        },
        accept: () => {
            router.post(route("users.renewSubscription", props.user.id), {
                user_package_id: subscription.id,
            });
        },
    });
};

const goToBilling = () => {
    router.visit(route("users.billing", props.user.id));
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
    const closeOnSuccess = () => {
        showPlanForm.value = false;
        planForm.reset();
        planAdjustPackageHubId.value = null;
        planChangeFromHubId.value = null;
    };

    if (planFormMode.value === "adjust" && planForm.id) {
        planForm.post(route("users.updatePurchasePackage", props.user.id), {
            onSuccess: closeOnSuccess,
        });
        return;
    }

    if (planFormMode.value === "change") {
        planForm.post(route("users.changeSubscription", props.user.id), {
            onSuccess: closeOnSuccess,
        });
        return;
    }

    planForm
        .transform((data) => ({
            ...data,
            require_new_website: planFormMode.value === "add",
        }))
        .post(route("users.purchasePackage", props.user.id), {
            onSuccess: closeOnSuccess,
        });
};

const handleEditLicense = (license: any, domain: string) => {
    licenseDomain.value = domain;
    tokenForm.reset();
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
                onLicenseFormHide();
            },
        });
    } else {
        tokenForm.tokenable_id = props.user.id;
        tokenForm.post(route("apiKeys.create"), {
            onSuccess() {
                showLicenseForm.value = false;
                onLicenseFormHide();
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

const confirmDeleteWebsite = (website: any) => {
    const licenseCount = website.licenses?.length ?? 0;
    const hasPlan = Boolean(website.subscription);

    let detail =
        "This permanently removes the website entry for this domain.";
    if (hasPlan || licenseCount > 0) {
        const parts: string[] = [];
        if (hasPlan) {
            parts.push("its subscription plan");
        }
        if (licenseCount > 0) {
            parts.push(
                `${licenseCount} license key${licenseCount === 1 ? "" : "s"}`,
            );
        }
        detail += ` It will also remove ${parts.join(" and ")}.`;
    }
    detail += " Pending payment requests for this domain will be cancelled.";

    confirm.require({
        message: `Delete ${website.domain}? ${detail}`,
        header: "Delete Website",
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
            size: "small",
        },
        acceptProps: {
            label: "Delete Website",
            severity: "danger",
            size: "small",
        },
        accept: () => {
            router.post(route("users.websites.delete", props.user.id), {
                domain: website.domain,
            });
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

<style scoped>
:deep(.website-menu-danger .p-menu-item-link),
:deep(.website-menu-danger .p-menuitem-link) {
    color: rgb(220 38 38);
}

:deep(.website-menu-danger .p-menu-item-link:hover),
:deep(.website-menu-danger .p-menuitem-link:hover) {
    color: rgb(185 28 28);
    background: rgb(254 242 242);
}
</style>
