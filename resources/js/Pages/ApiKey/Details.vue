<template>
    <div>
        <DataTable :value="user.tokens" tableStyle="min-width: 50rem">
            <Column field="name" header="Name" />
            <Column field="email" header="Email" />
            <Column field="last_used_ago" header="Last used ago" />
            <Column field="domain" header="Accessed Domain" />
            <Column field="abilities" header="Abilities" />
            <Column field="expires_at" header="Expires At">
                <template #body="{ data }">
                    {{ formatExpiresAt(data?.expires_at) }}
                </template>
            </Column>
            <Column header="Action" headerClass="text-right w-[12rem]">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button
                            severity="info"
                            size="small"
                            @click="$emit('handleCopy', data)"
                            icon="pi pi-copy"
                        />
                        <Button
                            severity="info"
                            size="small"
                            @click="$emit('handleEdit', data)"
                            icon="pi pi-pencil"
                        />
                        <Button
                            severity="danger"
                            :loading="data?.loading"
                            size="small"
                            @click="$emit('handleDeleteToken', data)"
                            icon="pi pi-trash"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<script setup lang="ts">
import { format } from "date-fns";

defineProps<{
    user: any;
}>();

function formatExpiresAt(expiresAt) {
    if (expiresAt === null) {
        return "No Expiration";
    }

    return format(new Date(expiresAt), "PPp"); // Example: Jan 18, 2025, 12:00 AM
}
</script>
