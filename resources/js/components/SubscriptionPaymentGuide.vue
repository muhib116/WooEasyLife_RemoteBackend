<template>
    <section class="rounded-xl border border-gray-100 bg-white dark:border-gray-700 dark:bg-slate-800">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                How to pay
            </h4>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Send money using bKash or Rocket, then submit your transaction details in the form.
            </p>
        </div>

        <div class="space-y-4 p-4">
            <div
                v-for="method in methods"
                :key="method.paymentPartner"
                class="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-700"
            >
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ method.paymentPartner }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Send to
                        <span class="font-mono font-semibold text-primary-600 dark:text-primary-400">
                            {{ method.account }}
                        </span>
                    </p>
                </div>
                <ol class="space-y-2 px-4 py-3">
                    <li
                        v-for="(step, index) in method.steps"
                        :key="index"
                        class="flex gap-3 text-sm text-gray-600 dark:text-gray-300"
                    >
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
                        >
                            {{ index + 1 }}
                        </span>
                        <span class="pt-0.5">{{ step }}</span>
                    </li>
                </ol>
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { useSubscriptionPaymentMethods } from "@/composables/useSubscriptionPaymentMethods";

const methods = useSubscriptionPaymentMethods();
</script>
