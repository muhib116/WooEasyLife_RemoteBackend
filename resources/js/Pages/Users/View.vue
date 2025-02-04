<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <Header />
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <UserNav :user="user" />

                    <div class="mt-5 flex flex-wrap gap-8">
                        <Widget
                            title="Personal Access Tokens"
                            :value="report.active_api_key"
                        />
                        <Widget
                            title="Sms Balance"
                            :value="`${report.sms_balance}TK`"
                        />
                        <Widget
                            title="Active Package"
                            :value="`${report.active_package}`"
                        />
                        <Widget
                            title="Remaining Order"
                            :value="`${report.remaining_orders}`"
                        />
                    </div>
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import UserNav from "./UserNav.vue";
import Header from "./Header.vue";
import Widget from "./fragments/Widget.vue";

defineOptions({
    name: "UserView",
});

const props = defineProps<{
    user: any;
    report: any;
}>();
</script>
