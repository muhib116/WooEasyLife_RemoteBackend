<template>
    <AuthenticatedLayout title="Steadfast API">
        <div class="space-y-5">
            <PageHeader
                title="Steadfast API"
                description="Hub checks use keys that gave parcel counts first. A rate limit or a Steadfast outage only rests a key for a few minutes. A key Steadfast rejects is checked again automatically, or when you press Test key. Each store's plugin still uses that store's own Steadfast keys."
                icon="PhKey"
                icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                icon-class="text-amber-600 dark:text-amber-400"
            />

            <PageCard
                title="Saved keys"
                description="From the Steadfast merchant dashboard: API guide. Keys are encrypted in the database. The secret stays visible here so you can copy it."
            >
                <p
                    v-if="savedMessage"
                    class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                >
                    {{ savedMessage }}
                </p>

                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div
                        class="rounded-xl border px-4 py-3 text-sm"
                        :class="accounts.length
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                            : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'"
                    >
                        <span v-if="accounts.length">Using {{ accounts.length }} key {{ accounts.length === 1 ? 'pair' : 'pairs' }} saved in this panel.</span>
                        <span v-else-if="credential.env_configured">No keys saved here. The public check is using the .env pair.</span>
                        <span v-else>No keys saved here, and .env has no Steadfast API pair. Public and admin checks cannot call the score API yet.</span>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            {{ merchantKeyCount }} {{ merchantKeyCount === 1 ? 'merchant has' : 'merchants have' }} API keys
                        </p>
                        <Button
                            type="button"
                            label="Add all merchant keys"
                            icon="pi pi-users"
                            severity="secondary"
                            outlined
                            class="!min-h-11"
                            :loading="importing"
                            @click="importMerchants"
                        />
                    </div>
                </div>

                <div v-if="accounts.length === 0" class="mb-6 text-sm text-slate-500">
                    No saved keys yet.
                </div>

                <div v-else class="mb-6 space-y-3">
                    <div
                        v-for="(account, index) in accounts"
                        :key="account.id"
                        class="rounded-xl border px-4 py-3"
                        :class="account.last_error && !account.error_temporary
                            ? 'border-rose-300 bg-rose-50/60 dark:border-rose-500/40 dark:bg-rose-500/10'
                            : account.resting_until || account.error_temporary
                                ? 'border-amber-300 bg-amber-50/70 dark:border-amber-500/40 dark:bg-amber-500/10'
                                : 'border-slate-200 dark:border-slate-600'"
                    >
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ drafts[account.id]?.label || account.label || `Key ${index + 1}` }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    Used {{ account.use_count || 0 }} {{ (account.use_count || 0) === 1 ? 'time' : 'times' }}
                                    <span v-if="account.shape === 'counts'"> · Gives parcel counts</span>
                                    <span v-else-if="account.shape === 'ratio'"> · Gives a ratio only, so it is asked last</span>
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    v-if="account.last_error || account.resting_until"
                                    type="button"
                                    label="Test key"
                                    icon="pi pi-check"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :loading="testingId === account.id"
                                    @click="testAccount(account.id)"
                                />
                                <Button
                                    type="button"
                                    label="Remove"
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="danger"
                                    outlined
                                    :loading="removingId === account.id"
                                    @click="removeAccount(account.id)"
                                />
                            </div>
                        </div>

                        <div class="space-y-3" v-if="drafts[account.id]">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500" :for="`steadfast-label-${account.id}`">Label</label>
                                <InputText
                                    :id="`steadfast-label-${account.id}`"
                                    v-model="drafts[account.id].label"
                                    class="w-full"
                                    autocomplete="off"
                                />
                            </div>
                            <div>
                                <p class="mb-1 text-xs font-medium text-slate-500">API key</p>
                                <div class="flex gap-2">
                                    <InputText v-model="drafts[account.id].api_key" class="w-full font-mono" autocomplete="off" />
                                    <Button
                                        type="button"
                                        label="Copy"
                                        icon="pi pi-copy"
                                        severity="secondary"
                                        outlined
                                        class="shrink-0"
                                        @click="copyValue(drafts[account.id].api_key, account.id + '-api')"
                                    />
                                </div>
                            </div>
                            <div>
                                <p class="mb-1 text-xs font-medium text-slate-500">Secret key</p>
                                <div class="flex gap-2">
                                    <InputText v-model="drafts[account.id].secret_key" class="w-full font-mono" autocomplete="off" />
                                    <Button
                                        type="button"
                                        label="Copy"
                                        icon="pi pi-copy"
                                        severity="secondary"
                                        outlined
                                        class="shrink-0"
                                        @click="copyValue(drafts[account.id].secret_key, account.id + '-secret')"
                                    />
                                </div>
                                <p v-if="copiedKey === account.id + '-secret' || copiedKey === account.id + '-api'" class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                    Copied
                                </p>
                            </div>
                            <div v-if="isDirty(account)" class="flex justify-end">
                                <Button
                                    type="button"
                                    label="Save"
                                    icon="pi pi-check"
                                    size="small"
                                    class="!min-h-10"
                                    :loading="savingId === account.id"
                                    @click="saveAccount(account.id)"
                                />
                            </div>
                            <p v-if="rowErrorId === account.id && rowError" class="text-sm text-rose-600">{{ rowError }}</p>
                            <p
                                v-if="account.last_error && !account.error_temporary"
                                class="rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-slate-900 dark:text-rose-300"
                            >
                                {{ account.last_error }}
                                <span v-if="account.last_error_at" class="mt-1 block text-xs text-rose-500 dark:text-rose-300/80">{{ account.last_error_at }}</span>
                                <span v-if="account.next_check_at && account.next_check_at !== 'soon'" class="mt-1 block text-xs text-rose-500 dark:text-rose-300/80">Checks ask this key again by themselves at {{ account.next_check_at }}. It comes back as soon as Steadfast accepts it.</span>
                                <span v-else-if="account.next_check_at === 'soon'" class="mt-1 block text-xs text-rose-500 dark:text-rose-300/80">Checks ask this key again by themselves soon. It comes back as soon as Steadfast accepts it.</span>
                            </p>
                            <p
                                v-else-if="account.error_temporary || account.resting_until"
                                class="rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-slate-900 dark:text-amber-200"
                            >
                                <span v-if="account.last_error">{{ account.last_error }}</span>
                                <span v-else>Steadfast asked this key to slow down.</span>
                                <span v-if="account.resting_until && account.resting_until !== 'soon'" class="mt-1 block text-xs">This key is not off. Checks try it again at {{ account.resting_until }}.</span>
                                <span v-else-if="account.resting_until === 'soon'" class="mt-1 block text-xs">This key is not off. Checks try it again soon.</span>
                                <span v-else class="mt-1 block text-xs">This key is not off. It is asked again by itself, and this message goes away when Steadfast answers.</span>
                            </p>
                        </div>
                    </div>
                </div>

                <form class="space-y-4 border-t border-slate-200 pt-4 dark:border-slate-600" @submit.prevent="save">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Add another key</p>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200" for="steadfast-label">
                            Label
                        </label>
                        <InputText
                            id="steadfast-label"
                            v-model="form.label"
                            class="w-full"
                            autocomplete="off"
                            placeholder="Optional, e.g. Shop 2"
                        />
                        <p v-if="form.errors.label" class="mt-1 text-sm text-rose-600">{{ form.errors.label }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200" for="steadfast-api-key">
                            API key
                        </label>
                        <InputText
                            id="steadfast-api-key"
                            v-model="form.api_key"
                            class="w-full font-mono"
                            autocomplete="off"
                            placeholder="Api-Key from Steadfast"
                        />
                        <p v-if="form.errors.api_key" class="mt-1 text-sm text-rose-600">{{ form.errors.api_key }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200" for="steadfast-secret-key">
                            Secret key
                        </label>
                        <InputText
                            id="steadfast-secret-key"
                            v-model="form.secret_key"
                            class="w-full font-mono"
                            autocomplete="off"
                            placeholder="Secret-Key from Steadfast"
                        />
                        <p v-if="form.errors.secret_key" class="mt-1 text-sm text-rose-600">{{ form.errors.secret_key }}</p>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            label="Add key"
                            icon="pi pi-plus"
                            class="!min-h-11 w-full sm:w-auto"
                            :loading="form.processing"
                        />
                    </div>
                </form>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineOptions({
    name: "FraudCheckSteadfastApi",
});

const props = defineProps({
    credential: {
        type: Object,
        default: () => ({
            saved: false,
            env_configured: false,
            accounts: [],
            merchant_key_count: 0,
        }),
    },
});

const page = usePage();
const removingId = ref<string | null>(null);
const testingId = ref<string | null>(null);
const savingId = ref<string | null>(null);
const importing = ref(false);
const copiedKey = ref("");
const rowError = ref("");
const rowErrorId = ref<string | null>(null);
const drafts = ref<Record<string, { label: string; api_key: string; secret_key: string }>>({});
const savedMessage = computed(() => {
    const flash = page.props.flash as { success?: string } | undefined;

    return flash?.success || "";
});

const accounts = computed(() => {
    const rows = props.credential.accounts;

    return Array.isArray(rows) ? rows : [];
});

const merchantKeyCount = computed(() => Number(props.credential.merchant_key_count) || 0);

watch(accounts, (rows) => {
    const next: Record<string, { label: string; api_key: string; secret_key: string }> = {};

    for (const account of rows) {
        next[account.id] = drafts.value[account.id] ?? {
            label: account.label || "",
            api_key: account.api_key || "",
            secret_key: account.secret_key || "",
        };
    }

    drafts.value = next;
}, { immediate: true });

const isDirty = (account: { id: string; label?: string; api_key?: string; secret_key?: string }) => {
    const draft = drafts.value[account.id];

    if (!draft) {
        return false;
    }

    return draft.label !== (account.label || "")
        || draft.api_key !== (account.api_key || "")
        || draft.secret_key !== (account.secret_key || "");
};

const saveAccount = (id: string) => {
    const draft = drafts.value[id];

    if (!draft) {
        return;
    }

    draft.label = draft.label.trim();
    draft.api_key = draft.api_key.trim();
    draft.secret_key = draft.secret_key.trim();

    savingId.value = id;
    rowErrorId.value = id;
    rowError.value = "";

    router.put(route("frauds.steadfastApi.updateAccount", id), {
        label: draft.label.trim(),
        api_key: draft.api_key.trim(),
        secret_key: draft.secret_key.trim(),
    }, {
        preserveScroll: true,
        onError: (errors) => {
            rowError.value = String(errors.api_key || errors.secret_key || errors.label || "Could not save this key.");
        },
        onFinish: () => {
            savingId.value = null;
        },
    });
};

const form = useForm({
    label: "",
    api_key: "",
    secret_key: "",
});

const save = () => {
    form.post(route("frauds.steadfastApi.store"), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const importMerchants = () => {
    importing.value = true;

    router.post(route("frauds.steadfastApi.importMerchants"), {}, {
        preserveScroll: true,
        onFinish: () => {
            importing.value = false;
        },
    });
};

const testAccount = (id: string) => {
    testingId.value = id;

    router.post(route("frauds.steadfastApi.test", id), {}, {
        preserveScroll: true,
        onFinish: () => {
            testingId.value = null;
        },
    });
};

const removeAccount = (id: string) => {
    removingId.value = id;

    router.delete(route("frauds.steadfastApi.destroy", id), {
        preserveScroll: true,
        onFinish: () => {
            removingId.value = null;
        },
    });
};

const copyValue = async (value: string, key: string) => {
    try {
        await navigator.clipboard.writeText(value);
        copiedKey.value = key;
    } catch {
        copiedKey.value = "";
    }
};
</script>
