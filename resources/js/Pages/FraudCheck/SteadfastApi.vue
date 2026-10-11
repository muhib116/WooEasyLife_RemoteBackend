<template>
    <AuthenticatedLayout title="Steadfast API">
        <div class="space-y-5">
            <PageHeader
                title="Steadfast API"
                description="API key and secret for the public page and admin Phone Check. Each store's plugin still uses that store's own Steadfast keys."
                icon="PhKey"
                icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                icon-class="text-amber-600 dark:text-amber-400"
            />

            <PageCard
                title="Score API keys"
                description="From the Steadfast merchant dashboard: API guide. Keys are encrypted. The secret is not shown again after you save it."
            >
                <p
                    v-if="savedMessage"
                    class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                >
                    {{ savedMessage }}
                </p>

                <div
                    class="mb-4 rounded-xl border px-4 py-3 text-sm"
                    :class="credential.saved
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                        : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'"
                >
                    <span v-if="credential.saved">Using keys saved in this panel.</span>
                    <span v-else-if="credential.env_configured">No keys saved here. The public check is using the .env pair.</span>
                    <span v-else>No keys saved here, and .env has no Steadfast API pair. Public and admin checks cannot call the score API yet.</span>
                </div>

                <form class="space-y-4" @submit.prevent="save">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200" for="steadfast-api-key">
                            API key
                        </label>
                        <InputText
                            id="steadfast-api-key"
                            v-model="form.api_key"
                            class="w-full"
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
                            type="password"
                            class="w-full"
                            autocomplete="new-password"
                            :placeholder="credential.has_secret ? 'Saved. Leave blank to keep the current secret.' : 'Secret-Key from Steadfast'"
                        />
                        <p v-if="form.errors.secret_key" class="mt-1 text-sm text-rose-600">{{ form.errors.secret_key }}</p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Button
                            v-if="credential.saved"
                            type="button"
                            label="Remove saved keys"
                            severity="secondary"
                            outlined
                            class="!min-h-11 w-full sm:w-auto"
                            :loading="clearing"
                            @click="removeSaved"
                        />
                        <Button
                            type="submit"
                            label="Save keys"
                            icon="pi pi-save"
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
import { computed, ref } from "vue";
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
            api_key: "",
            has_secret: false,
            env_configured: false,
        }),
    },
});

const page = usePage();
const clearing = ref(false);
const savedMessage = computed(() => {
    const flash = page.props.flash as { success?: string } | undefined;

    return flash?.success || "";
});

const form = useForm({
    api_key: props.credential.api_key || "",
    secret_key: "",
});

const save = () => {
    form.put(route("frauds.steadfastApi.update"), {
        preserveScroll: true,
        onSuccess: () => {
            form.secret_key = "";
        },
    });
};

const removeSaved = () => {
    clearing.value = true;

    router.put(route("frauds.steadfastApi.update"), { clear: true }, {
        preserveScroll: true,
        onFinish: () => {
            clearing.value = false;
            form.api_key = "";
            form.secret_key = "";
        },
    });
};
</script>
