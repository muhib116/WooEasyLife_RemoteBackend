<template>
    <AuthenticatedLayout title="Partner Credentials">
        <div class="space-y-5">
            <PageHeader
                title="Partner Credentials"
                description="Manage fraud-check logins for each courier. Fresh logins pick randomly among active accounts (then failover). .env remains last-resort fallback."
                icon="PhKey"
                icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                icon-class="text-amber-600 dark:text-amber-400"
            >
                <template #actions>
                    <Button
                        label="Add credential"
                        icon="pi pi-plus"
                        size="small"
                        class="!inline-flex shrink-0 whitespace-nowrap"
                        @click="openCreate"
                    />
                </template>
            </PageHeader>

            <PageCard
                title="Saved credentials"
                description="Passwords are encrypted at rest. .env fallbacks still work until you replace them here."
            >
                <div class="mb-4 flex flex-wrap gap-2">
                    <Button
                        v-for="courier in couriers"
                        :key="courier"
                        :label="courierLabel(courier)"
                        size="small"
                        :severity="filterCourier === courier ? undefined : 'secondary'"
                        :outlined="filterCourier !== courier"
                        @click="filterCourier = filterCourier === courier ? null : courier"
                    />
                </div>

                <div v-if="filteredRows.length === 0" class="py-10 text-center text-sm text-slate-500">
                    No credentials yet. Add one or keep using .env fallbacks below.
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="row in filteredRows"
                        :key="row.source === 'env' ? `env-${row.courier}` : row.id"
                        class="flex flex-col gap-3 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold capitalize">{{ row.courier }}</span>
                                <span
                                    class="rounded px-1.5 py-0.5 text-[11px] font-medium"
                                    :class="row.source === 'env'
                                        ? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300'"
                                >
                                    {{ row.source === 'env' ? '.env' : 'database' }}
                                </span>
                                <span
                                    v-if="row.source !== 'env'"
                                    class="rounded px-1.5 py-0.5 text-[11px] font-medium"
                                    :class="row.is_active
                                        ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300'
                                        : 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300'"
                                >
                                    {{ row.is_active ? 'active' : 'disabled' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                                {{ row.label || 'Untitled' }}
                                <span class="text-slate-400">·</span>
                                <span class="font-mono text-xs">{{ row.identifier }}</span>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Priority {{ row.priority }}
                                <span v-if="row.source !== 'env'" class="text-slate-400"> · fresh logins pick randomly among active accounts</span>
                                <span v-if="row.last_success_at"> · last ok {{ formatDate(row.last_success_at) }}</span>
                                <span v-if="row.last_error" class="text-rose-500"> · {{ row.last_error }}</span>
                            </p>
                        </div>
                        <div v-if="row.source !== 'env'" class="flex shrink-0 flex-wrap gap-2">
                            <Button
                                label="Edit"
                                icon="pi pi-pencil"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="openEdit(row)"
                            />
                            <Button
                                label="Delete"
                                icon="pi pi-trash"
                                size="small"
                                severity="danger"
                                outlined
                                :loading="deletingId === row.id"
                                @click="removeCredential(row)"
                            />
                        </div>
                        <p v-else class="text-xs text-slate-400">Read-only · remove from .env when DB creds are ready</p>
                    </div>
                </div>
            </PageCard>
        </div>

        <Dialog
            v-model:visible="dialogVisible"
            modal
            :header="editingId ? 'Edit credential' : 'Add credential'"
            class="w-full max-w-lg"
        >
            <div class="space-y-4">
                <div class="space-y-1.5">
                    <label class="text-sm font-medium">Courier</label>
                    <Dropdown
                        v-model="form.courier"
                        :options="courierOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                    <p class="text-xs text-slate-500">{{ activeMeta?.help }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium">Label</label>
                    <InputText
                        v-model="form.label"
                        class="w-full"
                        placeholder="e.g. Primary account / Backup"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium">{{ activeMeta?.identifier_label || 'Identifier' }}</label>
                    <InputText
                        v-model="form.identifier"
                        class="w-full"
                        :placeholder="activeMeta?.identifier_placeholder"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium">
                        Password
                        <span v-if="editingId" class="font-normal text-slate-400">(leave blank to keep)</span>
                    </label>
                    <Password
                        v-model="form.secret"
                        class="w-full"
                        input-class="w-full"
                        :feedback="false"
                        toggle-mask
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium">Priority</label>
                    <InputNumber
                        v-model="form.priority"
                        :min="1"
                        :max="9999"
                        class="w-full"
                        input-class="w-full"
                        show-buttons
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Lower number sorts higher in the list. Fresh logins still pick randomly among active accounts.
                    </p>
                </div>

                <div class="flex items-center justify-between gap-4 pt-1">
                    <div class="min-w-0">
                        <label for="credential-active" class="text-sm font-medium">Active</label>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Disabled accounts are skipped during login
                        </p>
                    </div>
                    <ToggleSwitch
                        input-id="credential-active"
                        v-model="form.is_active"
                        aria-label="Active"
                    />
                </div>

                <p v-if="formError" class="text-xs text-rose-500">{{ formError }}</p>
            </div>

            <template #footer>
                <Button label="Cancel" severity="secondary" outlined @click="dialogVisible = false" />
                <Button
                    :label="editingId ? 'Update' : 'Save'"
                    icon="pi pi-save"
                    :loading="saving"
                    @click="saveCredential"
                />
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { computed, reactive, ref } from "vue";
import axios from "axios";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineOptions({ name: "FraudPartnerCredentials" });

type CredentialRow = {
    id: number | null;
    courier: string;
    label: string | null;
    identifier: string;
    is_active: boolean;
    priority: number;
    last_success_at: string | null;
    last_error: string | null;
    source: string;
    read_only?: boolean;
};

const props = defineProps<{
    credentials: CredentialRow[];
    envFallbacks: CredentialRow[];
    courierMeta: Record<string, { identifier_label: string; identifier_placeholder: string; help: string }>;
    couriers: string[];
}>();

const rows = ref<CredentialRow[]>([...props.credentials]);
const filterCourier = ref<string | null>(null);
const dialogVisible = ref(false);
const editingId = ref<number | null>(null);
const saving = ref(false);
const deletingId = ref<number | null>(null);
const formError = ref("");

const form = reactive({
    courier: "steadfast",
    label: "",
    identifier: "",
    secret: "",
    priority: 100,
    is_active: true,
});

const courierOptions = computed(() =>
    props.couriers.map((value) => ({
        value,
        label: courierLabel(value),
    })),
);

const activeMeta = computed(() => props.courierMeta[form.courier] ?? null);

const filteredRows = computed(() => {
    const all = [...rows.value, ...props.envFallbacks];
    if (!filterCourier.value) {
        return all;
    }

    return all.filter((row) => row.courier === filterCourier.value);
});

const courierLabel = (courier: string) => {
    const map: Record<string, string> = {
        steadfast: "Steadfast",
        pathao: "Pathao",
        paperfly: "Paperfly",
        redx: "RedX",
        carrybee: "Carrybee",
    };

    return map[courier] ?? courier;
};

const formatDate = (value: string) => {
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const resetForm = () => {
    form.courier = "steadfast";
    form.label = "";
    form.identifier = "";
    form.secret = "";
    form.priority = 100;
    form.is_active = true;
    formError.value = "";
};

const openCreate = () => {
    editingId.value = null;
    resetForm();
    dialogVisible.value = true;
};

const openEdit = (row: CredentialRow) => {
    editingId.value = row.id;
    form.courier = row.courier;
    form.label = row.label || "";
    form.identifier = row.identifier;
    form.secret = "";
    form.priority = row.priority;
    form.is_active = row.is_active;
    formError.value = "";
    dialogVisible.value = true;
};

const saveCredential = async () => {
    saving.value = true;
    formError.value = "";

    const payload = {
        courier: form.courier,
        label: form.label || null,
        identifier: form.identifier,
        secret: form.secret || null,
        priority: form.priority,
        is_active: form.is_active,
    };

    try {
        if (editingId.value) {
            const { data } = await axios.put(
                route("frauds.credentials.update", editingId.value),
                payload,
            );
            const updated = data.credential as CredentialRow;
            rows.value = rows.value.map((row) => (row.id === updated.id ? updated : row));
        } else {
            const { data } = await axios.post(route("frauds.credentials.store"), payload);
            rows.value = [...rows.value, data.credential as CredentialRow];
        }
        dialogVisible.value = false;
    } catch (error: any) {
        const errors = error?.response?.data?.errors;
        const first = errors ? Object.values(errors).flat()[0] : null;
        formError.value =
            (typeof first === "string" ? first : null) ||
            error?.response?.data?.message ||
            "Unable to save credential.";
    } finally {
        saving.value = false;
    }
};

const removeCredential = async (row: CredentialRow) => {
    if (!row.id || !confirm(`Delete ${row.courier} credential ${row.identifier}?`)) {
        return;
    }

    deletingId.value = row.id;
    try {
        await axios.delete(route("frauds.credentials.destroy", row.id));
        rows.value = rows.value.filter((item) => item.id !== row.id);
    } finally {
        deletingId.value = null;
    }
};
</script>
