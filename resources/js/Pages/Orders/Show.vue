<template>
    <AuthenticatedLayout :title="`Landing Order #${order.id}`">
        <div class="space-y-5">
            <PageHeader
                :title="`Landing Order #${order.id}`"
                :description="`${order.customer_name || 'Guest'} · ${order.domain}`"
                icon="PhShoppingCart"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            >
                <template #actions>
                    <Link :href="route('orders.index', { status: order.status || 'pending' })">
                        <Button
                            label="Back to orders"
                            icon="pi pi-arrow-left"
                            severity="secondary"
                            outlined
                            size="small"
                            as="span"
                        />
                    </Link>
                </template>
            </PageHeader>

            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :label="order.status" :variant="statusVariant(order.status)" />
                <span class="text-sm text-gray-500">
                    Submitted {{ dateFormat(order.created_at) }}
                </span>
                <span v-if="order.converted_at" class="text-sm text-gray-500">
                    · Converted {{ dateFormat(order.converted_at) }}
                </span>
            </div>

            <div class="grid gap-5 lg:grid-cols-3">
                <div class="space-y-5 lg:col-span-2">
                    <PageCard title="Customer & store">
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Name</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.customer_name || "—" }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Email</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.email }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Phone</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.contact_number }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">WhatsApp</dt>
                                <dd class="mt-1 text-sm font-medium">
                                    <a
                                        v-if="whatsappUrl"
                                        :href="whatsappUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ order.whatsapp_number || order.contact_number }}
                                    </a>
                                    <span v-else>{{ order.whatsapp_number || "—" }}</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Website</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.domain }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Source</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.source || "landing_pricing" }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Address</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.address || "—" }}</dd>
                            </div>
                        </dl>
                    </PageCard>

                    <PageCard title="Plan & payment">
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Plan</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.package_hub?.title || "—" }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Amount</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.total_amount }} TK</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Method</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.transaction_method || "—" }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Txn ID</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.transaction_id || "—" }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sender</dt>
                                <dd class="mt-1 text-sm font-medium">{{ order.account_number || "—" }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Billing request</dt>
                                <dd class="mt-1 text-sm font-medium">
                                    <span v-if="order.package_payment_request">
                                        #{{ order.package_payment_request.id }}
                                        ({{ order.package_payment_request.status }})
                                    </span>
                                    <span v-else>—</span>
                                </dd>
                            </div>
                        </dl>
                        <p v-if="order.note" class="mt-4 whitespace-pre-wrap rounded-xl bg-slate-50 p-3 text-sm text-gray-600 dark:bg-slate-900/50 dark:text-gray-300">
                            {{ order.note }}
                        </p>
                    </PageCard>

                    <PageCard title="Activity">
                        <ol v-if="timeline.length" class="space-y-3">
                            <li
                                v-for="(event, index) in timeline"
                                :key="`${event.type}-${index}`"
                                class="rounded-xl border border-gray-100 px-4 py-3 dark:border-gray-700/80"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ eventLabel(event.type) }}
                                    </p>
                                    <span class="text-xs text-gray-500">
                                        {{ event.at ? dateFormat(event.at) : "—" }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    {{ event.message || "—" }}
                                </p>
                            </li>
                        </ol>
                        <p v-else class="text-sm text-gray-500">
                            No conversion activity yet.
                        </p>

                        <div
                            v-if="notifications"
                            class="mt-4 rounded-xl border border-gray-100 px-4 py-3 text-sm dark:border-gray-700/80"
                        >
                            <p class="font-semibold text-gray-900 dark:text-white">Notifications</p>
                            <p class="mt-1 text-gray-600 dark:text-gray-300">
                                Email: {{ notifications.email ? "sent" : "not sent" }}
                                · SMS: {{ notifications.sms ? "sent" : "not sent" }}
                            </p>
                            <ul
                                v-if="notifications.errors?.length"
                                class="mt-2 list-disc space-y-1 pl-5 text-rose-600 dark:text-rose-400"
                            >
                                <li v-for="err in notifications.errors" :key="err">{{ err }}</li>
                            </ul>
                        </div>
                    </PageCard>
                </div>

                <div class="space-y-5">
                    <PageCard title="Actions">
                        <div class="flex flex-col gap-2">
                            <Button
                                v-if="order.status === 'pending' || order.status === 'draft'"
                                label="Mark contacted"
                                icon="pi pi-phone"
                                severity="info"
                                outlined
                                @click="updateStatus('contacted')"
                            />
                            <Button
                                v-if="order.status === 'draft'"
                                label="Promote to pending"
                                icon="pi pi-arrow-up"
                                severity="warning"
                                outlined
                                @click="updateStatus('pending')"
                            />
                            <Button
                                v-if="order.status !== 'converted' && order.status !== 'rejected' && order.status !== 'draft'"
                                label="Convert to merchant"
                                icon="pi pi-check"
                                severity="success"
                                :loading="converting || previewLoading"
                                @click="openConvert"
                            />
                            <Button
                                v-if="order.status === 'converted' && order.converted_access_token_id"
                                label="Reveal license"
                                icon="pi pi-key"
                                severity="secondary"
                                outlined
                                @click="revealLicense"
                            />
                            <Link
                                v-if="order.user_id"
                                :href="route('users.view', order.user_id)"
                            >
                                <Button
                                    label="Open merchant panel"
                                    icon="pi pi-arrow-right"
                                    class="w-full"
                                    as="span"
                                />
                            </Link>
                            <a
                                v-if="whatsappUrl"
                                :href="whatsappUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Button
                                    label="WhatsApp merchant"
                                    icon="pi pi-whatsapp"
                                    severity="success"
                                    outlined
                                    class="w-full"
                                    as="span"
                                />
                            </a>
                            <Button
                                v-if="order.status !== 'rejected' && order.status !== 'converted'"
                                label="Reject"
                                icon="pi pi-times"
                                severity="danger"
                                outlined
                                @click="updateStatus('rejected')"
                            />
                        </div>
                    </PageCard>

                    <PageCard v-if="order.user" title="Linked merchant">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ order.user.name }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500">{{ order.user.email }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ order.user.phone }}</p>
                        <p
                            v-if="order.user.acquisition_source"
                            class="mt-2 text-xs text-amber-700 dark:text-amber-300"
                        >
                            {{ order.user.acquisition_source }}
                        </p>
                    </PageCard>
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="convertVisible"
            modal
            header="Convert to merchant"
            :style="{ width: '32rem' }"
            :breakpoints="{ '640px': '95vw' }"
        >
            <div class="space-y-3 text-sm">
                <p class="text-gray-600 dark:text-gray-300">
                    Verify data, then confirm to create/update merchant, website, billing, plan, and license.
                </p>
                <div v-if="previewLoading" class="text-gray-500">Running pre-checks…</div>
                <div
                    v-else-if="preview?.blockers?.length"
                    class="rounded-xl border border-rose-300/40 bg-rose-50 p-3 text-rose-800 dark:bg-rose-500/10 dark:text-rose-100"
                >
                    <ul class="list-disc space-y-1 pl-5">
                        <li v-for="b in preview.blockers" :key="b">{{ b }}</li>
                    </ul>
                </div>
                <div
                    v-else-if="preview"
                    class="rounded-xl border border-emerald-300/40 bg-emerald-50 p-3 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100"
                >
                    Ready · DNS {{ preview.dns_ok ? "OK" : "fail" }} · {{ preview.user_resolution?.label }}
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="convertVisible = false" />
                <Button
                    label="Confirm & convert"
                    severity="success"
                    :disabled="!preview?.ok || converting"
                    :loading="converting"
                    @click="submitConvert"
                />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import { dateFormat } from "@/Helper";
import { Link, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useConfirm } from "primevue";
import axios from "axios";
import Button from "primevue/button";
import Dialog from "primevue/dialog";

const props = defineProps<{
    order: any;
    whatsappUrl?: string | null;
    auditEvents?: any[];
    notifications?: any;
}>();

const confirm = useConfirm();
const convertVisible = ref(false);
const previewLoading = ref(false);
const converting = ref(false);
const preview = ref<any>(null);

const timeline = computed(() => props.auditEvents || props.order?.conversion_meta?.events || []);

const statusVariant = (status: string) => {
    if (status === "converted") return "success";
    if (status === "pending") return "warning";
    if (status === "contacted") return "info";
    if (status === "rejected") return "danger";
    if (status === "draft") return "neutral";
    return "neutral";
};

const eventLabel = (type: string) => {
    if (type === "converted") return "Converted";
    if (type === "notified") return "Merchant notified";
    return type || "Event";
};

const updateStatus = (status: string) => {
    const isReject = status === "rejected";
    const isPromote = status === "pending";
    confirm.require({
        header: isReject
            ? "Reject this order?"
            : isPromote
                ? "Promote lead to pending?"
                : "Mark as contacted?",
        message: isReject
            ? "The inquiry will be marked rejected."
            : isPromote
                ? "Use this when the lead is ready for payment review."
                : "Use this when you have reached the customer.",
        rejectProps: { label: "Cancel", severity: "secondary", outlined: true },
        acceptProps: {
            label: isReject ? "Reject" : isPromote ? "Promote" : "Mark contacted",
            severity: isReject ? "danger" : isPromote ? "warning" : "success",
        },
        accept: () => {
            router.post(route("orders.updateStatus", { order: props.order.id }), { status });
        },
    });
};

const openConvert = async () => {
    convertVisible.value = true;
    previewLoading.value = true;
    preview.value = null;

    try {
        const { data } = await axios.get(route("orders.convertPreview", { order: props.order.id }));
        preview.value = data;
    } catch (e: any) {
        preview.value = {
            ok: false,
            blockers: [e?.response?.data?.message || "Pre-check failed."],
        };
    } finally {
        previewLoading.value = false;
    }
};

const submitConvert = () => {
    if (! preview.value?.ok || converting.value) {
        return;
    }

    converting.value = true;
    router.post(
        route("orders.convert", { order: props.order.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                converting.value = false;
            },
            onSuccess: () => {
                convertVisible.value = false;
            },
        },
    );
};

const revealLicense = () => {
    router.post(route("orders.revealLicense", { order: props.order.id }), {}, { preserveScroll: true });
};
</script>
