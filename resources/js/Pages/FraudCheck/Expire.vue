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
                title="Steadfast CURL Configuration"
                description="Paste the CURL command used for Steadfast fraud API requests"
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
