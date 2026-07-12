<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { validateDomainInput } from '@/utils/domain';

const props = defineProps({
    appDownloadUrl: { type: String, default: null },
    pluginDownloadUrl: { type: String, default: null },
    playStoreUrl: { type: String, default: null },
});

const step = ref('form'); // form | otp | ready
const name = ref('');
const phone = ref('');
const website = ref('');
const otp = ref('');
const downloadToken = ref('');
const verifiedWebsite = ref('');
const loading = ref(false);
const websiteChecking = ref(false);
const websiteDnsOk = ref(null); // null | true | false
const error = ref('');
const websiteFieldError = ref('');
const phoneFieldError = ref('');
const info = ref('');
const resendAfter = ref(0);
const debugOtp = ref('');

let resendTimer = null;
let websiteValidateController = null;

const hasApk = computed(() => Boolean(props.appDownloadUrl));
const hasPlugin = computed(() => Boolean(props.pluginDownloadUrl));
const hasAnyFile = computed(() => hasApk.value || hasPlugin.value);

const looksLikePageUrl = (raw) => {
    const value = String(raw ?? '').trim();

    if (!value) {
        return false;
    }

    let candidate = value;

    if (!/^https?:\/\//i.test(candidate)) {
        if (!candidate.includes('/')) {
            return false;
        }

        candidate = `https://${candidate}`;
    }

    try {
        const parsed = new URL(candidate);
        const path = parsed.pathname || '';
        const query = parsed.search || '';
        const fragment = parsed.hash || '';

        return (path !== '' && path !== '/') || query !== '' || fragment !== '';
    } catch {
        return false;
    }
};

const validateWebsiteLocal = () => {
    const raw = website.value.trim();

    if (!raw) {
        websiteDnsOk.value = false;
        websiteFieldError.value = 'সঠিক ওয়েবসাইট/ডোমেইন দিন (যেমন: shop.example.com)।';

        return false;
    }

    if (looksLikePageUrl(raw)) {
        websiteDnsOk.value = false;
        websiteFieldError.value = 'শুধু ওয়েবসাইটের ডোমেইন দিন (যেমন: myshop.com), পূর্ণ পেজ লিংক নয়।';

        return false;
    }

    const result = validateDomainInput(raw);

    if (!result.valid) {
        websiteDnsOk.value = false;
        websiteFieldError.value = result.message || 'সঠিক ডোমেইন ফরম্যাট দিন (যেমন: example.com)।';

        return false;
    }

    return true;
};

const markWebsiteDirty = () => {
    websiteDnsOk.value = null;
    websiteFieldError.value = '';

    if (websiteValidateController) {
        websiteValidateController.abort();
        websiteValidateController = null;
    }
};

const ensureWebsiteDns = async () => {
    if (!validateWebsiteLocal()) {
        return false;
    }

    if (websiteDnsOk.value === true) {
        return true;
    }

    if (websiteValidateController) {
        websiteValidateController.abort();
    }

    const controller = new AbortController();
    websiteValidateController = controller;
    websiteChecking.value = true;

    try {
        const { data } = await axios.post(
            route('landing.download-gate.validate-website'),
            { website: website.value.trim() },
            { signal: controller.signal },
        );

        if (controller.signal.aborted) {
            return false;
        }

        if (data?.website) {
            website.value = data.website;
        }

        websiteDnsOk.value = true;
        websiteFieldError.value = '';

        return true;
    } catch (e) {
        if (axios.isCancel?.(e) || e?.code === 'ERR_CANCELED' || e?.name === 'CanceledError') {
            return false;
        }

        websiteDnsOk.value = false;
        websiteFieldError.value = e?.response?.data?.message
            || 'ডোমেইনের DNS A রেকর্ড পাওয়া যায়নি। লাইভ ওয়েবসাইটের সঠিক ডোমেইন দিন।';

        return false;
    } finally {
        if (websiteValidateController === controller) {
            websiteValidateController = null;
            websiteChecking.value = false;
        }
    }
};

const validateWebsiteOnBlur = async () => {
    await ensureWebsiteDns();
};

const startResendCountdown = (seconds) => {
    resendAfter.value = Math.max(0, Number(seconds) || 0);

    if (resendTimer) {
        clearInterval(resendTimer);
    }

    if (resendAfter.value <= 0) {
        return;
    }

    resendTimer = setInterval(() => {
        resendAfter.value -= 1;

        if (resendAfter.value <= 0) {
            clearInterval(resendTimer);
            resendTimer = null;
        }
    }, 1000);
};

const sendOtp = async () => {
    error.value = '';
    info.value = '';
    debugOtp.value = '';
    phoneFieldError.value = '';

    if (!validateWebsiteLocal()) {
        return;
    }

    if (websiteDnsOk.value === false) {
        return;
    }

    loading.value = true;

    try {
        const dnsOk = await ensureWebsiteDns();

        if (!dnsOk) {
            return;
        }

        const { data } = await axios.post(route('landing.download-gate.send-otp'), {
            name: name.value.trim(),
            phone: phone.value.trim(),
            website: website.value.trim(),
        });

        if (! data?.ok) {
            error.value = data?.message || 'OTP পাঠানো যায়নি।';
            startResendCountdown(data?.resend_after ?? 0);

            return;
        }

        if (data.website) {
            website.value = data.website;
        }

        info.value = data.message || 'OTP পাঠানো হয়েছে।';
        debugOtp.value = data.debug_otp || '';
        verifiedWebsite.value = data.website || website.value.trim();
        websiteFieldError.value = '';
        websiteDnsOk.value = true;
        step.value = 'otp';
        startResendCountdown(data.resend_after ?? 60);
    } catch (e) {
        const payload = e?.response?.data || {};
        const fieldErrors = payload.errors || {};
        const errorField = payload.error_field;

        if (errorField === 'website' || fieldErrors.website) {
            websiteDnsOk.value = false;
            websiteFieldError.value = fieldErrors.website || payload.message || '';
        } else if (errorField === 'phone' || fieldErrors.phone) {
            phoneFieldError.value = fieldErrors.phone || payload.message || '';
        } else {
            error.value = payload.message || 'OTP পাঠানো যায়নি। আবার চেষ্টা করুন।';
        }

        startResendCountdown(payload.resend_after ?? 0);
    } finally {
        loading.value = false;
    }
};

const verifyOtp = async () => {
    error.value = '';
    info.value = '';
    loading.value = true;

    try {
        const { data } = await axios.post(route('landing.download-gate.verify-otp'), {
            phone: phone.value.trim(),
            otp: otp.value.trim(),
        });

        downloadToken.value = data.download_token || '';
        verifiedWebsite.value = data.website || verifiedWebsite.value;
        info.value = data.message || 'যাচাই সম্পন্ন।';
        step.value = 'ready';
    } catch (e) {
        error.value = e?.response?.data?.message || 'OTP যাচাই হয়নি।';
    } finally {
        loading.value = false;
    }
};

const gatedUrl = (asset) => {
    if (! downloadToken.value) {
        return '#';
    }

    return route('landing.download-gate.download', {
        asset,
        token: downloadToken.value,
    });
};

const resetFlow = () => {
    step.value = 'form';
    otp.value = '';
    downloadToken.value = '';
    verifiedWebsite.value = '';
    error.value = '';
    websiteFieldError.value = '';
    phoneFieldError.value = '';
    websiteDnsOk.value = null;
    info.value = '';
    debugOtp.value = '';
};
</script>

<template>
    <section id="downloads" class="scroll-mt-24 border-t border-white/10 py-14 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 lg:px-8">
            <div class="text-center">
                <span class="inline-flex rounded-full border border-sky-400/30 bg-sky-500/10 px-3 py-1 text-xs font-bold text-sky-300">
                    ডাউনলোড
                </span>
                <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl">
                    অ্যাপ ও প্লাগইন ডাউনলোড
                </h2>
                <p class="mx-auto mt-3 max-w-xl text-sm text-slate-400 sm:text-base">
                    ডাউনলোডের আগে নাম, ওয়েবসাইট ও মোবাইল নম্বর দিন। ডোমেইন যাচাই ও OTP শেষ হলে ফাইল পাবেন।
                </p>
            </div>

            <div class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-8">
                <p v-if="!hasAnyFile" class="text-center text-sm text-slate-400">
                    ডাউনলোড লিংক এখনো সেট করা হয়নি। Admin → Landing Settings থেকে যোগ করুন।
                </p>

                <template v-else>
                    <!-- Step 1: name + website + phone -->
                    <form
                        v-if="step === 'form'"
                        class="space-y-4"
                        @submit.prevent="sendOtp"
                    >
                        <div>
                            <label for="dl-name" class="text-sm font-semibold text-white">আপনার নাম</label>
                            <input
                                id="dl-name"
                                v-model="name"
                                type="text"
                                required
                                minlength="2"
                                maxlength="120"
                                autocomplete="name"
                                class="mt-2 w-full rounded-xl border border-white/10 bg-[#0a0a0a] px-4 py-3 text-sm text-white outline-none ring-amber-400/40 placeholder:text-slate-500 focus:ring-2"
                                placeholder="যেমন: করিম উদ্দিন"
                            >
                        </div>
                        <div>
                            <label for="dl-website" class="text-sm font-semibold text-white">ওয়েবসাইট / ডোমেইন</label>
                            <input
                                id="dl-website"
                                v-model="website"
                                type="text"
                                required
                                maxlength="255"
                                autocomplete="url"
                                inputmode="url"
                                class="mt-2 w-full rounded-xl border bg-[#0a0a0a] px-4 py-3 text-sm text-white outline-none ring-amber-400/40 placeholder:text-slate-500 focus:ring-2"
                                :class="websiteFieldError ? 'border-rose-500/60' : 'border-white/10'"
                                placeholder="যেমন: shop.example.com"
                                @blur="validateWebsiteOnBlur"
                                @input="markWebsiteDirty"
                            >
                            <p v-if="websiteFieldError" class="mt-1.5 text-xs text-rose-400">{{ websiteFieldError }}</p>
                            <p v-else-if="websiteChecking" class="mt-1.5 text-xs text-sky-400">DNS যাচাই করা হচ্ছে…</p>
                            <p v-else class="mt-1.5 text-xs text-slate-500">শুধু লাইভ স্টোর ডোমেইন দিন — DNS A রেকর্ড যাচাই করা হবে।</p>
                        </div>
                        <div>
                            <label for="dl-phone" class="text-sm font-semibold text-white">মোবাইল নম্বর</label>
                            <input
                                id="dl-phone"
                                v-model="phone"
                                type="tel"
                                required
                                inputmode="numeric"
                                autocomplete="tel"
                                class="mt-2 w-full rounded-xl border bg-[#0a0a0a] px-4 py-3 text-sm text-white outline-none ring-amber-400/40 placeholder:text-slate-500 focus:ring-2"
                                :class="phoneFieldError ? 'border-rose-500/60' : 'border-white/10'"
                                placeholder="01XXXXXXXXX"
                                @input="phoneFieldError = ''"
                            >
                            <p v-if="phoneFieldError" class="mt-1.5 text-xs text-rose-400">{{ phoneFieldError }}</p>
                            <p v-else class="mt-1.5 text-xs text-slate-500">বাংলাদেশি নম্বর — OTP এই নম্বরে যাবে।</p>
                        </div>

                        <p v-if="error" class="text-sm text-rose-400">{{ error }}</p>
                        <p v-if="info" class="text-sm text-emerald-400">{{ info }}</p>

                        <button
                            type="submit"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black transition hover:bg-amber-400 disabled:opacity-60 sm:w-auto"
                            :disabled="loading || websiteChecking || resendAfter > 0"
                        >
                            {{ loading ? 'পাঠানো হচ্ছে…' : (resendAfter > 0 ? `অপেক্ষা ${resendAfter}s` : 'OTP পাঠান') }}
                        </button>
                    </form>

                    <!-- Step 2: OTP -->
                    <form
                        v-else-if="step === 'otp'"
                        class="space-y-4"
                        @submit.prevent="verifyOtp"
                    >
                        <p class="text-sm text-slate-300">
                            <span class="font-semibold text-white">{{ phone }}</span> নম্বরে OTP পাঠানো হয়েছে।
                            <span v-if="verifiedWebsite" class="mt-1 block text-slate-400">
                                ওয়েবসাইট: <span class="text-white">{{ verifiedWebsite }}</span>
                            </span>
                        </p>
                        <div>
                            <label for="dl-otp" class="text-sm font-semibold text-white">৬ সংখ্যার OTP</label>
                            <input
                                id="dl-otp"
                                v-model="otp"
                                type="text"
                                required
                                inputmode="numeric"
                                maxlength="6"
                                autocomplete="one-time-code"
                                class="mt-2 w-full rounded-xl border border-white/10 bg-[#0a0a0a] px-4 py-3 text-center text-lg tracking-[0.35em] text-white outline-none ring-amber-400/40 placeholder:text-slate-500 focus:ring-2"
                                placeholder="••••••"
                            >
                        </div>

                        <p v-if="debugOtp" class="rounded-lg border border-amber-400/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-200">
                            Local debug OTP: <strong>{{ debugOtp }}</strong>
                        </p>
                        <p v-if="error" class="text-sm text-rose-400">{{ error }}</p>
                        <p v-if="info" class="text-sm text-emerald-400">{{ info }}</p>

                        <div class="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-black transition hover:bg-amber-400 disabled:opacity-60"
                                :disabled="loading"
                            >
                                {{ loading ? 'যাচাই হচ্ছে…' : 'OTP যাচাই করুন' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10 disabled:opacity-50"
                                :disabled="loading || resendAfter > 0"
                                @click="sendOtp"
                            >
                                {{ resendAfter > 0 ? `আবার পাঠান (${resendAfter}s)` : 'OTP আবার পাঠান' }}
                            </button>
                            <button
                                type="button"
                                class="text-sm text-slate-400 underline-offset-2 hover:text-white hover:underline"
                                @click="resetFlow"
                            >
                                নম্বর বদলান
                            </button>
                        </div>
                    </form>

                    <!-- Step 3: downloads -->
                    <div v-else class="space-y-5">
                        <div class="rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                            যাচাই সম্পন্ন — এখন ডাউনলোড করতে পারবেন।
                            <span v-if="verifiedWebsite" class="mt-1 block text-emerald-100/80">
                                {{ verifiedWebsite }}
                            </span>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <a
                                v-if="hasApk"
                                :href="gatedUrl('apk')"
                                class="flex items-center gap-3 rounded-xl border border-white/10 bg-[#0a0a0a] p-4 transition hover:border-amber-400/40 hover:bg-white/5"
                            >
                                <img
                                    src="/images/woo-easy-life/apk-download-badge.webp"
                                    alt=""
                                    class="h-14 w-14 shrink-0"
                                    width="256"
                                    height="256"
                                    loading="lazy"
                                >
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-white">Android APK</p>
                                    <p class="mt-0.5 text-xs text-slate-400">মোবাইল অ্যাপ ডাউনলোড</p>
                                </div>
                            </a>

                            <a
                                v-if="hasPlugin"
                                :href="gatedUrl('plugin')"
                                class="flex items-start gap-3 rounded-xl border border-sky-400/20 bg-sky-500/5 p-4 transition hover:border-sky-400/40 hover:bg-sky-500/10"
                            >
                                <img
                                    src="/images/woo-easy-life/plugin-download-badge.webp"
                                    alt=""
                                    class="h-14 w-14 shrink-0"
                                    width="256"
                                    height="249"
                                    loading="lazy"
                                >
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-white">WooCommerce প্লাগইন</p>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-400">
                                        ZIP ডাউনলোড → WP Admin → Plugins → Upload → Activate
                                    </p>
                                </div>
                            </a>
                        </div>

                        <a
                            v-if="playStoreUrl"
                            :href="playStoreUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/15 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            <svg class="h-5 w-5 shrink-0 text-[#3DDC84]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M17.6 9.48 19.44 6.3a.64.64 0 0 0-.26-.85.64.64 0 0 0-.83.22l-1.88 3.24a11.2 11.2 0 0 0-8.94 0L5.65 5.67a.64.64 0 0 0-.87-.2.64.64 0 0 0-.22.83L6.4 9.48A8.98 8.98 0 0 0 1 18h22a8.98 8.98 0 0 0-5.4-8.52ZM7 15.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Zm10 0a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z" />
                            </svg>
                            Google Play
                        </a>

                        <button
                            type="button"
                            class="text-sm text-slate-400 underline-offset-2 hover:text-white hover:underline"
                            @click="resetFlow"
                        >
                            অন্য নম্বর দিয়ে আবার শুরু
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </section>
</template>
