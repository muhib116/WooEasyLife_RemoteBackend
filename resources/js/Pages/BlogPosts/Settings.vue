<template>
    <AuthenticatedLayout title="Blog AI Settings">
        <div class="space-y-5">
            <PageHeader
                title="Blog AI Settings"
                description="Toggles for Smart Post, GSC preference, competitors, and landing reference — plus how to use the system."
                icon="PhGearSix"
                icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                icon-class="text-violet-600 dark:text-violet-400"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            label="Blog AI"
                            icon="pi pi-sparkles"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('blogPosts.ai'))"
                        />
                        <Button
                            label="SEO & Learning"
                            icon="pi pi-chart-line"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="router.visit(route('blogPosts.seo'))"
                        />
                    </div>
                </template>
            </PageHeader>

            <PageCard
                title="How to use Blog AI"
                description="Follow these steps for free real demand + consistent drafts."
            >
                <ol class="space-y-3">
                    <li
                        v-for="(step, idx) in how_to"
                        :key="idx"
                        class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-600"
                    >
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ step.title }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            {{ step.body }}
                        </p>
                    </li>
                </ol>
                <ul
                    v-if="ops_notes?.length"
                    class="mt-4 list-disc space-y-1 pl-5 text-xs text-slate-500 dark:text-slate-400"
                >
                    <li
                        v-for="(note, idx) in ops_notes"
                        :key="idx"
                    >
                        {{ note }}
                    </li>
                </ul>
            </PageCard>

            <PageCard
                title="Defaults (already ON)"
                description="Safe defaults are set. Change only if you need to turn something off."
            >
                <form
                    class="space-y-5"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="field in defaultBoolFields"
                            :key="field.key"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-3 py-3 dark:border-slate-600"
                        >
                            <Checkbox
                                v-model="form[field.key]"
                                :binary="true"
                                :input-id="'blog-ai-'+field.key"
                                class="mt-0.5"
                            />
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ field.label }}
                                    <span
                                        class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                    >
                                        {{ sources[field.key] || 'env' }}
                                    </span>
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                                    {{ field.help }}
                                </span>
                            </span>
                        </label>
                    </div>

                    <div class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-600">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                            Configure yourself
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Queue, public URL, and search API keys — set these for your environment. OpenAI key remains in Landing Settings.
                        </p>

                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-amber-200/80 bg-amber-50/50 px-3 py-3 dark:border-amber-500/30 dark:bg-amber-500/10"
                        >
                            <Checkbox
                                v-model="form.queue"
                                :binary="true"
                                input-id="blog-ai-queue"
                                class="mt-0.5"
                            />
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    Use queue worker (required in production)
                                    <span
                                        class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                    >
                                        {{ sources.queue || 'env' }}
                                    </span>
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                                    Default OFF for local. Production: turn ON + run queue:work --timeout=900.
                                </span>
                            </span>
                        </label>

                        <div class="space-y-1.5">
                            <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                                Public site base URL
                                <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase dark:bg-slate-700">
                                    {{ sources.landing_public_base_url || 'env' }}
                                </span>
                            </label>
                            <InputText
                                v-model="form.landing_public_base_url"
                                class="w-full"
                                placeholder="https://wooeasylife.com (optional)"
                            />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                                    Brave Search API key
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase dark:bg-slate-700">
                                        {{ sources.brave_api_key || 'env' }}
                                    </span>
                                </label>
                                <InputText
                                    v-model="form.brave_api_key"
                                    type="password"
                                    class="w-full"
                                    autocomplete="off"
                                    :placeholder="settings.brave_api_key_set ? '•••••••• (saved — type to replace)' : 'Optional'"
                                />
                                <label class="flex items-center gap-2 text-xs text-slate-500">
                                    <Checkbox
                                        v-model="form.clear_brave_api_key"
                                        :binary="true"
                                    />
                                    Clear saved Brave key
                                </label>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                                    Bing Search API key
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase dark:bg-slate-700">
                                        {{ sources.bing_api_key || 'env' }}
                                    </span>
                                </label>
                                <InputText
                                    v-model="form.bing_api_key"
                                    type="password"
                                    class="w-full"
                                    autocomplete="off"
                                    :placeholder="settings.bing_api_key_set ? '•••••••• (saved — type to replace)' : 'Optional'"
                                />
                                <label class="flex items-center gap-2 text-xs text-slate-500">
                                    <Checkbox
                                        v-model="form.clear_bing_api_key"
                                        :binary="true"
                                    />
                                    Clear saved Bing key
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <Button
                            type="submit"
                            label="Save settings"
                            icon="pi pi-check"
                            :loading="form.processing"
                        />
                        <Button
                            type="button"
                            label="Reset to .env"
                            icon="pi pi-refresh"
                            severity="secondary"
                            outlined
                            :disabled="form.processing"
                            @click="resetToEnv"
                        />
                    </div>
                </form>
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Pages/Users/fragments/PageHeader.vue';
import PageCard from '@/Pages/Users/fragments/PageCard.vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    sources: { type: Object, default: () => ({}) },
    fields: { type: Array, default: () => [] },
    how_to: { type: Array, default: () => [] },
    ops_notes: { type: Array, default: () => [] },
});

const defaultBoolFields = computed(() =>
    (props.fields || []).filter((f) => f.type === 'bool' && f.group === 'defaults'),
);

const form = useForm({
    enabled: !!props.settings.enabled,
    smart_one_click: !!props.settings.smart_one_click,
    prefer_gsc: !!props.settings.prefer_gsc,
    competitors_enabled: !!props.settings.competitors_enabled,
    competitors_in_prompts: !!props.settings.competitors_in_prompts,
    discovery_enabled: !!props.settings.discovery_enabled,
    discovery_auto_on_smart: !!props.settings.discovery_auto_on_smart,
    landing_ref_fetch: !!props.settings.landing_ref_fetch,
    memory_enabled: !!props.settings.memory_enabled,
    memory_in_prompts: !!props.settings.memory_in_prompts,
    queue: !!props.settings.queue,
    landing_public_base_url: props.settings.landing_public_base_url || '',
    brave_api_key: '',
    bing_api_key: '',
    clear_brave_api_key: false,
    clear_bing_api_key: false,
});

const submit = () => {
    form.put(route('blogPosts.settings.update'), { preserveScroll: true });
};

const resetToEnv = () => {
    if (!window.confirm('Reset all Blog AI admin overrides and fall back to .env defaults?')) {
        return;
    }
    router.post(route('blogPosts.settings.reset'), {}, { preserveScroll: true });
};
</script>
