<template>
    <AuthenticatedLayout title="Settings">
        <div class="space-y-5">
            <PageHeader
                title="Settings"
                description="Download links, payment numbers, support contacts, and OpenAI settings"
                icon="PhGearSix"
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
                    <template v-if="activeTab === 'ai'">
                        <div>
                            <label
                                for="openai_api_key"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                OpenAI API key
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Used for AI blog post writing and image generation. Leave blank to clear and fall back to .env.
                                <span
                                    v-if="settings.openai_api_key_source !== 'none'"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    active: {{ settings.openai_api_key_source }}
                                </span>
                            </p>
                            <InputText
                                id="openai_api_key"
                                v-model="form.openai_api_key"
                                type="password"
                                class="mt-2 w-full"
                                placeholder="sk-..."
                                autocomplete="off"
                            />
                            <p
                                v-if="form.errors.openai_api_key"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ form.errors.openai_api_key }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="openai_blog_model"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                Blog post model
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Chat model used to draft blog titles, body, and SEO fields.
                                <span
                                    v-if="settings.openai_blog_model_source !== 'none'"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    active: {{ settings.openai_blog_model_source }}
                                </span>
                            </p>
                            <Select
                                id="openai_blog_model"
                                v-model="form.openai_blog_model"
                                :options="blogModelOptions"
                                class="mt-2 w-full"
                                placeholder="Select a blog model"
                            />
                            <p
                                v-if="form.errors.openai_blog_model"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ form.errors.openai_blog_model }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="openai_image_model"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                Image generation model
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Model used to generate featured / OG images for blog posts.
                                <span
                                    v-if="settings.openai_image_model_source !== 'none'"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    active: {{ settings.openai_image_model_source }}
                                </span>
                            </p>
                            <Select
                                id="openai_image_model"
                                v-model="form.openai_image_model"
                                :options="imageModelOptions"
                                class="mt-2 w-full"
                                placeholder="Select an image model"
                            />
                            <p
                                v-if="form.errors.openai_image_model"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ form.errors.openai_image_model }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="blog_ai_daily_token_cap"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                Daily AI token limit
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Max tokens per admin user per day for blog AI (writing + images). Leave blank to clear and fall back to .env / config default ({{ defaultTokenCap }}).
                                <span
                                    v-if="settings.blog_ai_daily_token_cap_source !== 'none'"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    active: {{ settings.blog_ai_daily_token_cap_source }}
                                </span>
                            </p>
                            <InputText
                                id="blog_ai_daily_token_cap"
                                v-model="form.blog_ai_daily_token_cap"
                                type="number"
                                min="1000"
                                max="10000000"
                                step="1000"
                                class="mt-2 w-full"
                                :placeholder="String(defaultTokenCap)"
                            />
                            <p
                                v-if="form.errors.blog_ai_daily_token_cap"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ form.errors.blog_ai_daily_token_cap }}
                            </p>
                        </div>
                    </template>

                    <template v-else>
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
                    </template>

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
import Select from "primevue/select";
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
    openai_api_key: string | null;
    openai_blog_model: string | null;
    openai_image_model: string | null;
    openai_api_key_source: string;
    openai_blog_model_source: string;
    openai_image_model_source: string;
    blog_ai_daily_token_cap: number;
    blog_ai_daily_token_cap_source: string;
    blog_model_options: string[];
    image_model_options: string[];
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
    openai_api_key: string;
    openai_blog_model: string;
    openai_image_model: string;
    blog_ai_daily_token_cap: string;
};

type SettingsField = {
    key: keyof FormFields;
    sourceKey: keyof LandingSettings;
    label: string;
    hint: string;
    placeholder: string;
};

type TabValue = "downloads" | "payments" | "contact" | "ai";

const props = defineProps<{
    settings: LandingSettings;
}>();

const tabOptions: { label: string; value: TabValue; icon: IconName }[] = [
    { label: "Downloads", value: "downloads", icon: "PhDownloadSimple" },
    { label: "Payments", value: "payments", icon: "PhCreditCard" },
    { label: "Contact", value: "contact", icon: "PhPhone" },
    { label: "AI", value: "ai", icon: "PhOpenAiLogo" },
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
        hint: "Saved in Landing Settings. Used on pricing payment instructions.",
        placeholder: "01XXXXXXXXX",
    },
    {
        key: "rocket_number",
        sourceKey: "rocket_number_source",
        label: "Rocket number",
        hint: "Saved in Landing Settings. Used on pricing payment instructions.",
        placeholder: "01XXXXXXXXX",
    },
    {
        key: "nagad_number",
        sourceKey: "nagad_number_source",
        label: "Nagad number",
        hint: "Saved in Landing Settings. Used on pricing payment instructions.",
        placeholder: "01XXXXXXXXX",
    },
];

const contactFields: SettingsField[] = [
    {
        key: "admin_whatsapp",
        sourceKey: "admin_whatsapp_source",
        label: "Admin WhatsApp number",
        hint: "Saved in Landing Settings. Used for WhatsApp buttons and support links.",
        placeholder: "8801XXXXXXXXX",
    },
    {
        key: "admin_email",
        sourceKey: "admin_email_source",
        label: "Admin email",
        hint: "Saved in Landing Settings. Used for footer contact and inquiry notifications.",
        placeholder: "support@example.com",
    },
    {
        key: "admin_phone",
        sourceKey: "admin_phone_source",
        label: "Admin phone number",
        hint: "Saved in Landing Settings. Shown as helpline in the footer.",
        placeholder: "8801XXXXXXXXX",
    },
];

const blogModelOptions = props.settings.blog_model_options ?? [];
const imageModelOptions = props.settings.image_model_options ?? [];
const defaultTokenCap = props.settings.blog_ai_daily_token_cap ?? 400000;

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
    openai_api_key: props.settings.openai_api_key ?? "",
    openai_blog_model: props.settings.openai_blog_model ?? "gpt-4o-mini",
    openai_image_model: props.settings.openai_image_model ?? "gpt-image-1",
    // Always include the effective cap so saving other tabs does not clear a DB override.
    blog_ai_daily_token_cap: String(props.settings.blog_ai_daily_token_cap ?? 400000),
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

    if (activeTab.value === "ai") {
        return {
            title: "OpenAI",
            description: "API key, models, and daily token limit for blog post generation.",
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

    if (["openai_api_key", "openai_blog_model", "openai_image_model", "blog_ai_daily_token_cap"].includes(field)) {
        return "ai";
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
