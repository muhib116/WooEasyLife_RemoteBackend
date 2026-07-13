<template>
    <AuthenticatedLayout title="Marketing">
        <div class="space-y-5">
            <PageHeader
                title="Marketing"
                description="Tracking pixels and ads tools for the public landing and marketing pages"
                icon="PhMegaphone"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
            />

            <PageCard
                title="Meta Pixel"
                description="Loads on public marketing pages (landing, pricing, SEO pages, blog). Leave blank to disable."
            >
                <form class="space-y-5" @submit.prevent="submit">
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

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-900/50">
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
import { useForm } from "@inertiajs/vue3";
import Button from "primevue/button";
import InputText from "primevue/inputtext";

const props = defineProps<{
    settings: {
        meta_pixel_id: string | null;
        meta_pixel_id_source: string;
    };
}>();

const form = useForm({
    meta_pixel_id: props.settings.meta_pixel_id ?? "",
});

const submit = () => {
    form.put(route("marketingSettings.update"), {
        preserveScroll: true,
    });
};
</script>
