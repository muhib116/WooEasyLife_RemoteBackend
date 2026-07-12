<template>
    <AuthenticatedLayout title="Landing Settings">
        <div class="space-y-5">
            <PageHeader
                title="Landing Settings"
                description="Download links, payment numbers, and support contacts for the public landing page"
                icon="PhDeviceMobile"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
            />

            <div
                class="box-bg box-border rounded-2xl border p-2 shadow-sm dark:border-gray-700"
            >
                <div
                    class="flex gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1 dark:bg-slate-900/60"
                >
                    <button
                        v-for="tab in tabOptions"
                        :key="tab.value"
                        type="button"
                        class="whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition-all"
                        :class="
                            activeTab === tab.value
                                ? 'bg-white text-primary-600 shadow-sm dark:bg-slate-800 dark:text-primary-400'
                                : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'
                        "
                        @click="activeTab = tab.value"
                    >
                        <span class="flex items-center gap-2">
                            <Icon :name="tab.icon" class="text-base" />
                            {{ tab.label }}
                        </span>
                    </button>
                </div>
            </div>

            <PageCard :title="activeCard.title" :description="activeCard.description">
                <form class="space-y-5" @submit.prevent="submit">
                    <div
                        v-for="field in activeFields"
                        :key="field.key"
                    >
                        <label
                            :for="field.key"
                            class="text-sm font-semibold text-gray-800 dark:text-white/90"
                        >
                            {{ field.label }}
                        </label>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ field.hint }}
                            <span
                                v-if="sourceOf(field) !== 'none'"
                                class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            >
                                active: {{ sourceOf(field) }}
                            </span>
                        </p>
                        <InputText
                            :id="field.key"
                            v-model="form[field.key]"
                            class="mt-2 w-full"
                            :placeholder="field.placeholder"
                        />
                        <p
                            v-if="form.errors[field.key]"
                            class="mt-1 text-xs text-rose-500"
                        >
                            {{ form.errors[field.key] }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            label="Save"
                            icon="pi pi-check"
                            :loading="form.processing"
                            :disabled="form.processing"
                        />
                    </div>
                </form>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import { Icon } from "@/plugins";
import type { IconName } from "@/types";
import { useForm } from "@inertiajs/vue3";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import { computed, ref, watch } from "vue";

type LandingSettings = {
    app_download_url: string | null;
    play_store_url: string | null;
    plugin_download_url: string | null;
    app_download_url_source: string;
    play_store_url_source: string;
    plugin_download_url_source: string;
    bkash_number: string | null;
    rocket_number: string | null;
    nagad_number: string | null;
    admin_whatsapp: string | null;
    admin_email: string | null;
    admin_phone: string | null;
    bkash_number_source: string;
    rocket_number_source: string;
    nagad_number_source: string;
    admin_whatsapp_source: string;
    admin_email_source: string;
    admin_phone_source: string;
};

type FormFields = {
    app_download_url: string;
    play_store_url: string;
    plugin_download_url: string;
    bkash_number: string;
    rocket_number: string;
    nagad_number: string;
    admin_whatsapp: string;
    admin_email: string;
    admin_phone: string;
};

type SettingsField = {
    key: keyof FormFields;
    sourceKey: keyof LandingSettings;
    label: string;
    hint: string;
    placeholder: string;
};

type TabValue = "downloads" | "payments" | "contact";

const props = defineProps<{
    settings: LandingSettings;
}>();

const tabOptions: { label: string; value: TabValue; icon: IconName }[] = [
    { label: "Downloads", value: "downloads", icon: "PhDownloadSimple" },
    { label: "Payments", value: "payments", icon: "PhCreditCard" },
    { label: "Contact", value: "contact", icon: "PhPhone" },
];

const activeTab = ref<TabValue>("downloads");

const downloadFields: SettingsField[] = [
    {
        key: "app_download_url",
        sourceKey: "app_download_url_source",
        label: "Android APK download URL",
        hint: "Direct APK / CDN link used by the APK download button.",
        placeholder: "https://example.com/woo-easy-life.apk",
    },
    {
        key: "play_store_url",
        sourceKey: "play_store_url_source",
        label: "Google Play Store URL",
        hint: "Optional Play Store listing link.",
        placeholder: "https://play.google.com/store/apps/details?id=...",
    },
    {
        key: "plugin_download_url",
        sourceKey: "plugin_download_url_source",
        label: "WooCommerce plugin download URL",
        hint: "Optional override. If blank, uses /download-plugins when a plugin ZIP is published.",
        placeholder: "https://yoursite.com/download-plugins",
    },
];

const paymentFields: SettingsField[] = [
    {
        key: "bkash_number",
        sourceKey: "bkash_number_source",
        label: "bKash number",
        hint: "Default from LANDING_BKASH_NUMBER / SUBSCRIPTION_PAYMENT_BKASH.",
        placeholder: "01XXXXXXXXX",
    },
    {
        key: "rocket_number",
        sourceKey: "rocket_number_source",
        label: "Rocket number",
        hint: "Default from LANDING_ROCKET_NUMBER / SUBSCRIPTION_PAYMENT_ROCKET.",
        placeholder: "01XXXXXXXXX",
    },
    {
        key: "nagad_number",
        sourceKey: "nagad_number_source",
        label: "Nagad number",
        hint: "Default from LANDING_NAGAD_NUMBER.",
        placeholder: "01XXXXXXXXX",
    },
];

const contactFields: SettingsField[] = [
    {
        key: "admin_whatsapp",
        sourceKey: "admin_whatsapp_source",
        label: "Admin WhatsApp number",
        hint: "Default from LANDING_WHATSAPP_PHONE. Used for WhatsApp buttons.",
        placeholder: "8801XXXXXXXXX",
    },
    {
        key: "admin_email",
        sourceKey: "admin_email_source",
        label: "Admin email",
        hint: "Default from LANDING_ADMIN_EMAIL / MAIL_FROM_ADDRESS.",
        placeholder: "support@example.com",
    },
    {
        key: "admin_phone",
        sourceKey: "admin_phone_source",
        label: "Admin phone number",
        hint: "Default from LANDING_HELPLINE_PHONE. Shown as helpline.",
        placeholder: "8801XXXXXXXXX",
    },
];

const form = useForm({
    app_download_url: props.settings.app_download_url ?? "",
    play_store_url: props.settings.play_store_url ?? "",
    plugin_download_url:
        props.settings.plugin_download_url_source === "auto"
            ? ""
            : (props.settings.plugin_download_url ?? ""),
    bkash_number: props.settings.bkash_number ?? "",
    rocket_number: props.settings.rocket_number ?? "",
    nagad_number: props.settings.nagad_number ?? "",
    admin_whatsapp: props.settings.admin_whatsapp ?? "",
    admin_email: props.settings.admin_email ?? "",
    admin_phone: props.settings.admin_phone ?? "",
});

const activeFields = computed(() => {
    if (activeTab.value === "payments") {
        return paymentFields;
    }

    if (activeTab.value === "contact") {
        return contactFields;
    }

    return downloadFields;
});

const activeCard = computed(() => {
    if (activeTab.value === "payments") {
        return {
            title: "Payment numbers",
            description: "Shown on landing / subscription payment guides. Leave blank to use .env defaults.",
        };
    }

    if (activeTab.value === "contact") {
        return {
            title: "Admin / support contact",
            description: "Used for WhatsApp CTA, helpline, and footer on the landing page.",
        };
    }

    return {
        title: "Download links",
        description: "Saved values override defaults. Leave blank to clear the database value and fall back to env / auto plugin URL.",
    };
});

const sourceOf = (field: SettingsField) => String(props.settings[field.sourceKey] ?? "none");

const tabForError = (field: string): TabValue | null => {
    if (["app_download_url", "play_store_url", "plugin_download_url"].includes(field)) {
        return "downloads";
    }

    if (["bkash_number", "rocket_number", "nagad_number"].includes(field)) {
        return "payments";
    }

    if (["admin_whatsapp", "admin_email", "admin_phone"].includes(field)) {
        return "contact";
    }

    return null;
};

watch(
    () => form.errors,
    (errors) => {
        const first = Object.keys(errors)[0];

        if (!first) {
            return;
        }

        const tab = tabForError(first);

        if (tab) {
            activeTab.value = tab;
        }
    },
);

const submit = () => {
    form.put(route("landingSettings.update"), {
        preserveScroll: true,
    });
};
</script>
