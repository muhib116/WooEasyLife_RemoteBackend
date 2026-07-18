<template>
    <AuthenticatedLayout :title="isEdit ? 'Edit Blog Post' : 'New Blog Post'">
        <div class="space-y-5">
            <PageHeader
                :title="isEdit ? 'Edit blog post' : 'Write a blog post'"
                description="SEO fields + CKEditor body. Use H2/H3, internal links, and a clear focus keyword."
                icon="PhNewspaper"
                icon-bg-class="bg-amber-50 dark:bg-amber-500/15"
                icon-class="text-amber-600 dark:text-amber-400"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="!isEdit && canUseBlogAi"
                            label="AI Auto Create"
                            icon="pi pi-sparkles"
                            size="small"
                            @click="aiWizardOpen = true"
                        />
                        <Button
                            label="Back"
                            icon="pi pi-arrow-left"
                            severity="secondary"
                            outlined
                            size="small"
                            @click="router.visit(route('blogPosts.index'))"
                        />
                        <Button
                            v-if="viewPostUrl"
                            label="View post"
                            icon="pi pi-external-link"
                            severity="secondary"
                            outlined
                            size="small"
                            as="a"
                            :href="viewPostUrl"
                            target="_blank"
                            rel="noopener"
                        />
                    </div>
                </template>
            </PageHeader>

            <div
                v-if="form.slug"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900"
            >
                <span class="font-semibold text-slate-700 dark:text-slate-200">View post:</span>
                <a
                    v-if="viewPostUrl"
                    :href="viewPostUrl"
                    target="_blank"
                    rel="noopener"
                    class="ml-2 break-all font-mono text-amber-700 underline underline-offset-2 hover:text-amber-600 dark:text-amber-400"
                >
                    {{ viewPostLabel }}
                </a>
                <span
                    v-else
                    class="ml-2 break-all font-mono text-slate-500 dark:text-slate-400"
                >
                    {{ previewPath }}
                    <span class="ml-1 text-xs not-italic text-amber-600 dark:text-amber-400">(publish to make live)</span>
                </span>
            </div>

            <form class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]" @submit.prevent="submit">
                <div class="space-y-5">
                    <PageCard title="Content" description="Title, slug, and rich body">
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Title <span class="text-rose-500">*</span>
                                </label>
                                <InputText
                                    v-model="form.title"
                                    class="w-full"
                                    placeholder="e.g. কিভাবে ফেক অর্ডার আটকাবো"
                                    @blur="maybeAutofillSlug"
                                />
                                <small v-if="form.errors.title" class="mt-1 block text-rose-500">
                                    {{ form.errors.title }}
                                </small>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        SEO slug
                                        <span v-if="form.status === 'published'" class="text-rose-500">*</span>
                                    </label>
                                    <InputText
                                        v-model="form.slug"
                                        class="w-full font-mono text-sm"
                                        placeholder="e.g. fake-order-atkabo"
                                        @blur="normalizeSlugField"
                                    />
                                    <small class="mt-1 block text-gray-500">
                                        Public path: <span class="font-mono">{{ previewPath }}</span>
                                        — use Latin letters only (required to publish).
                                    </small>
                                    <small v-if="banglaNeedsSlug" class="mt-1 block text-amber-600 dark:text-amber-400">
                                        Bangla titles don’t auto-slug. Set an English slug (or fill focus keyword then click Suggest).
                                    </small>
                                    <small v-if="markdownConflict" class="mt-1 block text-amber-600 dark:text-amber-400">
                                        This slug matches a markdown guide and will replace it on /blog.
                                    </small>
                                    <small v-if="form.errors.slug" class="mt-1 block text-rose-500">
                                        {{ form.errors.slug }}
                                    </small>
                                    <button
                                        type="button"
                                        class="mt-1 text-xs font-semibold text-amber-700 hover:underline dark:text-amber-300"
                                        @click="suggestSlug"
                                    >
                                        Suggest from focus keyword / title
                                    </button>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        Focus keyword
                                    </label>
                                    <InputText
                                        v-model="form.focus_keyword"
                                        class="w-full"
                                        placeholder="e.g. fake order atkabo"
                                        @blur="maybeAutofillSlug"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Body <span class="text-rose-500">*</span>
                                </label>
                                <div class="mb-2">
                                    <Button
                                        type="button"
                                        label="Insert from media library"
                                        icon="pi pi-images"
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        @click="openMediaPicker('body')"
                                    />
                                </div>
                                <BlogClassic
                                    v-model="form.body_html"
                                    :upload-url="route('blogPosts.uploadImage')"
                                    min-height="640px"
                                />
                                <small v-if="form.errors.body_html" class="mt-1 block text-rose-500">
                                    {{ form.errors.body_html }}
                                </small>
                            </div>
                        </div>
                    </PageCard>

                    <PageCard title="SEO" description="Search snippet controls (title ≤70, description ≤160)">
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Meta title
                                </label>
                                <InputText
                                    v-model="form.meta_title"
                                    class="w-full"
                                    :placeholder="form.title || 'Defaults to post title'"
                                    maxlength="70"
                                />
                                <div class="mt-1 flex justify-between text-xs text-gray-500">
                                    <span>Shown in Google / browser tab</span>
                                    <span :class="metaTitleLen > 60 ? 'text-amber-600' : ''">
                                        {{ metaTitleLen }}/70
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Meta description
                                </label>
                                <Textarea
                                    v-model="form.meta_description"
                                    class="w-full"
                                    rows="3"
                                    maxlength="160"
                                    placeholder="One clear sentence with the focus keyword."
                                />
                                <div class="mt-1 flex justify-between text-xs text-gray-500">
                                    <span>SERP snippet under the title</span>
                                    <span :class="metaDescLen > 155 ? 'text-amber-600' : ''">
                                        {{ metaDescLen }}/160
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Excerpt (listing card)
                                </label>
                                <Textarea
                                    v-model="form.excerpt"
                                    class="w-full"
                                    rows="2"
                                    maxlength="500"
                                    placeholder="Short teaser on /blog index"
                                />
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-slate-950/40">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    SERP preview
                                </p>
                                <p class="mt-2 truncate text-lg text-[#1a0dab] dark:text-sky-400">
                                    {{ serpTitle }}
                                </p>
                                <p class="truncate text-xs text-emerald-700 dark:text-emerald-400">
                                    {{ serpUrl }}
                                </p>
                                <p class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">
                                    {{ serpDescription }}
                                </p>
                            </div>
                        </div>
                    </PageCard>

                    <PageCard
                        title="FAQs"
                        :description="`Required for publish (≥ ${seoMinFaqs}). Powers FAQPage schema.`"
                    >
                        <div class="space-y-3">
                            <div
                                v-for="(faq, index) in form.faqs_json"
                                :key="`faq-${index}`"
                                class="space-y-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        FAQ {{ index + 1 }}
                                    </p>
                                    <Button
                                        type="button"
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        rounded
                                        size="small"
                                        :disabled="form.processing"
                                        @click="removeFaq(index)"
                                    />
                                </div>
                                <InputText
                                    v-model="faq.q"
                                    class="w-full"
                                    maxlength="200"
                                    placeholder="Question"
                                />
                                <Textarea
                                    v-model="faq.a"
                                    class="w-full"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="Answer"
                                />
                            </div>
                            <Button
                                type="button"
                                label="Add FAQ"
                                icon="pi pi-plus"
                                size="small"
                                severity="secondary"
                                outlined
                                :disabled="form.processing || form.faqs_json.length >= 12"
                                @click="addFaq"
                            />
                            <small v-if="form.errors.faqs_json" class="block text-rose-500">
                                {{ form.errors.faqs_json }}
                            </small>
                        </div>
                    </PageCard>
                </div>

                <div class="space-y-5">
                    <PageCard title="Publish" description="Visibility & locale">
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Status
                                </label>
                                <Select
                                    v-model="form.status"
                                    :options="statusOptions"
                                    option-label="label"
                                    option-value="value"
                                    class="w-full"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Locale
                                </label>
                                <Select
                                    v-model="form.locale"
                                    :options="localeOptions"
                                    option-label="label"
                                    option-value="value"
                                    class="w-full"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Published at
                                </label>
                                <InputText
                                    v-model="form.published_at"
                                    type="datetime-local"
                                    class="w-full"
                                />
                                <small class="mt-1 block text-gray-500">
                                    Leave empty when publishing to use now.
                                </small>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Author
                                </label>
                                <InputText
                                    v-model="form.author_name"
                                    class="w-full"
                                    placeholder="WooEasyLife"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    Robots
                                </label>
                                <Select
                                    v-model="form.robots"
                                    :options="robotOptions"
                                    class="w-full"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    OG image URL / path
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <InputText
                                        v-model="form.og_image"
                                        class="min-w-[10rem] flex-1"
                                        placeholder="https://… or storage path"
                                    />
                                    <Button
                                        type="button"
                                        label="Media"
                                        icon="pi pi-images"
                                        severity="secondary"
                                        outlined
                                        @click="openMediaPicker('og')"
                                    />
                                </div>
                                <img
                                    v-if="ogPreview"
                                    :src="ogPreview"
                                    alt="OG preview"
                                    class="mt-2 max-h-28 rounded-lg border border-gray-200 object-cover dark:border-gray-700"
                                />
                            </div>

                            <div class="flex flex-col gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                                <Button
                                    type="submit"
                                    :label="isEdit ? 'Save changes' : 'Create post'"
                                    icon="pi pi-check"
                                    :loading="form.processing"
                                    :disabled="form.processing"
                                />
                                <Button
                                    v-if="isEdit"
                                    type="button"
                                    label="Delete"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    :disabled="form.processing"
                                    @click="destroy"
                                />
                            </div>
                        </div>
                    </PageCard>

                    <PageCard
                        title="SEO checklist"
                        description="Quick quality checks"
                    >
                        <template #actions>
                            <Button
                                v-if="canUseBlogAi"
                                type="button"
                                label="Regenerate"
                                icon="pi pi-refresh"
                                size="small"
                                severity="help"
                                outlined
                                class="!inline-flex whitespace-nowrap"
                                :loading="seoRegenLoading"
                                :disabled="seoRegenLoading || form.processing || !canRegenerateSeo"
                                :title="canRegenerateSeo ? 'Fix unchecked SEO items with AI' : 'Need title, focus keyword, and body first'"
                                @click="regenerateSeoChecklist"
                            />
                        </template>
                        <div
                            v-if="form.ai_quality_score != null"
                            class="mb-3 rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">AI readiness score</p>
                            <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-slate-100">
                                {{ form.ai_quality_score }}
                            </p>
                        </div>
                        <p
                            v-if="seoRegenNote"
                            class="mb-3 rounded-lg border border-sky-200/80 bg-sky-50/80 px-3 py-2 text-xs text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100"
                        >
                            {{ seoRegenNote }}
                        </p>
                        <ul class="space-y-2 text-sm">
                            <li
                                v-for="item in checklist"
                                :key="item.label"
                                class="flex items-start gap-2"
                            >
                                <i
                                    :class="[
                                        'pi mt-0.5 text-sm',
                                        item.ok ? 'pi-check-circle text-emerald-500' : 'pi-circle text-gray-400',
                                    ]"
                                />
                                <span :class="item.ok ? 'text-gray-800 dark:text-gray-100' : 'text-gray-500'">
                                    {{ item.label }}
                                    <span
                                        v-if="item.required"
                                        class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-rose-500"
                                    >required</span>
                                </span>
                            </li>
                        </ul>
                    </PageCard>
                </div>
            </form>
        </div>

        <MediaPickerDialog
            v-model:visible="mediaPickerOpen"
            :title="mediaPickerMode === 'og' ? 'Choose OG image' : 'Insert media into body'"
            @select="onMediaSelected"
        />

        <BlogAiWizard
            v-model:visible="aiWizardOpen"
            @apply="applyAiDraft"
        />
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Pages/Users/fragments/PageHeader.vue';
import PageCard from '@/Pages/Users/fragments/PageCard.vue';
import MediaPickerDialog from '@/components/media/MediaPickerDialog.vue';
import BlogAiWizard from '@/components/blog/BlogAiWizard.vue';
import { BlogClassic } from '@/plugins/form/editor';
import { usePermissions } from '@/composables/usePermissions';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';

const props = defineProps({
    post: { type: Object, default: null },
    options: {
        type: Object,
        default: () => ({
            locales: ['bn', 'en'],
            statuses: ['draft', 'published'],
            robots: ['index,follow'],
            markdown_slugs: [],
        }),
    },
});

const { can } = usePermissions();
const toast = useToast();
const canUseBlogAi = computed(() => can('billing.manage'));
const isEdit = computed(() => Boolean(props.post?.id));
const mediaPickerOpen = ref(false);
const mediaPickerMode = ref('og');
const aiWizardOpen = ref(false);
const seoRegenLoading = ref(false);
const seoRegenNote = ref('');

onMounted(() => {
    if (!isEdit.value && canUseBlogAi.value) {
        const params = new URLSearchParams(window.location.search);
        if (params.get('ai') === '1') {
            aiWizardOpen.value = true;
        }
    }
});

const openMediaPicker = (mode) => {
    mediaPickerMode.value = mode;
    mediaPickerOpen.value = true;
};

const escapeAttr = (value) =>
    String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');

const onMediaSelected = (media) => {
    if (!media?.url) {
        return;
    }

    if (mediaPickerMode.value === 'og') {
        form.og_image = media.url;
        return;
    }

    const alt = escapeAttr(media.alt || media.title || '');
    const html = `<p><img src="${escapeAttr(media.url)}" alt="${alt}"></p>`;
    form.body_html = `${form.body_html || ''}${html}`;
};

const applyAiDraft = (draft) => {
    if (!draft) {
        return;
    }

    form.title = draft.title || form.title;
    form.slug = draft.slug || form.slug;
    form.locale = draft.locale || 'bn';
    form.status = 'draft';
    form.focus_keyword = draft.focus_keyword || form.focus_keyword;
    form.meta_title = draft.meta_title || form.meta_title;
    form.meta_description = draft.meta_description || form.meta_description;
    form.excerpt = draft.excerpt || form.excerpt;
    form.author_name = draft.author_name || form.author_name || 'Muhibbullah Ansary';
    form.robots = draft.robots || 'index,follow';
    form.body_html = draft.body_html || form.body_html;
    if (draft.cluster) {
        form.cluster = draft.cluster;
    }
    if (draft.og_image) {
        form.og_image = draft.og_image;
    }
    if (Array.isArray(draft.faqs)) {
        form.faqs_json = draft.faqs;
    }
    if (draft.ai_quality_score != null) {
        form.ai_quality_score = draft.ai_quality_score;
    }
    if (draft.ai_quality_breakdown) {
        form.ai_quality_breakdown = draft.ai_quality_breakdown;
    }
    if (draft.ai_run_id) {
        form.ai_run_id = draft.ai_run_id;
    }
};

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    locale: props.post?.locale ?? 'bn',
    cluster: props.post?.cluster ?? '',
    status: props.post?.status ?? 'draft',
    excerpt: props.post?.excerpt ?? '',
    meta_title: props.post?.meta_title ?? '',
    meta_description: props.post?.meta_description ?? '',
    focus_keyword: props.post?.focus_keyword ?? '',
    og_image: props.post?.og_image ?? '',
    robots: props.post?.robots ?? 'index,follow',
    author_name: props.post?.author_name ?? 'Muhibbullah Ansary',
    faqs_json: props.post?.faqs_json ?? [],
    body_html: props.post?.body_html ?? '',
    published_at: props.post?.published_at ?? '',
    ai_quality_score: props.post?.ai_quality_score ?? null,
    ai_quality_breakdown: props.post?.ai_quality_breakdown ?? null,
    ai_run_id: props.post?.ai_run_id ?? null,
    public_url: props.post?.public_url ?? null,
    public_path: props.post?.public_path ?? null,
});

const isSeoSlug = (value) => /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value || '');
const isPlaceholderSlug = (value) => /^post-[a-z0-9]{6}$/i.test(value || '');

const previewPath = computed(() => {
    const slug = String(form.slug || '').trim();
    return slug ? `/blog/${slug}` : '/blog/…';
});

const absolutePublicUrl = (path) => {
    if (!path) {
        return null;
    }
    if (/^https?:\/\//i.test(path)) {
        return path;
    }
    if (typeof window !== 'undefined' && window.location?.origin) {
        return `${window.location.origin}${path.startsWith('/') ? path : `/${path}`}`;
    }
    return path;
};

const viewPostUrl = computed(() => {
    if (form.status !== 'published') {
        return null;
    }
    const slug = String(form.slug || '').trim();
    if (!slug || isPlaceholderSlug(slug) || !isSeoSlug(slug)) {
        return null;
    }
    // Prefer same-origin absolute URL so View always hits the public site (not a relative admin path).
    return absolutePublicUrl(form.public_url || form.public_path || previewPath.value);
});

const viewPostLabel = computed(() => viewPostUrl.value || previewPath.value);

const localeOptions = computed(() =>
    (props.options.locales || ['bn', 'en']).map((value) => ({
        value,
        label: value === 'bn' ? 'Bangla (bn)' : value === 'en' ? 'English (en)' : value,
    })),
);

const statusOptions = computed(() =>
    (props.options.statuses || ['draft', 'published']).map((value) => ({
        value,
        label: value === 'published' ? 'Published' : 'Draft',
    })),
);

const robotOptions = computed(() => props.options.robots || ['index,follow']);
const markdownSlugs = computed(() => props.options.markdown_slugs || []);

const metaTitleLen = computed(() => (form.meta_title || form.title || '').length);
const metaDescLen = computed(() => (form.meta_description || form.excerpt || '').length);

const serpTitle = computed(
    () => form.meta_title || form.title || 'Post title',
);
const serpUrl = computed(
    () => `${window.location.origin}/blog/${form.slug || 'your-slug'}`,
);
const serpDescription = computed(
    () => form.meta_description || form.excerpt || 'Meta description will appear here.',
);

const banglaNeedsSlug = computed(() => {
    const titleHasLatin = /[a-z0-9]/i.test(form.title || '');
    return !titleHasLatin && !isSeoSlug(form.slug);
});

const markdownConflict = computed(() => {
    const slug = (form.slug || '').trim();
    return slug !== '' && markdownSlugs.value.includes(slug);
});

const ogPreview = computed(() => {
    if (props.post?.og_image_url && !form.og_image) {
        return props.post.og_image_url;
    }
    const value = form.og_image;
    if (!value) return null;
    if (/^https?:\/\//i.test(value) || value.startsWith('/')) return value;
    return `/storage/${value}`;
});

const slugify = (value) =>
    String(value || '')
        .normalize('NFKD')
        .replace(/[^\w\s-]/g, '')
        .trim()
        .toLowerCase()
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');

const suggestSlug = () => {
    const generated = slugify(form.focus_keyword || form.title);
    if (generated) {
        form.slug = generated;
        return;
    }
    window.alert('Could not build a Latin slug from title. Type an English slug manually (e.g. fake-order-atkabo).');
};

const maybeAutofillSlug = () => {
    if (form.slug && !isPlaceholderSlug(form.slug)) return;
    const generated = slugify(form.focus_keyword || form.title);
    if (generated) {
        form.slug = generated;
    }
};

const normalizeSlugField = () => {
    if (!form.slug) return;
    form.slug = slugify(form.slug) || form.slug.trim().toLowerCase();
};

const stripHtml = (html) =>
    String(html || '')
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

const seoMinWords = computed(() => Number(props.options?.seo?.min_body_words || 800));
const seoMinFaqs = computed(() => Number(props.options?.seo?.min_faqs || 5));
const seoMinLinks = computed(() => Number(props.options?.seo?.min_internal_links || 2));

const firstBodyParagraphText = (html) => {
    const source = String(html || '');
    const afterQuick = source.match(/seo-quick-answer[\s\S]*?<\/section>\s*<p\b[^>]*>(.*?)<\/p>/is);
    if (afterQuick?.[1]) {
        return stripHtml(afterQuick[1]).toLowerCase();
    }
    const firstP = source.match(/<p\b[^>]*>(.*?)<\/p>/is);
    if (firstP?.[1]) {
        return stripHtml(firstP[1]).toLowerCase();
    }
    return stripHtml(source).toLowerCase().slice(0, 400);
};

const addFaq = () => {
    if (!Array.isArray(form.faqs_json)) {
        form.faqs_json = [];
    }
    if (form.faqs_json.length >= 12) {
        return;
    }
    form.faqs_json.push({ q: '', a: '' });
};

const removeFaq = (index) => {
    if (!Array.isArray(form.faqs_json)) {
        return;
    }
    form.faqs_json.splice(index, 1);
};

const checklist = computed(() => {
    const bodyText = stripHtml(form.body_html);
    const keyword = (form.focus_keyword || '').trim().toLowerCase();
    const title = (form.title || '').toLowerCase();
    const metaDesc = (form.meta_description || form.excerpt || '').toLowerCase();
    const hasH2 = /<h2[\s>]/i.test(form.body_html || '');
    const internalLinks = (form.body_html || '').match(/<a\b[^>]*\bhref=["']\/[^"']*["']/gi) || [];
    const faqCount = Array.isArray(form.faqs_json)
        ? form.faqs_json.filter((row) => row?.q && row?.a).length
        : 0;
    const firstParagraph = firstBodyParagraphText(form.body_html);
    const hasContentImage = /<img[\s>]/i.test(form.body_html || '');
    const contentImageAltOk = !hasContentImage
        || /<img\b[^>]*\balt=["'][^"']+["']/i.test(form.body_html || '');
    const hasOgImage = Boolean(String(form.og_image || '').trim() || ogPreview.value);
    const hasH3 = /<h3[\s>]/i.test(form.body_html || '');
    const hasLists = /<(ul|ol)[\s>]/i.test(form.body_html || '');
    const keywordInH2 = (() => {
        if (!keyword) return false;
        const matches = form.body_html?.match(/<h2\b[^>]*>(.*?)<\/h2>/gis) || [];
        return matches.some((h) => stripHtml(h).toLowerCase().includes(keyword));
    })();
    const hasQuickAnswer =
        /seo-quick-answer/i.test(form.body_html || '')
        || /<h2\b[^>]*>[^<]*(Quick Answer|দ্রুত উত্তর)[^<]*<\/h2>/iu.test(form.body_html || '');
    const hasAiSummary =
        /seo-ai-summary/i.test(form.body_html || '')
        || /<h2\b[^>]*>[^<]*(AI Search Summary|AI Summary|এআই সারাংশ)[^<]*<\/h2>/iu.test(form.body_html || '');
    const wordCount = bodyText ? bodyText.split(/\s+/).filter(Boolean).length : 0;
    const keywordInTitle = keyword ? title.includes(keyword) : false;
    const metaLen = metaDescLen.value;
    const ogOrImage = hasOgImage || (hasContentImage && contentImageAltOk);

    return [
        { label: 'Title present', ok: Boolean(form.title?.trim()), required: true },
        { label: 'Focus keyword set', ok: Boolean(keyword), required: true },
        { label: 'Keyword in title', ok: keywordInTitle, required: true },
        { label: 'Keyword in first paragraph (after Quick Answer)', ok: keyword ? firstParagraph.includes(keyword) : false, required: true },
        { label: 'Keyword in one H2', ok: keywordInH2, required: true },
        { label: 'Meta description 50–160 chars', ok: metaLen >= 50 && metaLen <= 160, required: true },
        { label: 'Keyword in meta description', ok: keyword ? metaDesc.includes(keyword) : false, required: true },
        { label: 'Body has H2 heading', ok: hasH2, required: true },
        { label: 'Body has H3 heading', ok: hasH3, required: true },
        { label: 'Has bullet or numbered list', ok: hasLists, required: true },
        { label: 'Featured snippet (দ্রুত উত্তর)', ok: hasQuickAnswer, required: true },
        { label: 'AI Search Summary (এআই সারাংশ)', ok: hasAiSummary, required: true },
        { label: `${seoMinLinks.value}+ internal links`, ok: internalLinks.length >= seoMinLinks.value, required: true },
        { label: `FAQs ≥ ${seoMinFaqs.value}`, ok: faqCount >= seoMinFaqs.value, required: true },
        { label: 'OG/cover or content image with alt', ok: ogOrImage, required: true },
        { label: `Body ≥ ${seoMinWords.value} words`, ok: wordCount >= seoMinWords.value, required: true },
        { label: 'Readable English SEO slug', ok: isSeoSlug(form.slug) && !isPlaceholderSlug(form.slug), required: true },
        { label: 'Slug does not shadow markdown', ok: !markdownConflict.value, required: false },
    ];
});

const publishChecklistReady = computed(() =>
    checklist.value.filter((item) => item.required).every((item) => item.ok),
);
const canRegenerateSeo = computed(() =>
    Boolean(form.title?.trim())
        && Boolean(form.focus_keyword?.trim())
        && Boolean(stripHtml(form.body_html)),
);

const regenerateSeoChecklist = async () => {
    if (!canUseBlogAi.value || !canRegenerateSeo.value || seoRegenLoading.value) {
        return;
    }

    seoRegenLoading.value = true;
    seoRegenNote.value = '';

    try {
        let url;
        try {
            url = route('blogAi.regenerateSeoChecklist');
        } catch (routeError) {
            throw new Error(
                routeError?.message
                || 'Regenerate route missing — hard-refresh the page after deploy (Ziggy routes outdated).',
            );
        }

        const { data } = await axios.post(
            url,
            {
                title: form.title,
                focus_keyword: form.focus_keyword,
                body_html: form.body_html,
                slug: form.slug || null,
                meta_title: form.meta_title || null,
                meta_description: form.meta_description || null,
                excerpt: form.excerpt || null,
                faqs_json: Array.isArray(form.faqs_json) ? form.faqs_json : [],
                secondary_keywords: [],
                og_image: form.og_image || props.post?.og_image_url || null,
                locale: form.locale || 'bn',
                cluster: form.cluster || null,
                ignore_post_id: props.post?.id || null,
            },
            {
                timeout: 180000,
                headers: { Accept: 'application/json' },
            },
        );

        if (data.title) form.title = data.title;
        if (data.meta_title != null) form.meta_title = data.meta_title;
        if (data.meta_description != null) form.meta_description = data.meta_description;
        if (data.excerpt != null) form.excerpt = data.excerpt;
        if (data.body_html != null) form.body_html = data.body_html;
        if (Array.isArray(data.faqs_json)) form.faqs_json = data.faqs_json;
        if (data.focus_keyword) form.focus_keyword = data.focus_keyword;
        if (data.ai_quality_score != null) form.ai_quality_score = data.ai_quality_score;
        if (data.ai_quality_breakdown != null) form.ai_quality_breakdown = data.ai_quality_breakdown;

        const fixed = Array.isArray(data.fixed_checks) ? data.fixed_checks.length : 0;
        const notes = Array.isArray(data.notes) ? data.notes.filter(Boolean) : [];
        seoRegenNote.value = notes[0]
            || (fixed > 0
                ? `Updated ${fixed} SEO check(s). Review the draft, then save.`
                : 'Regenerate finished. Review checklist and save.');

        toast.add({
            severity: fixed > 0 || (data.ai_quality_score ?? 0) > 0 ? 'success' : 'info',
            summary: 'SEO regenerate',
            detail: seoRegenNote.value,
            life: 6000,
            group: 'br',
        });
    } catch (error) {
        const message = seoRegenErrorMessage(error);
        seoRegenNote.value = message;
        toast.add({
            severity: 'error',
            summary: 'SEO regenerate failed',
            detail: message,
            life: 9000,
            group: 'br',
        });
    } finally {
        seoRegenLoading.value = false;
    }
};

const seoRegenErrorMessage = (error) => {
    const data = error?.response?.data;
    const status = error?.response?.status;
    const fieldError = data?.errors?.ai?.[0]
        || Object.values(data?.errors || {})?.flat?.()?.[0]
        || Object.values(data?.errors || {})?.[0]?.[0];

    if (fieldError) {
        return String(fieldError);
    }

    if (typeof data?.message === 'string' && data.message.trim() && data.message !== 'The given data was invalid.') {
        return data.message;
    }

    if (error?.code === 'ECONNABORTED' || /timeout/i.test(String(error?.message || ''))) {
        return 'Regenerate timed out waiting for OpenAI. Try again, or shorten the body first.';
    }

    if (status === 419) {
        return 'Session expired — refresh the page and try again.';
    }

    if (status === 404) {
        return 'Regenerate endpoint not found on server — deploy may be incomplete. Hard-refresh after deploy.';
    }

    if (status === 403) {
        return 'Permission denied — billing.manage is required for Blog AI regenerate.';
    }

    if (status >= 500) {
        return 'Server error during regenerate (often PHP timeout or missing OpenAI key). Check Landing Settings → OpenAI, then retry.';
    }

    if (typeof error?.message === 'string' && error.message.trim()) {
        return error.message;
    }

    if (typeof data?.message === 'string' && data.message.trim()) {
        return data.message;
    }

    return 'Could not regenerate SEO checklist items.';
};

const submit = () => {
    if (form.status === 'published') {
        normalizeSlugField();
        if (!isSeoSlug(form.slug) || isPlaceholderSlug(form.slug)) {
            form.setError('slug', 'Add a readable English SEO slug before publishing (e.g. fake-order-atkabo).');
            return;
        }

        if (!publishChecklistReady.value) {
            const missing = checklist.value
                .filter((item) => item.required && !item.ok)
                .map((item) => item.label)
                .slice(0, 5);
            toast.add({
                severity: 'warn',
                summary: 'SEO checklist incomplete',
                detail: missing.length
                    ? `Fix before publishing: ${missing.join('; ')}`
                    : 'Fix required SEO checklist items (or use Regenerate) before publishing.',
                life: 8000,
                group: 'br',
            });
            return;
        }
    }

    if (markdownConflict.value && form.status === 'published') {
        const ok = window.confirm(
            `Slug “${form.slug}” already exists as a markdown guide. Publishing will replace it on /blog. Continue?`,
        );
        if (!ok) return;
    }

    form.transform((data) => {
        const { public_url, public_path, ...payload } = data;
        return payload;
    });

    if (isEdit.value) {
        form.put(route('blogPosts.update', props.post.id), {
            preserveScroll: true,
        });
        return;
    }

    form.post(route('blogPosts.store'), {
        preserveScroll: true,
    });
};

const destroy = () => {
    if (!props.post?.id) return;
    if (!window.confirm(`Delete “${form.title}”?`)) return;
    router.delete(route('blogPosts.destroy', props.post.id));
};
</script>
