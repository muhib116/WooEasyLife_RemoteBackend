<template>
    <div class="flex min-h-svh bg-slate-100 dark:bg-slate-950">
        <div
            class="relative hidden w-[45%] overflow-hidden bg-gradient-to-br from-primary-600 via-primary-500 to-primary-700 lg:flex lg:flex-col lg:justify-between lg:p-12"
        >
            <div class="absolute inset-0 opacity-10">
                <div class="absolute -left-20 -top-20 h-80 w-80 rounded-full bg-white/30 blur-3xl" />
                <div class="absolute -bottom-20 -right-20 h-96 w-96 rounded-full bg-white/20 blur-3xl" />
            </div>
            <div class="relative">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">
                        <img src="/app-logo" alt="" class="h-8 w-8 object-contain" />
                    </div>
                    <div>
                        <p class="text-lg font-bold text-white">WooEasyLife</p>
                        <p class="text-xs text-white/70">WooEasyLife Admin</p>
                    </div>
                </div>
            </div>
            <div class="relative space-y-4">
                <h1 class="text-3xl font-bold leading-tight text-white">
                    Manage merchants, couriers &amp; APIs in one place
                </h1>
                <p class="max-w-md text-sm leading-relaxed text-white/80">
                    Commercial-grade platform for Bangladesh e-commerce — fraud checks,
                    SMS, Steadfast, Pathao, and package billing.
                </p>
            </div>
            <p class="relative text-xs text-white/50">
                &copy; {{ new Date().getFullYear() }} WooEasyLife
            </p>
        </div>

        <div class="flex flex-1 flex-col items-center justify-center px-4 py-10 sm:px-8">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 dark:bg-primary-500/15">
                    <img src="/app-logo" alt="" class="h-7 w-7 object-contain" />
                </div>
                <div>
                    <p class="font-bold text-gray-900 dark:text-white">WooEasyLife</p>
                    <p class="text-xs text-gray-500">Admin sign in</p>
                </div>
            </div>

            <div
                class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8 dark:border-gray-800 dark:bg-slate-900"
            >
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts"></script>
