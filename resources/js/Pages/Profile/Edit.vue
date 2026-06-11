<template>
    <AuthenticatedLayout title="Profile">
        <div class="space-y-5">
            <PageHeader
                title="Account Settings"
                description="Manage your profile, password, and account security"
                icon="PhUserCircle"
                icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                icon-class="text-sky-600 dark:text-sky-400"
            />

            <PageCard title="Profile Information" description="Update your name and email address">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </PageCard>

            <PageCard title="Password" description="Ensure your account uses a strong password">
                <UpdatePasswordForm class="max-w-xl" />
            </PageCard>

            <PageCard title="Delete Account" description="Permanently remove your account and data">
                <DeleteUserForm class="max-w-xl" />
            </PageCard>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import DeleteUserForm from "./Partials/DeleteUserForm.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm.vue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});
</script>
