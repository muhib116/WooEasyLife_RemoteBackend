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
                <div
                    class="flex items-start justify-between gap-3 border-b border-gray-100 p-5 dark:border-gray-700/80"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <Icon name="PhGlobe" class="text-primary-500" />
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
                        <a
                            :href="website.display_url"
                            target="_blank"
                            rel="noopener"
                            class="mt-1 inline-flex items-center gap-1 text-xs text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400"
                        >
                            {{ website.display_url }}
                            <Icon
                                name="PhArrowSquareOut"
                                class="text-[0.7rem]"
                            />
                        </a>
                    </div>
                    <Button
                        icon="pi pi-ellipsis-v"
                        size="small"
                        severity="secondary"
                        text
                        rounded
                        aria-label="More actions"
                        @click="toggleMenu($event, website)"
                    />
                </div>

                <div class="space-y-5 p-5">
                    <div
                        v-if="website.health.issues?.length"
                        class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
                    >
                        <Icon name="PhWarning" class="mt-0.5 shrink-0" />
                        <ul class="space-y-0.5">
                            <li
                                v-for="issue in website.health.issues"
                                :key="issue"
                            >
                                {{ issue }}
                            </li>
                        </ul>
                    </div>

                    <section>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <h4
                                class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Subscription
                            </h4>
                            <span
                                v-if="website.subscription"
                                class="truncate text-sm font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ website.subscription.title }}
                            </span>
                        </div>

                        <template v-if="website.subscription">
                            <ProgressBar
                                :value="usagePercent(website.subscription)"
                                :show-value="false"
                                style="height: 0.5rem"
                            />
                            <div
                                class="mt-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300"
                            >
                                <span>
                                    <strong
                                        class="text-gray-900 dark:text-gray-100"
                                        >{{
                                            website.subscription.remaining_order
                                        }}</strong
                                    >
                                    of
                                    {{
                                        website.subscription
                                            .total_order_can_handle
                                    }}
                                    orders left
                                </span>
                                <span class="flex items-center gap-3">
                                    <span
                                        >Cost:
                                        <strong
                                            >{{
                                                website.subscription.total_cost
                                            }}
                                            TK</strong
                                        ></span
                                    >
                                    <span
                                        v-if="website.subscription.expires_at"
                                    >
                                        Expires
                                        {{
                                            formatDate(
                                                website.subscription
                                                    .expires_at,
                                            )
                                        }}
                                    </span>
                                </span>
                            </div>
                        </template>
                        <div
                            v-else
                            class="rounded-lg border border-dashed border-gray-200 px-3 py-2.5 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
                        >
                            No plan assigned yet.
                        </div>
                    </section>

                    <section>
                        <h4
                            class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            License Keys
                        </h4>
                        <div
                            v-if="website.licenses?.length"
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <div
                                v-for="license in website.licenses"
                                :key="license.id"
                                class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="truncate text-sm font-medium text-gray-800 dark:text-gray-100"
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
                                        {{ license.last_used_ago || "Never" }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center">
                                    <Button
                                        v-if="license.has_token"
                                        v-tooltip.top="'Copy key'"
                                        icon="pi pi-copy"
                                        size="small"
                                        severity="secondary"
                                        text
                                        rounded
                                        :loading="isRevealing(license.id)"
                                        @click="revealAndCopy(license.id)"
                                    />
                                    <Button
                                        v-tooltip.top="'Edit license'"
                                        icon="pi pi-pencil"
                                        size="small"
                                        severity="secondary"
                                        text
                                        rounded
                                        @click="
                                            handleEditLicense(
                                                license,
                                                website.domain,
                                            )
                                        "
                                    />
                                    <Button
                                        v-tooltip.top="'Delete license'"
                                        icon="pi pi-trash"
                                        size="small"
                                        severity="danger"
                                        text
                                        rounded
                                        @click="handleDeleteLicense(license)"
                                    />
                                </div>
                            </div>
                        </div>
                        <p
                            v-else
                            class="text-sm text-gray-500 dark:text-gray-400"
                        >
                            No license key generated yet.
                        </p>
                    </section>

                    <div class="pt-1">
                        <Button
                            :label="primaryAction(website).label"
                            :icon="primaryAction(website).icon"
                            size="small"
                            class="w-full sm:w-auto"
                            @click="primaryAction(website).run()"
                        />
                    </div>
                </div>
            </PageCard>
        </div>

        <Menu ref="actionMenu" :model="menuItems" :popup="true" />

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
import { router, useForm } from "@inertiajs/vue3";
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
const actionMenu = ref();
const menuItems = ref<any[]>([]);

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

const usagePercent = (subscription: any) => {
    const quota = Number(subscription?.total_order_can_handle) || 0;
    const used = Number(subscription?.total_order_handled) || 0;

    if (quota <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((used / quota) * 100));
};

const primaryAction = (website: any) => {
    if (!website.subscription) {
        return {
            label: "Assign Plan",
            icon: "pi pi-plus",
            run: () => openAssignPlan(website.domain),
        };
    }

    return {
        label: "Generate License",
        icon: "pi pi-key",
        run: () => openGenerateLicense(website.domain),
    };
};

const buildMenuItems = (website: any) => {
    const items: any[] = [];

    if (website.subscription) {
        items.push({
            label: "Edit Plan",
            icon: "pi pi-pencil",
            command: () => openEditPlan(website),
        });
    }

    items.push({
        label: website.subscription ? "Add Another Plan" : "Assign Plan",
        icon: "pi pi-plus",
        command: () => openAssignPlan(website.domain),
    });

    items.push({
        label: "Generate License",
        icon: "pi pi-key",
        command: () => openGenerateLicense(website.domain),
    });

    items.push({ separator: true });

    items.push({
        label: "Order History",
        icon: "pi pi-chart-line",
        command: () =>
            router.visit(
                route("users.packagesOrders", {
                    user_id: props.user.id,
                    domain: website.domain,
                }),
            ),
    });

    if (website.subscription) {
        items.push({
            label: "Usage Breakdown",
            icon: "pi pi-list",
            command: () => {
                showUseDetails.value = website.subscription.id;
            },
        });
        items.push({
            label: "Manage Billing",
            icon: "pi pi-credit-card",
            command: () => router.visit(route("users.billing", props.user.id)),
        });
    }

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
