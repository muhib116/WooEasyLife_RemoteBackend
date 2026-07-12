<script setup>
import MerchantPortalLayout from '@/layouts/MerchantPortalLayout.vue';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(route('portal.password.force.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <MerchantPortalLayout title="Change Password">
        <div class="mx-auto max-w-lg space-y-5">
            <div class="rounded-2xl border border-amber-300/40 bg-amber-50 p-5 dark:border-amber-500/30 dark:bg-amber-500/10">
                <h1 class="text-lg font-semibold text-amber-900 dark:text-amber-100">
                    Change your password
                </h1>
                <p class="mt-2 text-sm text-amber-800/90 dark:text-amber-100/80">
                    Your account was created from a landing order with a temporary phone-number password.
                    Set a new password before using the merchant portal.
                </p>
            </div>

            <form
                class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-slate-900"
                @submit.prevent="submit"
            >
                <div>
                    <InputLabel for="current_password" value="Current password (phone number)" />
                    <TextInput
                        id="current_password"
                        v-model="form.current_password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="current-password"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.current_password" />
                </div>

                <div>
                    <InputLabel for="password" value="New password" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel for="password_confirmation" value="Confirm new password" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                        required
                    />
                </div>

                <PrimaryButton :disabled="form.processing">
                    Save new password
                </PrimaryButton>
            </form>
        </div>
    </MerchantPortalLayout>
</template>
