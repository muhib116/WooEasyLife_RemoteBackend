<template>
    <MerchantPortalLayout title="Change password">
        <div class="space-y-5">
            <PageHeader
                title="Change password"
                description="Use a password you have not used on this account before. At least 8 characters."
                icon="PhLockKey"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <PageCard title="Password" description="Your current password is required before a new one is saved.">
                <form class="max-w-xl space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <label for="current_password" class="text-sm font-medium" style="color: var(--mp-ink)">
                            Current password
                        </label>
                        <Password
                            id="current_password"
                            v-model="form.current_password"
                            class="w-full"
                            input-class="w-full"
                            :feedback="false"
                            toggle-mask
                            autocomplete="current-password"
                            :invalid="!!form.errors.current_password"
                            required
                        />
                        <small v-if="form.errors.current_password" class="block text-sm text-rose-600 dark:text-rose-400">
                            {{ form.errors.current_password }}
                        </small>
                    </div>

                    <div class="space-y-2">
                        <label for="password" class="text-sm font-medium" style="color: var(--mp-ink)">
                            New password
                        </label>
                        <Password
                            id="password"
                            v-model="form.password"
                            class="w-full"
                            input-class="w-full"
                            toggle-mask
                            autocomplete="new-password"
                            :invalid="!!form.errors.password"
                            required
                        />
                        <small v-if="form.errors.password" class="block text-sm text-rose-600 dark:text-rose-400">
                            {{ form.errors.password }}
                        </small>
                    </div>

                    <div class="space-y-2">
                        <label for="password_confirmation" class="text-sm font-medium" style="color: var(--mp-ink)">
                            Confirm new password
                        </label>
                        <Password
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            class="w-full"
                            input-class="w-full"
                            :feedback="false"
                            toggle-mask
                            autocomplete="new-password"
                            :invalid="!!form.errors.password_confirmation"
                            required
                        />
                        <small v-if="form.errors.password_confirmation" class="block text-sm text-rose-600 dark:text-rose-400">
                            {{ form.errors.password_confirmation }}
                        </small>
                    </div>

                    <Button
                        type="submit"
                        label="Save password"
                        icon="pi pi-lock"
                        size="small"
                        :loading="form.processing"
                        :disabled="form.processing"
                    />
                </form>
            </PageCard>
        </div>
    </MerchantPortalLayout>
</template>

<script setup>
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import { useForm } from "@inertiajs/vue3";
import Password from "primevue/password";

const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.put(route("portal.settings.password.update"), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset("password", "password_confirmation");
            }
            if (form.errors.current_password) {
                form.reset("current_password");
            }
        },
    });
};
</script>
