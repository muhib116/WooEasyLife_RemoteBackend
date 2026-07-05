<template>
    <div
        v-if="!adminLoginUnlocked"
        class="flex min-h-svh cursor-default select-none items-center justify-center bg-slate-950 px-6"
    >
        <Head title="Not found" />

        <div class="text-center">
            <p class="text-6xl font-bold tracking-tight text-slate-800">404</p>
            <p class="mt-3 text-sm text-slate-600">Page not found</p>
        </div>
    </div>

    <GuestLayout v-else>
        <Head title="Log in" />

        <div class="mb-7">
            <h2 class="text-2xl font-bold tracking-tight text-white">
                Welcome back
            </h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-400">
                Sign in to the WooEasyLife admin console for platform operators and support staff.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300"
        >
            <i class="pi pi-check-circle mt-0.5 text-emerald-400" />
            <span>{{ status }}</span>
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
                        placeholder="you@company.com"
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
                label="Sign in"
                icon="pi pi-sign-in"
                class="w-full !border-0 !bg-gradient-to-r !from-amber-500 !to-yellow-500 !font-bold !text-black shadow-lg shadow-amber-900/30 hover:!from-amber-400 hover:!to-yellow-400"
                :loading="form.processing"
                :disabled="form.processing"
            />
        </form>

        <p class="mt-6 text-center text-xs text-slate-500">
            Store owner or team member?
            <Link
                :href="route('merchant.login')"
                class="font-medium text-amber-400 transition hover:text-amber-300"
            >
                Sign in to merchant portal
            </Link>
        </p>
    </GuestLayout>
</template>

<script setup>
import Checkbox from '@/components/Checkbox.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { GuestLayout } from '@/layouts';

const props = defineProps({
    canResetPassword: Boolean,
    status: String,
    adminLoginUnlocked: {
        type: Boolean,
        default: false,
    },
    adminLoginRequiredClicks: {
        type: Number,
        default: 10,
    },
});

const CLICK_RESET_MS = 2000;

const clickCount = ref(0);
const lastClickAt = ref(0);
const unlocking = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const handleGateClick = () => {
    if (props.adminLoginUnlocked || unlocking.value) {
        return;
    }

    const now = Date.now();

    if (lastClickAt.value && now - lastClickAt.value > CLICK_RESET_MS) {
        clickCount.value = 0;
    }

    lastClickAt.value = now;
    clickCount.value += 1;

    if (clickCount.value >= props.adminLoginRequiredClicks) {
        unlocking.value = true;
        router.post(route('login.unlock'), {}, {
            preserveScroll: true,
            onFinish: () => {
                unlocking.value = false;
            },
        });
    }
};

onMounted(() => {
    if (!props.adminLoginUnlocked) {
        window.addEventListener('click', handleGateClick);
    }
});

onUnmounted(() => {
    window.removeEventListener('click', handleGateClick);
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>
