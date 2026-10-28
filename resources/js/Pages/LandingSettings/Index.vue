<template>
    <AuthenticatedLayout title="Settings">
        <div class="space-y-5">
            <PageHeader
                title="Settings"
                description="Download links, payment numbers, support contacts, OpenAI, and expiry SMS"
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
                                Used for AI blog post writing, image generation, and the store landing page generator. Leave blank to clear and fall back to .env.
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
                                Light model (keywords / hooks)
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Cheap model for keyword suggestions and title hooks. Default: gpt-4o-mini.
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
                                placeholder="Select a light model"
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
                                for="openai_blog_planning_model"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                Planning model (research / outline / review)
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Research, outline, competitor analysis, step review, and image vision checks.
                                Prefer <span class="font-medium">gpt-4.1-mini</span> or <span class="font-medium">gpt-5-mini</span>.
                                <span
                                    v-if="settings.openai_blog_planning_model_source !== 'none'"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    active: {{ settings.openai_blog_planning_model_source }}
                                </span>
                            </p>
                            <Select
                                id="openai_blog_planning_model"
                                v-model="form.openai_blog_planning_model"
                                :options="blogPlanningModelOptions"
                                class="mt-2 w-full"
                                placeholder="Select a planning model"
                            />
                            <p
                                v-if="form.errors.openai_blog_planning_model"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ form.errors.openai_blog_planning_model }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="openai_blog_writing_model"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                Writing model (article draft)
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Long-form article drafts and Fix SEO body expands only.
                                Best for your quality bar: <span class="font-medium">gpt-4.1</span> (default) or <span class="font-medium">gpt-5</span>.
                                <span
                                    v-if="settings.openai_blog_writing_model_source !== 'none'"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    active: {{ settings.openai_blog_writing_model_source }}
                                </span>
                            </p>
                            <Select
                                id="openai_blog_writing_model"
                                v-model="form.openai_blog_writing_model"
                                :options="blogWritingModelOptions"
                                class="mt-2 w-full"
                                placeholder="Select a writing model"
                            />
                            <p
                                v-if="form.errors.openai_blog_writing_model"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ form.errors.openai_blog_writing_model }}
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

                        <div class="border-t border-gray-100 pt-5 dark:border-gray-800">
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">
                                Store landing page generator
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Writes the full landing page from the WEL Elementor widget (Config → AI content). Stores never enter a key; the hub uses the key above.
                            </p>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <label
                                    for="openai_landing_model"
                                    class="text-sm font-semibold text-gray-800 dark:text-white/90"
                                >
                                    Landing page model
                                </label>
                                <Button
                                    type="button"
                                    label="Refresh list"
                                    icon="pi pi-refresh"
                                    size="small"
                                    text
                                    :loading="refreshingModels"
                                    :disabled="refreshingModels"
                                    @click="refreshModels"
                                />
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ landingModelNote }}
                                <span
                                    v-if="settings.openai_landing_model_source !== 'none'"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    active: {{ settings.openai_landing_model_source }}
                                </span>
                            </p>
                            <Select
                                id="openai_landing_model"
                                v-model="form.openai_landing_model"
                                :options="landingModelOptions"
                                option-label="label"
                                option-value="value"
                                class="mt-2 w-full"
                            />
                            <p
                                v-if="form.errors.openai_landing_model"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ form.errors.openai_landing_model }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="landing_ai_daily_limit"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                Generations per store per day
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Each Generate click counts once per store license. Failed runs do not count.
                                <span
                                    v-if="settings.landing_ai_daily_limit_source !== 'none'"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    active: {{ settings.landing_ai_daily_limit_source }}
                                </span>
                            </p>
                            <InputText
                                id="landing_ai_daily_limit"
                                v-model="form.landing_ai_daily_limit"
                                type="number"
                                min="1"
                                max="500"
                                step="1"
                                class="mt-2 w-full"
                                placeholder="20"
                            />
                            <p
                                v-if="form.errors.landing_ai_daily_limit"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ form.errors.landing_ai_daily_limit }}
                            </p>
                        </div>

                        <div class="border-t border-gray-100 pt-5 dark:border-gray-800">
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">
                                Return desk AI
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Reads SteadFast hold parcels in the WEL plugin (Return Requests → Decide) and says resend, cancel, or check yourself, with a call plan and follow-up date. Applies to every store with the AI Return Decide plan feature. Stores only add their own memory rules; they never see the key or these settings.
                            </p>
                        </div>

                        <div>
                            <label
                                for="return_ai_mode"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                Mode
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ returnAiModeHint }}
                            </p>
                            <Select
                                id="return_ai_mode"
                                v-model="form.return_ai.mode"
                                :options="returnAiModeOptions"
                                option-label="label"
                                option-value="value"
                                class="mt-2 w-full"
                            />
                            <p
                                v-if="returnAiError('mode')"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ returnAiError("mode") }}
                            </p>
                        </div>

                        <div
                            v-if="form.return_ai.mode === 'auto'"
                            class="space-y-3 rounded-xl border border-gray-100 p-3 dark:border-gray-800"
                        >
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                The AI acts only when confidence is at or above the minimum, the quoted lines exist in the parcel notes, no suspicion flag is raised, and a second check agrees. Anything else goes to the store's "needs review" list.
                            </p>
                            <div
                                v-for="action in returnAiActions"
                                :key="action.key"
                                class="flex items-start justify-between gap-4"
                            >
                                <div class="min-w-0">
                                    <label
                                        :for="`return_ai_${action.key}`"
                                        class="text-sm font-medium text-gray-800 dark:text-white/90"
                                    >
                                        {{ action.label }}
                                    </label>
                                    <p
                                        class="mt-0.5 text-xs"
                                        :class="action.danger ? 'text-rose-500' : 'text-gray-500 dark:text-gray-400'"
                                    >
                                        {{ action.hint }}
                                    </p>
                                </div>
                                <ToggleSwitch
                                    :id="`return_ai_${action.key}`"
                                    v-model="form.return_ai[action.key]"
                                />
                            </div>
                        </div>

                        <div>
                            <label
                                for="return_ai_model"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                Model
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ returnAiModelHint }}
                            </p>
                            <Select
                                id="return_ai_model"
                                v-model="form.return_ai.model"
                                :options="returnAiModelOptions"
                                option-label="label"
                                option-value="value"
                                class="mt-2 w-full"
                            />
                            <p
                                v-if="returnAiError('model')"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ returnAiError("model") }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div
                                v-for="field in returnAiNumbers"
                                :key="field.key"
                            >
                                <label
                                    :for="`return_ai_${field.key}`"
                                    class="text-sm font-semibold text-gray-800 dark:text-white/90"
                                >
                                    {{ field.label }}
                                </label>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ field.hint }}
                                </p>
                                <InputText
                                    :id="`return_ai_${field.key}`"
                                    v-model="form.return_ai[field.key]"
                                    type="number"
                                    :min="field.min"
                                    :max="field.max"
                                    step="1"
                                    class="mt-2 w-full"
                                />
                                <p
                                    v-if="returnAiError(field.key)"
                                    class="mt-1 text-xs text-rose-500"
                                >
                                    {{ returnAiError(field.key) }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <label
                                    for="return_ai_background_review"
                                    class="text-sm font-semibold text-gray-800 dark:text-white/90"
                                >
                                    Review in the background
                                </label>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    After each SteadFast sync the store reads new or changed hold parcels ahead of time, so Decide opens with the answer ready. An unchanged parcel is never read twice.
                                </p>
                            </div>
                            <ToggleSwitch
                                id="return_ai_background_review"
                                v-model="form.return_ai.background_review"
                            />
                        </div>
                    </template>

                    <template v-else-if="activeTab === 'notifications'">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <label
                                    for="sms_expiry"
                                    class="text-sm font-semibold text-gray-800 dark:text-white/90"
                                >
                                    Expiry SMS to merchants
                                </label>
                                <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    Default ON. Sends plan/license expiry SMS at 7, 3, 1, and 0 days. No website links — today/expired messages include 01770989591.
                                    <span
                                        class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                    >
                                        active: {{ settings.sms_expiry_source ?? "env" }}
                                    </span>
                                </p>
                            </div>
                            <ToggleSwitch
                                id="sms_expiry"
                                v-model="form.sms_expiry"
                            />
                        </div>
                        <p
                            v-if="form.errors.sms_expiry"
                            class="text-xs text-rose-500"
                        >
                            {{ form.errors.sms_expiry }}
                        </p>
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
import { router, useForm } from "@inertiajs/vue3";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import ToggleSwitch from "primevue/toggleswitch";
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
    openai_blog_planning_model: string | null;
    openai_blog_writing_model: string | null;
    openai_image_model: string | null;
    openai_api_key_source: string;
    openai_blog_model_source: string;
    openai_blog_planning_model_source: string;
    openai_blog_writing_model_source: string;
    openai_image_model_source: string;
    blog_ai_daily_token_cap: number;
    blog_ai_daily_token_cap_source: string;
    openai_landing_model: string | null;
    openai_landing_model_source: string;
    landing_ai_daily_limit: number;
    landing_ai_daily_limit_source: string;
    landing_model_options?: { value: string; label: string }[];
    landing_model_best?: string;
    landing_model_list_source?: string;
    landing_model_list_error?: string | null;
    landing_model_list_fetched_at?: string | null;
    blog_model_options: string[];
    blog_planning_model_options: string[];
    blog_writing_model_options: string[];
    image_model_options: string[];
    sms_expiry?: boolean;
    sms_expiry_source?: string;
    return_ai?: ReturnAiSettings;
    return_ai_model_options?: { value: string; label: string; hint: string; recommended: boolean }[];
};

type ReturnAiMode = "off" | "suggest" | "auto";

type ReturnAiSettings = {
    mode: ReturnAiMode;
    auto_note: boolean;
    auto_resend: boolean;
    auto_cancel: boolean;
    model: string;
    min_confidence: number;
    daily_auto_limit: number;
    daily_review_limit: number;
    background_review: boolean;
};

type ReturnAiToggle = "auto_note" | "auto_resend" | "auto_cancel";
type ReturnAiNumber = "min_confidence" | "daily_auto_limit" | "daily_review_limit";

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
    openai_blog_planning_model: string;
    openai_blog_writing_model: string;
    openai_image_model: string;
    blog_ai_daily_token_cap: string;
    openai_landing_model: string;
    landing_ai_daily_limit: string;
    sms_expiry: boolean;
};

type SettingsField = {
    key: keyof FormFields;
    sourceKey: keyof LandingSettings;
    label: string;
    hint: string;
    placeholder: string;
};

type TabValue = "downloads" | "payments" | "contact" | "ai" | "notifications";

const props = defineProps<{
    settings: LandingSettings;
}>();

const tabOptions: { label: string; value: TabValue; icon: IconName }[] = [
    { label: "Downloads", value: "downloads", icon: "PhDownloadSimple" },
    { label: "Payments", value: "payments", icon: "PhCreditCard" },
    { label: "Contact", value: "contact", icon: "PhPhone" },
    { label: "Notifications", value: "notifications", icon: "PhBellRinging" },
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
const blogPlanningModelOptions = props.settings.blog_planning_model_options ?? blogModelOptions;
const blogWritingModelOptions = props.settings.blog_writing_model_options ?? blogModelOptions;
const imageModelOptions = props.settings.image_model_options ?? [];
const defaultTokenCap = props.settings.blog_ai_daily_token_cap ?? 400000;
// PrimeVue Select treats "" as empty, so Auto uses a sentinel that is cleared on save.
const AUTO_MODEL = "auto";

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
    openai_blog_planning_model: props.settings.openai_blog_planning_model ?? "gpt-4.1-mini",
    openai_blog_writing_model: props.settings.openai_blog_writing_model ?? "gpt-4.1",
    openai_image_model: props.settings.openai_image_model ?? "gpt-image-1",
    // Always include the effective cap so saving other tabs does not clear a DB override.
    blog_ai_daily_token_cap: String(props.settings.blog_ai_daily_token_cap ?? 400000),
    openai_landing_model: props.settings.openai_landing_model || AUTO_MODEL,
    landing_ai_daily_limit: String(props.settings.landing_ai_daily_limit ?? 20),
    sms_expiry: props.settings.sms_expiry !== false,
    return_ai: {
        mode: props.settings.return_ai?.mode ?? "suggest",
        auto_note: props.settings.return_ai?.auto_note ?? false,
        auto_resend: props.settings.return_ai?.auto_resend ?? false,
        auto_cancel: props.settings.return_ai?.auto_cancel ?? false,
        model: props.settings.return_ai?.model ?? "gpt-4.1-mini",
        min_confidence: props.settings.return_ai?.min_confidence ?? 95,
        daily_auto_limit: props.settings.return_ai?.daily_auto_limit ?? 30,
        daily_review_limit: props.settings.return_ai?.daily_review_limit ?? 300,
        background_review: props.settings.return_ai?.background_review ?? true,
    } as ReturnAiSettings,
});

const returnAiModeOptions: { value: ReturnAiMode; label: string; hint: string }[] = [
    { value: "off", label: "Off", hint: "No AI review at all. Stores decide by hand. No OpenAI cost." },
    {
        value: "suggest",
        label: "Suggest only (recommended)",
        hint: "The AI reads each case and tells the merchant what to do and whom to call. It never acts on its own.",
    },
    {
        value: "auto",
        label: "Auto",
        hint: "Suggest, and run the ticked actions on its own when every safety check passes.",
    },
];

const returnAiActions: { key: ReturnAiToggle; label: string; hint: string; danger?: boolean }[] = [
    {
        key: "auto_note",
        label: "Auto note to rider",
        hint: "Writes a short instruction on the parcel. Skipped when the merchant already wrote their own note.",
    },
    {
        key: "auto_resend",
        label: "Auto resend",
        hint: "Asks SteadFast to try again. Safest action; it keeps the sale.",
    },
    {
        key: "auto_cancel",
        label: "Auto cancel",
        hint: "Cancels the parcel for good. Also needs the merchant's own call proving the customer refused. Leave off unless you are sure.",
        danger: true,
    },
];

const returnAiNumbers: { key: ReturnAiNumber; label: string; hint: string; min: number; max: number }[] = [
    { key: "min_confidence", label: "Minimum confidence (%)", hint: "Auto actions only. 95–99.", min: 95, max: 99 },
    { key: "daily_auto_limit", label: "Auto actions per store per day", hint: "Stops a runaway day. 1–200.", min: 1, max: 200 },
    { key: "daily_review_limit", label: "AI reviews per store per day", hint: "Caps OpenAI cost. Each review and each second check counts.", min: 10, max: 5000 },
];

const returnAiModelOptions = computed(() => props.settings.return_ai_model_options ?? []);

const returnAiModeHint = computed(
    () => returnAiModeOptions.find((option) => option.value === form.return_ai.mode)?.hint ?? "",
);

const returnAiModelHint = computed(
    () => returnAiModelOptions.value.find((option) => option.value === form.return_ai.model)?.hint ?? "",
);

const returnAiError = (field: string): string | undefined =>
    (form.errors as Record<string, string | undefined>)[`return_ai.${field}`];

const refreshingModels = ref(false);

const landingModelOptions = computed(() => {
    const options = (props.settings.landing_model_options ?? []).map((option) =>
        option.value === "" ? { ...option, value: AUTO_MODEL } : option,
    );
    const current = form.openai_landing_model;

    if (current && !options.some((option) => option.value === current)) {
        options.push({ value: current, label: `${current} (saved)` });
    }

    return options;
});

const landingModelNote = computed(() => {
    if (props.settings.landing_model_list_error) {
        return props.settings.landing_model_list_error;
    }

    if (props.settings.landing_model_list_source === "live") {
        return "Latest text models from your OpenAI account. Auto always uses the best one.";
    }

    return "Built-in list. Auto always uses the best one.";
});

const refreshModels = () => {
    router.reload({
        only: ["settings"],
        data: { refresh_models: 1 },
        preserveScroll: true,
        onStart: () => {
            refreshingModels.value = true;
        },
        onFinish: () => {
            refreshingModels.value = false;
        },
    });
};

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
            description: "API key, models, and daily limits for blog posts, store landing pages, and the return desk.",
        };
    }

    if (activeTab.value === "notifications") {
        return {
            title: "Merchant notifications",
            description: "Control expiry SMS sent to merchants. Default is enabled.",
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

    if (field.startsWith("return_ai.") || ["openai_api_key", "openai_blog_model", "openai_blog_planning_model", "openai_blog_writing_model", "openai_image_model", "blog_ai_daily_token_cap", "openai_landing_model", "landing_ai_daily_limit"].includes(field)) {
        return "ai";
    }

    if (field === "sms_expiry") {
        return "notifications";
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
    form
        .transform((data) => ({
            ...data,
            openai_landing_model: data.openai_landing_model === AUTO_MODEL ? "" : data.openai_landing_model,
        }))
        .put(route("landingSettings.update"), {
            preserveScroll: true,
        });
};
</script>
