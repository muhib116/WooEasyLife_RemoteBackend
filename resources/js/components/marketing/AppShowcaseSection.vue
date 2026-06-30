<script setup>
defineProps({
    appShowcase: { type: Object, default: () => ({}) },
    appDownloadUrl: { type: String, default: null },
    playStoreUrl: { type: String, default: null },
});
</script>

<template>
    <section id="download-app" class="scroll-mt-24 border-t border-white/10 py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 lg:px-8">
            <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-12">
                <div class="order-2 lg:order-1">
                    <span class="inline-flex rounded-full border border-sky-400/30 bg-sky-500/10 px-3 py-1 text-xs font-bold text-sky-300">
                        মোবাইল অ্যাপ
                    </span>
                    <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl">
                        {{ appShowcase.headline }}
                    </h2>
                    <p class="mt-4 text-sm leading-relaxed text-slate-400 sm:text-base">
                        {{ appShowcase.subheadline }}
                    </p>

                    <ul v-if="appShowcase.benefits?.length" class="mt-6 space-y-3">
                        <li
                            v-for="benefit in appShowcase.benefits"
                            :key="benefit"
                            class="flex items-start gap-2.5 text-sm text-slate-200"
                        >
                            <span class="mt-0.5 text-sky-400">✓</span>
                            {{ benefit }}
                        </li>
                    </ul>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a
                            v-if="appDownloadUrl"
                            :href="appDownloadUrl"
                            class="inline-flex items-center justify-center rounded-xl bg-violet-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-violet-500"
                            download
                        >
                            APK ডাউনলোড
                        </a>
                        <a
                            v-if="playStoreUrl"
                            :href="playStoreUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center rounded-xl border border-white/15 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            Google Play
                        </a>
                    </div>
                </div>

                <div class="order-1 flex justify-center lg:order-2">
                    <div class="relative w-full max-w-[280px] sm:max-w-xs">
                        <div class="absolute -inset-4 rounded-[2.5rem] bg-sky-600/20 blur-2xl" />
                        <div class="relative overflow-hidden rounded-[2rem] border-4 border-slate-700 bg-[#0f1729] shadow-2xl">
                            <div class="flex items-center justify-center gap-1 border-b border-white/10 bg-black/40 px-4 py-2">
                                <span class="h-2 w-2 rounded-full bg-rose-400" />
                                <span class="h-2 w-2 rounded-full bg-amber-400" />
                                <span class="h-2 w-2 rounded-full bg-emerald-400" />
                            </div>
                            <div class="space-y-3 p-4">
                                <div class="flex items-center gap-2">
                                    <img src="/app-logo" alt="" class="h-8 w-8 rounded-lg" />
                                    <div>
                                        <p class="text-xs font-bold text-white">WooEasyLife</p>
                                        <p class="text-[10px] text-slate-400">৩টি ওয়েবসাইট কানেক্টেড</p>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2">
                                    <p class="text-[10px] text-rose-300">নতুন অর্ডার · Fashion Website</p>
                                    <p class="text-xs font-bold text-white">#৪৮২১ — ৳১,২৫০</p>
                                </div>
                                <div class="rounded-xl border border-white/10 bg-white/5 px-3 py-2">
                                    <p class="text-[10px] text-slate-400">ফ্রড চেক</p>
                                    <p class="text-xs font-bold text-emerald-400">৮৮% নিরাপদ ✓</p>
                                </div>
                                <div class="rounded-xl border border-sky-500/30 bg-sky-500/10 px-3 py-2">
                                    <p class="text-[10px] text-sky-300">ইনকামিং কল</p>
                                    <p class="text-xs text-white">রাহিম আহমেদ · ৩ অর্ডার</p>
                                </div>
                                <div class="flex gap-2">
                                    <span class="rounded-lg bg-violet-600/30 px-2 py-1 text-[10px] font-bold text-violet-200">ওয়েবসাইট ১</span>
                                    <span class="rounded-lg bg-white/5 px-2 py-1 text-[10px] text-slate-400">২</span>
                                    <span class="rounded-lg bg-white/5 px-2 py-1 text-[10px] text-slate-400">৩</span>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-center text-xs text-slate-500">
                            স্ক্রিনশট যোগ করলে এখানে রিয়েল অ্যাপ দেখানো যাবে
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
