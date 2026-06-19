<template>
    <div>
        <EmptyState
            v-if="!user.tokens?.length"
            title="No API tokens"
            description="Generate a token to allow this merchant's storefront to access the API."
            icon="PhKey"
        />

        <DataTable
            v-else
            :value="user.tokens"
            paginator
            :rows="10"
            responsive-layout="scroll"
            class="professional-table text-sm"
        >
            <Column field="title" header="Token">
                <template #body="{ data }">
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ data.title || data.name || "Untitled" }}
                    </div>
                    <div
                        v-if="data.description"
                        class="mt-0.5 line-clamp-1 max-w-xs text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ data.description }}
                    </div>
                </template>
            </Column>
            <Column field="domain" header="Domain">
                <template #body="{ data }">
                    <span
                        class="inline-block max-w-[200px] break-all rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-gray-700 dark:bg-slate-800 dark:text-gray-300"
                    >
                        {{ data.domain || "—" }}
                    </span>
                </template>
            </Column>
            <Column field="last_used_ago" header="Last Used">
                <template #body="{ data }">
                    {{ data.last_used_ago || "Never" }}
                </template>
            </Column>
            <Column field="expires_at" header="Expires">
                <template #body="{ data }">
                    <div>{{ formatExpiresAt(data?.expires_at) }}</div>
                    <div
                        v-if="isExpired(data?.expires_at)"
                        class="text-xs font-medium text-rose-500"
                    >
                        Expired
                    </div>
                </template>
            </Column>
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <StatusBadge
                        :label="data?.status ? 'Enabled' : 'Disabled'"
                        :variant="data?.status ? 'success' : 'neutral'"
                    />
                </template>
            </Column>
            <Column header="Actions" header-class="text-right">
                <template #body="{ data }">
                    <TableActions>
                        <TableActionButton
                            action="copy"
                            tooltip="Copy bearer token"
                            @click="$emit('handleCopy', data)"
                        />
                        <TableActionButton
                            action="edit"
                            tooltip="Edit token"
                            @click="$emit('handleEdit', data)"
                        />
                        <TableActionButton
                            action="delete"
                            tooltip="Delete token"
                            :loading="data?.loading"
                            @click="$emit('handleDeleteToken', data)"
                        />
                    </TableActions>
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<script setup lang="ts">
import { format, isPast, parseISO } from "date-fns";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";

defineProps<{
    user: any;
}>();

defineEmits<{
    handleCopy: [token: any];
    handleEdit: [token: any];
    handleDeleteToken: [token: any];
}>();

function formatExpiresAt(expiresAt: string | null) {
    if (expiresAt === null) {
        return "No expiration";
    }

    return format(new Date(expiresAt), "PPp");
}

function isExpired(expiresAt: string | null) {
    if (!expiresAt) {
        return false;
    }

    try {
        return isPast(parseISO(expiresAt));
    } catch {
        return isPast(new Date(expiresAt));
    }
}
</script>
