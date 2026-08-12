<template>
    <AuthenticatedLayout title="Marketing">
        <div class="space-y-5">
            <PageHeader
                title="Marketing"
                description="Tracking pixels, verification tags, and custom scripts for public marketing pages"
                icon="PhMegaphone"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
            />

            <form class="space-y-5" @submit.prevent="submit">
                <PageCard
                    title="Meta Pixel"
                    description="Loads on public marketing pages (landing, pricing, SEO pages, blog). Leave blank to disable."
                >
                    <div>
                        <label
                            for="meta_pixel_id"
                            class="text-sm font-semibold text-gray-800 dark:text-white/90"
                        >
                            Meta Pixel ID
                        </label>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            From Meta Events Manager → your Pixel → Settings.
                            <span
                                v-if="settings.meta_pixel_id_source !== 'none'"
                                class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            >
                                active: {{ settings.meta_pixel_id_source }}
                            </span>
                        </p>
                        <InputText
                            id="meta_pixel_id"
                            v-model="form.meta_pixel_id"
                            class="mt-2 w-full"
                            placeholder="806373635894978"
                            inputmode="numeric"
                            autocomplete="off"
                        />
                        <p
                            v-if="form.errors.meta_pixel_id"
                            class="mt-1 text-xs text-rose-500"
                        >
                            {{ form.errors.meta_pixel_id }}
                        </p>
                    </div>

                    <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-900/50">
                        <p class="font-semibold text-gray-800 dark:text-white/90">
                            Events fired for Ads Manager
                        </p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-gray-600 dark:text-gray-400">
                            <li><strong>PageView</strong> — every marketing page (incl. SPA navigations)</li>
                            <li><strong>ViewContent</strong> — page / plan / blog article views</li>
                            <li><strong>ScrollDepth</strong> (custom) — 25 / 50 / 75 / 90%</li>
                            <li><strong>CtaClick</strong> (custom) — header, hero, SEO, plan, sticky CTAs</li>
                            <li><strong>Search</strong> — successful free fraud phone check</li>
                            <li><strong>Contact</strong> — WhatsApp / phone / email clicks</li>
                            <li><strong>InitiateCheckout</strong> — subscription wizard opens</li>
                            <li><strong>AddPaymentInfo</strong> — paid plan payment details entered</li>
                            <li><strong>Lead</strong> — successful inquiry submit only (not on failed attempts)</li>
                            <li><strong>StartTrial</strong> — free trial submitted (ad optimization event)</li>
                            <li><strong>Subscribe</strong> — paid inquiry submitted (payment still pending review)</li>
                            <li><strong>Purchase</strong> — not fired on inquiry (avoids inflated ROAS)</li>
                            <li><strong>WizardStep</strong> / <strong>DownloadUnlocked</strong> (custom) — funnel detail</li>
                        </ul>
                    </div>
                </PageCard>

                <PageCard
                    title="Header & footer scripts"
                    description="Raw HTML injected on every public page (verification metas, extra pixels, chat widgets). Admin-only — treat as trusted code."
                >
                    <div class="space-y-5">
                        <div>
                            <label
                                for="header_scripts"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                Header scripts
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Injected near the end of
                                <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">&lt;head&gt;</code>
                                — Pinterest / Google Search Console / Bing verification,
                                <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">&lt;meta&gt;</code>
                                tags, or head
                                <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">&lt;script&gt;</code>.
                                <span
                                    v-if="settings.header_scripts_source !== 'none'"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    active: {{ settings.header_scripts_source }}
                                </span>
                            </p>
                            <Textarea
                                id="header_scripts"
                                v-model="form.header_scripts"
                                class="mt-2 w-full font-mono text-xs"
                                rows="6"
                                autoResize
                                placeholder='<meta name="p:domain_verify" content="18497601a62b9cb9e1b1b32fb7d57ae2"/>'
                            />
                            <p
                                v-if="form.errors.header_scripts"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ form.errors.header_scripts }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="footer_scripts"
                                class="text-sm font-semibold text-gray-800 dark:text-white/90"
                            >
                                Footer scripts
                            </label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Injected just before
                                <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">&lt;/body&gt;</code>
                                — chat widgets, deferred trackers, etc.
                                <span
                                    v-if="settings.footer_scripts_source !== 'none'"
                                    class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    active: {{ settings.footer_scripts_source }}
                                </span>
                            </p>
                            <Textarea
                                id="footer_scripts"
                                v-model="form.footer_scripts"
                                class="mt-2 w-full font-mono text-xs"
                                rows="6"
                                autoResize
                                placeholder="<script>/* footer snippet */</script>"
                            />
                            <p
                                v-if="form.errors.footer_scripts"
                                class="mt-1 text-xs text-rose-500"
                            >
                                {{ form.errors.footer_scripts }}
                            </p>
                        </div>
                    </div>
                </PageCard>

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
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import { useForm } from "@inertiajs/vue3";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Textarea from "primevue/textarea";

const props = defineProps<{
    settings: {
        meta_pixel_id: string | null;
        meta_pixel_id_source: string;
        header_scripts: string | null;
        header_scripts_source: string;
        footer_scripts: string | null;
        footer_scripts_source: string;
    };
}>();

const form = useForm({
    meta_pixel_id: props.settings.meta_pixel_id ?? "",
    header_scripts: props.settings.header_scripts ?? "",
    footer_scripts: props.settings.footer_scripts ?? "",
});

const submit = () => {
    form.put(route("marketingSettings.update"), {
        preserveScroll: true,
    });
};
</script>
