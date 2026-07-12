<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
import SeoHead from '@/components/marketing/SeoHead.vue';
import SeoBreadcrumbs from '@/components/marketing/SeoBreadcrumbs.vue';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: null },
    posts: { type: Array, default: () => [] },
    whatsappUrl: { type: String, default: null },
    locale: { type: String, default: 'bn' },
    indexPath: { type: String, default: '/blog' },
});

const isEn = computed(() => props.locale === 'en');

const formatDate = (value) => {
    if (!value) return '';
    try {
        return new Date(value).toLocaleDateString(isEn.value ? 'en-US' : 'bn-BD', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return value;
    }
};
</script>

<template>
    <SeoHead :seo="seo" />

    <MarketingLayout :can-login="canLogin" :whatsapp-url="whatsappUrl" active-nav="features">
        <section class="border-b border-white/10 px-4 py-12 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="text-left">
                    <SeoBreadcrumbs :items="seo?.breadcrumbs || []" />
                </div>
                <p class="text-sm font-semibold tracking-[0.18em] text-amber-300/90">WooEasyLife</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ isEn ? 'Blog — COD fraud & operations guides' : 'ব্লগ — ফেক অর্ডার, ফ্রড চেক ও COD টিপস' }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    {{ isEn
                        ? 'Practical guides for Bangladesh WooCommerce COD sellers.'
                        : 'বাংলাদেশি WooCommerce সেলারদের জন্য প্র্যাকটিক্যাল গাইড — কুরিয়ার হিস্টোরি, রিটার্ন লস ও ফ্রড চেক।' }}
                </p>
                <p class="mt-3 text-sm">
                    <Link v-if="isEn" href="/blog" class="text-amber-400 hover:text-amber-300">বাংলা ব্লগ</Link>
                    <Link v-else href="/en/blog" class="text-amber-400 hover:text-amber-300">English blog</Link>
                </p>
            </div>
        </section>

        <section class="px-4 py-12 lg:px-8">
            <div class="mx-auto grid max-w-3xl gap-4">
                <article
                    v-for="post in posts"
                    :key="post.slug"
                    class="rounded-2xl border border-white/10 bg-white/5 p-5 transition hover:border-amber-500/30 hover:bg-amber-500/5"
                >
                    <p v-if="post.date" class="text-xs font-medium text-slate-500">
                        {{ formatDate(post.date) }}
                    </p>
                    <h2 class="mt-2 text-lg font-bold text-white sm:text-xl">
                        <Link
                            :href="route('blog.show', post.slug)"
                            class="hover:text-amber-300"
                        >
                            {{ post.title }}
                        </Link>
                    </h2>
                    <p v-if="post.description" class="mt-2 text-sm leading-relaxed text-slate-400">
                        {{ post.description }}
                    </p>
                    <Link
                        :href="route('blog.show', post.slug)"
                        class="mt-4 inline-flex text-sm font-semibold text-amber-400 hover:text-amber-300"
                    >
                        {{ isEn ? 'Read →' : 'পড়ুন →' }}
                    </Link>
                </article>

                <p v-if="!posts.length" class="text-center text-sm text-slate-400">
                    {{ isEn ? 'No posts yet.' : 'এখনো কোনো পোস্ট নেই।' }}
                </p>
            </div>
        </section>
    </MarketingLayout>
</template>
