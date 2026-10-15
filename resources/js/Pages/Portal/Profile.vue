<template>
    <MerchantPortalLayout title="Profile">
        <div class="space-y-5">
            <PageHeader
                title="Account Settings"
                description="Update your name and email address"
                icon="PhUserCircle"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <PageCard title="Profile Information" description="Update your name and email address">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    update-route="portal.profile.update"
                    class="max-w-xl"
                />
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
import UpdateProfileInformationForm from "@/Pages/Profile/Partials/UpdateProfileInformationForm.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});
</script>
