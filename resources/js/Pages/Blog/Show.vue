<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';
import { trackCtaClick, trackOnce, trackViewContent } from '@/utils/metaPixel';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    post: { type: Object, default: () => ({}) },
    whatsappUrl: { type: String, default: null },
});

const formatDate = (value) => {
    if (!value) return '';
    try {
        return new Date(value).toLocaleDateString('bn-BD', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return value;
    }
};

onMounted(() => {
    const slug = props.post.slug || props.post.id || props.post.title || 'post';
    trackOnce(`viewcontent:blog:${slug}`, () =>
        trackViewContent({
            content_name: props.post.title,
            content_category: 'blog',
            content_type: 'article',
        }),
    );

    if (props.post.slug) {
        trackBlogEvent('view');
        setupScrollTracking();
    }
});

const setupScrollTracking = () => {
    let fired = false;
    const onScroll = () => {
        if (fired) return;
        const doc = document.documentElement;
        const scrollTop = window.scrollY || doc.scrollTop;
        const height = doc.scrollHeight - doc.clientHeight;
        if (height <= 0) return;
        const pct = Math.round((scrollTop / height) * 100);
        if (pct >= 50) {
            fired = true;
            trackBlogEvent('scroll_depth', { scroll_pct: 50 });
            window.removeEventListener('scroll', onScroll);
        }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
};

const trackBlogEvent = (event, extra = {}) => {
    if (!props.post?.slug) {
        return;
    }

    let visitorId = '';
    try {
        visitorId = localStorage.getItem('wel_blog_vid') || '';
        if (!/^[a-f0-9]{16,64}$/i.test(visitorId)) {
            visitorId = [...crypto.getRandomValues(new Uint8Array(16))]
                .map((b) => b.toString(16).padStart(2, '0'))
                .join('');
            localStorage.setItem('wel_blog_vid', visitorId);
        }
    } catch {
        visitorId = '';
    }

    const payload = {
        slug: props.post.slug,
        event,
        visitor_id: visitorId || undefined,
        ...extra,
    };

    fetch('/blog/analytics/event', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
        keepalive: true,
    }).catch(() => {});
};

const onFraudCtaClick = () => {
    trackCtaClick({ location: 'blog_fraud_cta', href: '/bd-fraud-checker', label: 'ফ্রি ফ্রড চেক' });
    trackBlogEvent('cta_click', { cta_label: 'ফ্রি ফ্রড চেক' });
};
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="features">
        <article class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                <p v-if="post.date" class="mt-4 text-xs font-medium text-slate-500">
                    {{ formatDate(post.date) }}
                </p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl">
                    {{ post.title }}
                </h1>
                <p v-if="post.description" class="mt-4 text-base text-slate-300 sm:text-lg">
                    {{ post.description }}
                </p>

                <div
                    class="prose prose-invert prose-amber mt-10 max-w-none prose-headings:text-white prose-a:text-amber-400 prose-strong:text-white prose-li:text-slate-300 prose-p:text-slate-300"
                    v-html="post.html"
                />

                <div class="mt-12 flex flex-wrap gap-3 border-t border-white/10 pt-8">
                    <Link
                        :href="route('blog.index')"
                        class="inline-flex rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                    >
                        ← সব পোস্ট
                    </Link>
                    <Link
                        href="/bd-fraud-checker"
                        class="inline-flex rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-bold text-black hover:bg-amber-400"
                        @click="onFraudCtaClick"
                    >
                        ফ্রি ফ্রড চেক
                    </Link>
                </div>
            </div>
        </article>
    </MarketingLayout>
</template>
