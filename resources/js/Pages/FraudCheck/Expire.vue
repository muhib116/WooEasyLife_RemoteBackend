<template>
    <AuthenticatedLayout title="Fraud Admin">
        <div class="space-y-5">
            <PageHeader
                title="Fraud Checker Admin"
                description="Manage Pathao token expiry and Steadfast CURL configuration"
                icon="PhGear"
            />

            <PageCard
                title="Pathao Access Token"
                description="Check remaining validity and renew when expired"
            >
                <div class="flex flex-wrap gap-3">
                    <Button
                        label="Check Time Left"
                        icon="pi pi-clock"
                        severity="secondary"
                        outlined
                        :loading="checkingExpiry"
                        @click="getExpire"
                    />
                    <Button
                        label="Renew Token"
                        icon="pi pi-refresh"
                        :loading="renewing"
                        @click="renewExpire"
                    />
                </div>
                <div
                    v-if="timeLeft !== null && timeLeft !== undefined"
                    class="mt-4 rounded-xl bg-slate-50 p-4 font-mono text-sm dark:bg-slate-900/40"
                >
                    <pre class="whitespace-pre-wrap text-gray-700 dark:text-gray-300">{{ formattedTimeLeft }}</pre>
                </div>
            </PageCard>

            <PageCard
                title="Courier Credentials"
                description="Fraud checks now use merchant login credentials from .env instead of saved CURL commands"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="(enabled, key) in credentialStatus || {}"
                        :key="key"
                        class="rounded-xl border px-4 py-3 text-sm"
                        :class="enabled
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                            : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'"
                    >
                        <span class="font-medium">{{ key }}</span>:
                        {{ enabled ? "Configured" : "Missing in .env" }}
                    </div>
                </div>
            </PageCard>

            <PageCard
                title="Steadfast CURL Configuration"
                description="Legacy fallback only. Prefer STEADFAST_USER and STEADFAST_PASSWORD in .env"
            >
                <Textarea
                    v-model="curlForm.curl_text"
                    class="min-h-[320px] w-full font-mono text-sm"
                    placeholder="Paste Steadfast CURL code here..."
                />
                <div class="mt-4 flex justify-end">
                    <Button
                        label="Save CURL"
                        icon="pi pi-save"
                        :loading="curlForm.processing"
                        @click="saveCurl"
                    />
                </div>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import axios from "axios";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineOptions({
    name: "FraudCheckExpire",
});

const props = defineProps({
    steadfast_curl: String,
    credentialStatus: Object,
});

const checkingExpiry = ref(false);
const renewing = ref(false);
const timeLeft = ref<any>(null);

const curlForm = useForm({
    curl_text: props.steadfast_curl || "",
});

const formattedTimeLeft = computed(() => {
    if (timeLeft.value === null || timeLeft.value === undefined) {
        return "";
    }

    if (typeof timeLeft.value === "object") {
        return JSON.stringify(timeLeft.value, null, 2);
    }

    return String(timeLeft.value);
});

const saveCurl = () => {
    curlForm.post(route("frauds.saveSteadfastCurl"));
};

const getExpire = async () => {
    checkingExpiry.value = true;
    try {
        const { data } = await axios.post(route("frauds.getExpire"));
        timeLeft.value = data;
    } finally {
        checkingExpiry.value = false;
    }
};

const renewExpire = async () => {
    renewing.value = true;
    try {
        const { data } = await axios.post(route("frauds.renewExpire"));
        timeLeft.value = data;
    } finally {
        renewing.value = false;
    }
};
</script>
