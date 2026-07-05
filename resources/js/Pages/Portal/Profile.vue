<template>
    <MerchantPortalLayout title="Profile">
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
                    update-route="portal.profile.update"
                    class="max-w-xl"
                />
            </PageCard>

            <PageCard title="Password" description="Ensure your account uses a strong password">
                <UpdatePasswordForm class="max-w-xl" />
            </PageCard>

            <PageCard title="Delete Account" description="Permanently remove your account and data">
                <DeleteUserForm destroy-route="portal.profile.destroy" class="max-w-xl" />
            </PageCard>
        </div>
    </MerchantPortalLayout>
</template>

<script setup>
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import DeleteUserForm from "@/Pages/Profile/Partials/DeleteUserForm.vue";
import UpdatePasswordForm from "@/Pages/Profile/Partials/UpdatePasswordForm.vue";
import UpdateProfileInformationForm from "@/Pages/Profile/Partials/UpdateProfileInformationForm.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});
</script>
