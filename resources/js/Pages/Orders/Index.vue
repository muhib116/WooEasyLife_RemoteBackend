<template>
    <AuthenticatedLayout title="Landing Orders">
        <div class="space-y-5">
            <PageHeader
                title="Landing Orders"
                description="Subscription requests from the public pricing page (guests and logged-in users). For merchant portal payments, use Payment Requests."
                icon="PhShoppingCart"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <button
                    v-for="card in statCards"
                    :key="card.value"
                    type="button"
                    class="text-left transition hover:scale-[1.01] focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded-2xl"
                    :class="activeStatus === card.value ? 'ring-2 ring-primary-500/60' : ''"
                    @click="filterByStatus(card.value)"
                >
                    <StatCard
                        :title="card.label"
                        :value="counts[card.countKey]"
                        :icon="card.icon"
                        :accent-class="card.accentClass"
                    />
                </button>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <SelectButton
                    v-model="activeStatus"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    @change="onStatusChange"
                />
                <IconField class="w-full sm:max-w-xs">
                    <InputIcon>
                        <i class="pi pi-search" />
                    </InputIcon>
                    <InputText
                        v-model="searchQuery"
                        placeholder="Search email, domain, txn..."
                        class="w-full"
                        @keyup.enter="applySearch"
                    />
                </IconField>
            </div>

            <PageCard
                title="Orders"
                :description="`${orders.length} shown`"
                no-padding
            >
                <EmptyState
                    v-if="!orders.length"
                    title="No landing orders"
                    :description="emptyDescription"
                    icon="PhShoppingCart"
                />

                <DataTable
                    v-else
                    :value="orders"
                    v-model:expandedRows="expandedRows"
                    data-key="id"
                    paginator
                    :rows="15"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column expander style="width: 3rem" />
                    <Column header="Submitted">
                        <template #body="{ data }">
                            <span class="whitespace-nowrap text-xs text-gray-500">
                                {{ dateFormat(data.created_at) }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Customer">
                        <template #body="{ data }">
                            <div>
                                <div class="font-medium">
                                    <Link
                                        :href="route('orders.show', data.id)"
                                        class="text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ data.customer_name || data.user?.name || "Guest" }}
                                    </Link>
                                </div>
                                <div class="text-xs text-gray-500">{{ data.email }}</div>
                                <div class="text-xs text-gray-500">{{ data.contact_number }}</div>
                            </div>
                        </template>
                    </Column>
                    <Column header="WhatsApp">
                        <template #body="{ data }">
                            <a
                                :href="whatsappLink(data.whatsapp_number)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-primary-600 hover:underline dark:text-primary-400"
                            >
                                {{ data.whatsapp_number }}
                            </a>
                        </template>
                    </Column>
                    <Column field="domain" header="Website" />
                    <Column header="Plan">
                        <template #body="{ data }">
                            {{ data.package_hub?.title || "—" }}
                        </template>
                    </Column>
                    <Column header="Amount">
                        <template #body="{ data }">
                            {{ data.total_amount }} TK
                        </template>
                    </Column>
                    <Column header="Txn ID">
                        <template #body="{ data }">
                            {{ data.transaction_id || "—" }}
                        </template>
                    </Column>
                    <Column field="transaction_method" header="Method" />
                    <Column header="Sender">
                        <template #body="{ data }">
                            {{ data.account_number || "—" }}
                        </template>
                    </Column>
                    <Column field="status" header="Status">
                        <template #body="{ data }">
                            <StatusBadge
                                :label="data.status"
                                :variant="statusVariant(data.status)"
                            />
                        </template>
                    </Column>
                    <Column header="Actions" header-class="text-right">
                        <template #body="{ data }">
                            <TableActions>
                                <TableActionButton
                                    action="view"
                                    tooltip="View details"
                                    @click="router.visit(route('orders.show', data.id))"
                                />
                                <TableActionButton
                                    v-if="data.status === 'pending' || data.status === 'draft'"
                                    action="contact"
                                    tooltip="Mark contacted"
                                    @click="confirmStatusUpdate(data, 'contacted')"
                                />
                                <TableActionButton
                                    v-if="data.status === 'draft'"
                                    action="approve"
                                    tooltip="Promote to pending"
                                    @click="confirmStatusUpdate(data, 'pending')"
                                />
                                <TableActionButton
                                    v-if="data.status !== 'converted' && data.status !== 'draft'"
                                    action="approve"
                                    tooltip="Convert to merchant"
                                    @click="openConvertDialog(data)"
                                />
                                <TableActionButton
                                    v-if="data.status === 'converted' && data.converted_access_token_id"
                                    action="key"
                                    tooltip="Reveal license"
                                    @click="revealLicense(data)"
                                />
                                <TableActionButton
                                    v-if="data.status === 'converted' && data.user_id"
                                    action="navigate"
                                    tooltip="Open merchant"
                                    @click="router.visit(route('users.view', data.user_id))"
                                />
                                <TableActionButton
                                    v-if="data.status !== 'rejected'"
                                    action="reject"
                                    tooltip="Reject"
                                    @click="confirmStatusUpdate(data, 'rejected')"
                                />
                            </TableActions>
                        </template>
                    </Column>
                    <template #expansion="{ data }">
                        <div class="grid gap-4 bg-slate-50/80 px-4 py-4 dark:bg-slate-900/40 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Address
                                </p>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                    {{ data.address || "—" }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Note
                                </p>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                    {{ data.note || "—" }}
                                </p>
                            </div>
                            <div v-if="data.package_payment_request">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Linked payment request
                                </p>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                    #{{ data.package_payment_request.id }}
                                    ({{ data.package_payment_request.status }})
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Source
                                </p>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                    {{ data.source || "landing_pricing" }}
                                </p>
                            </div>
                            <div v-if="data.user_id">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Merchant
                                </p>
                                <Link
                                    :href="route('users.view', data.user_id)"
                                    class="mt-1 inline-flex text-sm text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    Open merchant #{{ data.user_id }}
                                </Link>
                            </div>
                        </div>
                    </template>
                </DataTable>
            </PageCard>
        </div>

        <!-- Verify before convert -->
        <Dialog
            v-model:visible="convertDialogVisible"
            modal
            header="Convert to merchant"
            :style="{ width: '34rem' }"
            :breakpoints="{ '640px': '95vw' }"
            :closable="!converting"
            :close-on-escape="!converting"
        >
            <div v-if="convertTarget" class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Verify this landing order data carefully. Confirming will create (or update)
                    the merchant, add the website, assign the plan, record billing, and issue a license.
                </p>

                <div class="rounded-xl border border-gray-200 bg-slate-50 p-4 text-sm dark:border-gray-700 dark:bg-slate-900/50">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Name</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                {{ convertTarget.customer_name || "—" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Website</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                {{ convertTarget.domain }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Plan</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                {{ convertTarget.package_hub?.title || "—" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Amount</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                {{ convertTarget.total_amount }} TK
                                <span v-if="convertTarget.transaction_method" class="text-gray-500">
                                    · {{ convertTarget.transaction_method }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Txn ID</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                {{ convertTarget.transaction_id || "—" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sender</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                {{ convertTarget.account_number || "—" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Phone / WhatsApp</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                {{ convertTarget.contact_number }}
                                <span v-if="convertTarget.whatsapp_number" class="text-gray-500">
                                    / {{ convertTarget.whatsapp_number }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Address</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                {{ convertTarget.address || "—" }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    v-if="previewLoading"
                    class="rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-500 dark:border-gray-700"
                >
                    Checking domain DNS and conflicts…
                </div>

                <div
                    v-else-if="convertPreview"
                    class="space-y-3"
                >
                    <div
                        v-if="convertPreview.blockers?.length"
                        class="rounded-xl border border-rose-300/50 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100"
                    >
                        <p class="font-semibold">Cannot convert yet</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="item in convertPreview.blockers" :key="item">{{ item }}</li>
                        </ul>
                    </div>

                    <div
                        v-if="convertPreview.warnings?.length"
                        class="rounded-xl border border-amber-300/50 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
                    >
                        <p class="font-semibold">Warnings</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="item in convertPreview.warnings" :key="item">{{ item }}</li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-slate-50 p-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-slate-900/50 dark:text-gray-300">
                        <p>
                            DNS:
                            <span :class="convertPreview.dns_ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                {{ convertPreview.dns_ok ? 'OK' : 'Missing A record' }}
                            </span>
                            · Merchant:
                            {{ convertPreview.user_resolution?.label || '—' }}
                            · Billing:
                            {{ convertPreview.payment_resolution?.action || '—' }}
                        </p>
                        <p
                            v-if="convertPreview.credentials?.must_change_password"
                            class="mt-1"
                        >
                            New merchant must change password on first portal login.
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-amber-300/50 bg-amber-50 p-4 text-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                    <p class="font-semibold text-amber-800 dark:text-amber-200">Login credentials</p>
                    <ul class="mt-2 space-y-1 text-amber-900/90 dark:text-amber-100/90">
                        <li>
                            <span class="text-amber-700/80 dark:text-amber-200/70">Username (email):</span>
                            {{ convertTarget.email }}
                        </li>
                        <li>
                            <span class="text-amber-700/80 dark:text-amber-200/70">Password (phone):</span>
                            {{ convertTarget.contact_number }}
                            <span class="text-xs text-amber-700/70 dark:text-amber-200/60">
                                — only applied when creating a new merchant
                            </span>
                        </li>
                        <li class="text-xs text-amber-700/80 dark:text-amber-200/70">
                            Source tag: landing_order:{{ convertTarget.id }}
                        </li>
                    </ul>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    outlined
                    :disabled="converting"
                    @click="convertDialogVisible = false"
                />
                <Button
                    label="Confirm & convert"
                    icon="pi pi-check"
                    severity="success"
                    :loading="converting || previewLoading"
                    :disabled="converting || previewLoading || !canConfirmConvert"
                    @click="submitConvert"
                />
            </template>
        </Dialog>

        <!-- Success: license + merchant nav -->
        <Dialog
            v-model:visible="successDialogVisible"
            modal
            header="Merchant ready"
            :style="{ width: '34rem' }"
            :breakpoints="{ '640px': '95vw' }"
        >
            <div class="space-y-4 text-sm">
                <p class="text-gray-600 dark:text-gray-300">
                    Conversion completed. Copy the license key and open the merchant panel when ready.
                </p>

                <div
                    v-if="successNotifySummary"
                    class="rounded-xl border border-gray-200 bg-slate-50 p-3 text-sm dark:border-gray-700 dark:bg-slate-900/50"
                >
                    {{ successNotifySummary }}
                </div>

                <div
                    v-if="successLoginEmail"
                    class="rounded-xl border border-gray-200 bg-slate-50 p-3 dark:border-gray-700 dark:bg-slate-900/50"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Login email</p>
                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ successLoginEmail }}</p>
                    <p
                        v-if="successUserCreated"
                        class="mt-1 text-xs text-gray-500"
                    >
                        Initial password is the customer phone number from the order.
                    </p>
                    <p
                        v-else
                        class="mt-1 text-xs text-gray-500"
                    >
                        Existing merchant account was reused — password was not changed.
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">License key</p>
                    <div class="mt-2 flex gap-2">
                        <InputText
                            :model-value="successLicenseToken || ''"
                            readonly
                            class="w-full font-mono text-xs"
                        />
                        <Button
                            icon="pi pi-copy"
                            severity="secondary"
                            outlined
                            :disabled="!successLicenseToken"
                            @click="copyLicense"
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Close"
                    severity="secondary"
                    outlined
                    @click="successDialogVisible = false"
                />
                <Button
                    v-if="successOrderId"
                    label="Open order"
                    icon="pi pi-eye"
                    severity="secondary"
                    outlined
                    @click="goToOrder"
                />
                <Button
                    v-if="successUserId"
                    label="Open merchant panel"
                    icon="pi pi-arrow-right"
                    @click="goToMerchant"
                />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import StatCard from "@/components/StatCard.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import { dateFormat } from "@/Helper";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { useConfirm } from "primevue";
import { useToast } from "primevue/usetoast";
import axios from "axios";
import InputText from "primevue/inputtext";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

defineOptions({
    name: "OrdersIndex",
});

const props = defineProps<{
    orders: any[];
    counts: {
        draft: number;
        pending: number;
        contacted: number;
        converted: number;
        rejected: number;
    };
    status: string;
    search?: string;
}>();

const page = usePage();
const confirm = useConfirm();
const toast = useToast();

const activeStatus = ref(props.status || "pending");
const searchQuery = ref(props.search || "");
const expandedRows = ref({});
const convertDialogVisible = ref(false);
const convertTarget = ref<any | null>(null);
const convertPreview = ref<any | null>(null);
const previewLoading = ref(false);
const converting = ref(false);
const successDialogVisible = ref(false);
const successLicenseToken = ref<string | null>(null);
const successUserId = ref<number | null>(null);
const successOrderId = ref<number | null>(null);
const successLoginEmail = ref<string | null>(null);
const successUserCreated = ref(false);
const successNotifyEmail = ref(false);
const successNotifySms = ref(false);

const canConfirmConvert = computed(() => Boolean(convertPreview.value?.ok));

const successNotifySummary = computed(() => {
    const parts = [];

    if (successNotifyEmail.value) {
        parts.push("email");
    }

    if (successNotifySms.value) {
        parts.push("SMS");
    }

    if (! parts.length) {
        return null;
    }

    return `Merchant notified via ${parts.join(" + ")}.`;
});
const statCards = [
    { label: "Draft", value: "draft", countKey: "draft", icon: "PhUserPlus", accentClass: "bg-violet-500" },
    { label: "Pending", value: "pending", countKey: "pending", icon: "PhHourglass", accentClass: "bg-amber-500" },
    { label: "Contacted", value: "contacted", countKey: "contacted", icon: "PhChatCircle", accentClass: "bg-sky-500" },
    { label: "Converted", value: "converted", countKey: "converted", icon: "PhCheckCircle", accentClass: "bg-emerald-500" },
    { label: "Rejected", value: "rejected", countKey: "rejected", icon: "PhXCircle", accentClass: "bg-red-500" },
] as const;

const statusOptions = [
    { label: "Draft", value: "draft" },
    { label: "Pending", value: "pending" },
    { label: "Contacted", value: "contacted" },
    { label: "Converted", value: "converted" },
    { label: "Rejected", value: "rejected" },
    { label: "All", value: "all" },
];

const emptyDescription = computed(() => {
    if (searchQuery.value) {
        return "No orders match your search. Try a different email, domain, or transaction ID.";
    }

    if (activeStatus.value === "all") {
        return "Landing subscription requests will appear here when customers submit the pricing wizard.";
    }

    if (activeStatus.value === "draft") {
        return "No draft orders yet. Drafts appear when someone fills the contact form before finishing payment.";
    }

    return `No ${activeStatus.value} landing orders right now.`;
});

const statusVariant = (status: string) => {
    if (status === "converted") return "success";
    if (status === "pending") return "warning";
    if (status === "contacted") return "info";
    if (status === "rejected") return "danger";
    if (status === "draft") return "neutral";
    return "neutral";
};

const whatsappLink = (phone: string) => {
    const digits = String(phone || "").replace(/\D/g, "");
    const normalized = digits.startsWith("880") ? digits : `880${digits.replace(/^0/, "")}`;

    return `https://wa.me/${normalized}`;
};

const queryParams = () => ({
    status: activeStatus.value,
    ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
});

const onStatusChange = () => {
    router.get(route("orders.index"), queryParams(), { preserveState: true, replace: true });
};

const filterByStatus = (status: string) => {
    activeStatus.value = status;
    onStatusChange();
};

const applySearch = () => {
    router.get(route("orders.index"), queryParams(), { preserveState: true, replace: true });
};

const statusConfirmCopy: Record<string, { header: string; message: string; acceptLabel: string; severity?: "danger" }> = {
    pending: {
        header: "Promote lead to pending?",
        message: "Use this when the lead is ready for payment review (like a normal order).",
        acceptLabel: "Promote to pending",
    },
    contacted: {
        header: "Mark as contacted?",
        message: "Use this when you have reached the customer by phone or WhatsApp.",
        acceptLabel: "Mark contacted",
    },
    rejected: {
        header: "Reject this order?",
        message: "The inquiry will be marked rejected. This does not notify the customer automatically.",
        acceptLabel: "Reject",
        severity: "danger",
    },
};

const confirmStatusUpdate = (item: { id: number }, status: string) => {
    const copy = statusConfirmCopy[status];

    if (!copy) {
        return;
    }

    confirm.require({
        header: copy.header,
        message: copy.message,
        rejectProps: { label: "Cancel", severity: "secondary", outlined: true },
        acceptProps: { label: copy.acceptLabel, severity: copy.severity ?? "success" },
        accept: () => {
            router.post(route("orders.updateStatus", { order: item.id }), { status });
        },
    });
};

const openConvertDialog = async (item: any) => {
    convertTarget.value = item;
    convertPreview.value = null;
    convertDialogVisible.value = true;
    previewLoading.value = true;

    try {
        const { data } = await axios.get(route('orders.convertPreview', { order: item.id }));
        convertPreview.value = data;
    } catch (e: any) {
        convertPreview.value = {
            ok: false,
            blockers: [e?.response?.data?.message || 'Could not run conversion pre-checks.'],
            warnings: [],
        };
    } finally {
        previewLoading.value = false;
    }
};

const revealLicense = (item: { id: number }) => {
    router.post(route('orders.revealLicense', { order: item.id }), {}, { preserveScroll: true });
};

const submitConvert = () => {
    if (! convertTarget.value?.id || converting.value || ! canConfirmConvert.value) {
        return;
    }

    converting.value = true;

    router.post(
        route('orders.convert', { order: convertTarget.value.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                converting.value = false;
            },
            onSuccess: () => {
                convertDialogVisible.value = false;
            },
        },
    );
};

const copyLicense = async () => {
    if (!successLicenseToken.value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(successLicenseToken.value);
        toast.add({
            severity: "success",
            summary: "Copied",
            detail: "License key copied to clipboard",
            life: 2500,
            group: "br",
        });
    } catch {
        toast.add({
            severity: "error",
            summary: "Copy failed",
            detail: "Could not copy license key",
            life: 3000,
            group: "br",
        });
    }
};

const goToMerchant = () => {
    if (! successUserId.value) {
        return;
    }

    router.visit(route("users.view", successUserId.value));
};

const goToOrder = () => {
    if (! successOrderId.value) {
        return;
    }

    router.visit(route("orders.show", successOrderId.value));
};

watch(
    () => page.props.flash,
    (flash: any) => {
        if (! flash?.license_token || ! flash?.converted_user_id) {
            return;
        }

        successLicenseToken.value = String(flash.license_token);
        successUserId.value = Number(flash.converted_user_id);
        successOrderId.value = flash.converted_order_id
            ? Number(flash.converted_order_id)
            : null;
        successLoginEmail.value = flash.converted_login_email
            ? String(flash.converted_login_email)
            : null;
        successUserCreated.value = Boolean(flash.converted_user_created);
        successNotifyEmail.value = Boolean(flash.converted_notify_email);
        successNotifySms.value = Boolean(flash.converted_notify_sms);
        successDialogVisible.value = true;
    },
    { immediate: true, deep: true },
);
</script>
