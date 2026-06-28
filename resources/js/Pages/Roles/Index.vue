<template>
    <AuthenticatedLayout title="Roles & Access">
        <div class="space-y-5">
            <PageHeader
                title="Roles & Access"
                description="Platform admin roles and permission assignments"
                icon="PhShieldCheck"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <PageCard title="Platform Admins" description="Assign a role to each admin account">
                <DataTable
                    :value="admins"
                    responsive-layout="scroll"
                    class="professional-table text-sm"
                >
                    <Column field="name" header="Name" />
                    <Column field="email" header="Email" />
                    <Column header="Role">
                        <template #body="{ data }">
                            <Select
                                :model-value="data.admin_role_id"
                                :options="roleOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Super Admin (full access)"
                                show-clear
                                class="w-full max-w-xs"
                                :disabled="!canManageRoles"
                                @update:model-value="
                                    (value) => assignRole(data.id, value)
                                "
                            />
                        </template>
                    </Column>
                </DataTable>
            </PageCard>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <PageCard
                    v-for="role in roles"
                    :key="role.id"
                    :title="role.name"
                    :description="role.description || role.slug"
                >
                    <p class="mb-3 text-xs text-gray-500">
                        {{ role.admin_count }} admin(s) ·
                        {{ role.permissions.length }} permission(s)
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Tag
                            v-for="permission in role.permissions"
                            :key="permission"
                            :value="permission"
                            severity="secondary"
                        />
                    </div>
                    <p
                        v-if="role.slug === 'super-admin'"
                        class="mt-3 text-xs text-amber-600"
                    >
                        Super Admin always has full access. Legacy admins without
                        a role assignment are treated as Super Admin.
                    </p>
                </PageCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from "@/layouts/AuthenticatedLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import { router } from "@inertiajs/vue3";
import { computed } from "vue";

defineOptions({
    name: "RolesIndex",
});

const props = defineProps<{
    roles: Array<{
        id: number;
        name: string;
        slug: string;
        description?: string;
        permissions: string[];
        admin_count: number;
    }>;
    admins: Array<{
        id: number;
        name: string;
        email: string;
        admin_role_id: number | null;
    }>;
    canManageRoles: boolean;
}>();

const canManageRoles = computed(() => props.canManageRoles);

const roleOptions = computed(() =>
    props.roles
        .filter((role) => role.slug !== "super-admin")
        .map((role) => ({
            label: role.name,
            value: role.id,
        })),
);

const assignRole = (userId: number, adminRoleId: number | null) => {
    if (!canManageRoles.value) {
        return;
    }

    router.post(route("roles.assignAdmin", userId), {
        admin_role_id: adminRoleId,
    });
};
</script>
