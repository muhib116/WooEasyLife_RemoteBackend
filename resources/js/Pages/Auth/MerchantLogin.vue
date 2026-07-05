<template>
    <MerchantGuestLayout>
        <Head title="Merchant sign in" />

        <div class="mb-7">
            <h2 class="text-2xl font-bold tracking-tight text-white">
                Merchant sign in
            </h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-400">
                Access your store dashboard, manage websites, recharge packages, and invite team members.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300"
        >
            <i class="pi pi-check-circle mt-0.5 text-emerald-400" />
            <span>{{ status }}</span>
        </div>

        <div
            v-if="oauthError"
            class="mb-5 flex items-start gap-3 rounded-xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-300"
        >
            <i class="pi pi-exclamation-circle mt-0.5 text-rose-400" />
            <span>{{ oauthError }}</span>
        </div>

        <div v-if="socialProviders.length" class="mb-6 space-y-3">
            <a
                v-if="socialProviders.includes('google')"
                :href="socialRedirectUrl('google')"
                class="flex w-full items-center justify-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] px-4 py-3 text-sm font-semibold text-white transition hover:border-white/20 hover:bg-white/[0.08]"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#EA4335" d="M12 10.2v3.6h5c-.2 1.2-1.6 3.6-5 3.6-3 0-5.5-2.5-5.5-5.5S9 6.4 12 6.4c1.7 0 2.9.7 3.6 1.3l2.5-2.4C16.8 3.9 14.6 3 12 3 7.6 3 4 6.6 4 11s3.6 8 8 8c4.6 0 7.7-3.2 7.7-7.8 0-.5-.1-.9-.2-1.2H12z" />
                    <path fill="#34A853" d="M6.5 14.3 5.2 15.5A7.96 7.96 0 0 0 12 19c2.4 0 4.4-.8 5.9-2.2l-2.9-2.2c-.8.5-1.8.9-3 .9-2.3 0-4.2-1.5-4.9-3.6z" />
                    <path fill="#4A90E2" d="M4 7.5A7.96 7.96 0 0 0 3 11c0 1.3.3 2.5.8 3.6l3.2-2.5C6.8 11.5 6.8 10.5 7 10c0-.5.1-1 .3-1.5L4 7.5z" />
                    <path fill="#FBBC05" d="M12 6.4c1.3 0 2.5.4 3.4 1.2l2.5-2.5C16.9 3.9 14.6 3 12 3 8.7 3 5.8 5.1 4.5 8.1l3.2 2.5c.6-1.9 2.5-3.2 4.3-3.2z" />
                </svg>
                Continue with Google
            </a>

            <a
                v-if="socialProviders.includes('facebook')"
                :href="socialRedirectUrl('facebook')"
                class="flex w-full items-center justify-center gap-3 rounded-xl border border-[#1877F2]/40 bg-[#1877F2]/10 px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#1877F2]/20"
            >
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.845c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.971H15.83c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" />
                </svg>
                Continue with Facebook
            </a>

            <div class="relative flex items-center gap-3 py-1">
                <div class="h-px flex-1 bg-white/10" />
                <span class="text-xs text-slate-500">or sign in with email</span>
                <div class="h-px flex-1 bg-white/10" />
            </div>
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <label for="email" class="text-sm font-medium text-slate-300">
                    Email address
                </label>
                <IconField class="w-full">
                    <InputIcon class="pi pi-envelope text-slate-500" />
                    <InputText
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="w-full"
                        placeholder="you@store.com"
                        required
                        autofocus
                        autocomplete="username"
                        :invalid="!!form.errors.email"
                    />
                </IconField>
                <small v-if="form.errors.email" class="block text-sm text-rose-400">
                    {{ form.errors.email }}
                </small>
            </div>

            <div class="space-y-2">
                <label for="password" class="text-sm font-medium text-slate-300">
                    Password
                </label>
                <Password
                    id="password"
                    v-model="form.password"
                    class="w-full"
                    input-class="w-full"
                    placeholder="Enter your password"
                    :feedback="false"
                    toggle-mask
                    required
                    autocomplete="current-password"
                    :invalid="!!form.errors.password"
                />
                <small v-if="form.errors.password" class="block text-sm text-rose-400">
                    {{ form.errors.password }}
                </small>
            </div>

            <div class="flex items-center justify-between gap-4">
                <label class="flex cursor-pointer items-center gap-2.5">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="text-sm text-slate-400">Remember me</span>
                </label>
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-amber-400 transition hover:text-amber-300"
                >
                    Forgot password?
                </Link>
            </div>

            <Button
                type="submit"
                label="Sign in to portal"
                icon="pi pi-sign-in"
                class="w-full !border-0 !bg-gradient-to-r !from-amber-500 !to-yellow-500 !font-bold !text-black shadow-lg shadow-amber-900/30 hover:!from-amber-400 hover:!to-yellow-400"
                :loading="form.processing"
                :disabled="form.processing"
            />
        </form>

        <div class="mt-7 rounded-xl border border-white/5 bg-white/[0.03] px-3 py-3">
            <p class="text-xs font-semibold text-amber-400">Store owner</p>
            <p class="mt-0.5 text-xs leading-relaxed text-slate-500">
                Full access to billing &amp; websites. New here? Google or Facebook creates your merchant account automatically.
            </p>
        </div>

        <p class="mt-6 text-center text-xs text-slate-500">
            New to WooEasyLife?
            <Link
                :href="route('pricing')"
                class="font-medium text-amber-400 transition hover:text-amber-300"
            >
                View packages &amp; pricing
            </Link>
        </p>
    </MerchantGuestLayout>
</template>

<script setup>
import Checkbox from '@/components/Checkbox.vue';
import MerchantGuestLayout from '@/layouts/MerchantGuestLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    canResetPassword: Boolean,
    status: String,
    socialProviders: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const oauthError = computed(() => {
    if (form.errors.email) {
        return null;
    }

    const errors = page.props.errors ?? {};

    return typeof errors.email === 'string' ? errors.email : null;
});

const redirectQuery = computed(() => {
    const redirect = new URLSearchParams(window.location.search).get('redirect');

    return redirect && redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : null;
});

const socialRedirectUrl = (provider) => {
    const baseUrl = route('merchant.auth.redirect', { provider });

    if (!redirectQuery.value) {
        return baseUrl;
    }

    return `${baseUrl}?redirect=${encodeURIComponent(redirectQuery.value)}`;
};

const submit = () => {
    form.post(route('merchant.login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>
