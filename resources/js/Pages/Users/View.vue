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
                        <div class="w-[300px] rounded border p-4">
                            <h2>Personal Access Tokens</h2>
                            <div class="text-4xl">
                                {{ report.active_api_key }}
                            </div>
                        </div>
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

defineOptions({
    name: "UserView",
});

const props = defineProps<{
    user: any;
    report: any;
}>();
</script>
