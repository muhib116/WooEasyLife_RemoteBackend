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
                            label="Back"
                            icon="pi pi-arrow-left"
                            severity="secondary"
                            outlined
                            size="small"
                            @click="router.visit(route('blogPosts.index'))"
                        />
                        <Button
                            v-if="form.public_url"
                            label="View live"
                            icon="pi pi-external-link"
                            severity="secondary"
                            outlined
                            size="small"
                            as="a"
                            :href="form.public_url"
                            target="_blank"
                            rel="noopener"
                        />
                    </div>
                </template>
            </PageHeader>

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
                                        Public URL: /blog/{{ form.slug || '…' }}
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
                                <InputText
                                    v-model="form.og_image"
                                    class="w-full"
                                    placeholder="https://… or storage path"
                                />
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

                    <PageCard title="SEO checklist" description="Quick quality checks">
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
                                </span>
                            </li>
                        </ul>
                    </PageCard>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Pages/Users/fragments/PageHeader.vue';
import PageCard from '@/Pages/Users/fragments/PageCard.vue';
import { BlogClassic } from '@/plugins/form/editor';
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

const isEdit = computed(() => Boolean(props.post?.id));

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    locale: props.post?.locale ?? 'bn',
    status: props.post?.status ?? 'draft',
    excerpt: props.post?.excerpt ?? '',
    meta_title: props.post?.meta_title ?? '',
    meta_description: props.post?.meta_description ?? '',
    focus_keyword: props.post?.focus_keyword ?? '',
    og_image: props.post?.og_image ?? '',
    robots: props.post?.robots ?? 'index,follow',
    author_name: props.post?.author_name ?? 'WooEasyLife',
    body_html: props.post?.body_html ?? '',
    published_at: props.post?.published_at ?? '',
    public_url: props.post?.public_url ?? null,
});

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

const isSeoSlug = (value) => /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value || '');
const isPlaceholderSlug = (value) => /^post-[a-z0-9]{6}$/i.test(value || '');

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

const checklist = computed(() => {
    const bodyText = stripHtml(form.body_html);
    const keyword = (form.focus_keyword || '').trim().toLowerCase();
    const title = (form.title || '').toLowerCase();
    const metaDesc = (form.meta_description || form.excerpt || '').toLowerCase();
    const hasH2 = /<h2[\s>]/i.test(form.body_html || '');
    const hasInternalLink = /href=["']\//i.test(form.body_html || '');
    const wordCount = bodyText ? bodyText.split(/\s+/).filter(Boolean).length : 0;

    return [
        { label: 'Title present', ok: Boolean(form.title?.trim()) },
        { label: 'Focus keyword set', ok: Boolean(keyword) },
        { label: 'Keyword in title', ok: keyword ? title.includes(keyword) : false },
        { label: 'Meta description 50–160 chars', ok: metaDescLen.value >= 50 && metaDescLen.value <= 160 },
        { label: 'Keyword in meta description', ok: keyword ? metaDesc.includes(keyword) : false },
        { label: 'Body has H2 heading', ok: hasH2 },
        { label: 'At least one internal link', ok: hasInternalLink },
        { label: 'Body ≥ 300 words', ok: wordCount >= 300 },
        { label: 'Readable English SEO slug', ok: isSeoSlug(form.slug) && !isPlaceholderSlug(form.slug) },
        { label: 'Slug does not shadow markdown', ok: !markdownConflict.value },
    ];
});

const submit = () => {
    if (form.status === 'published') {
        normalizeSlugField();
        if (!isSeoSlug(form.slug) || isPlaceholderSlug(form.slug)) {
            form.setError('slug', 'Add a readable English SEO slug before publishing (e.g. fake-order-atkabo).');
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
        const { public_url, ...payload } = data;
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
