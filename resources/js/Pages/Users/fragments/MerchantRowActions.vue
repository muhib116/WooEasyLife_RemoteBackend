<template>
    <div
        v-if="labeled"
        class="grid gap-2"
        :class="labeledColumns"
    >
        <template v-if="trashed">
            <Button
                label="Restore"
                icon="pi pi-replay"
                size="small"
                severity="success"
                outlined
                class="min-h-11 w-full"
                :loading="restoring"
                @click="$emit('restore')"
            />
            <Button
                label="Delete"
                icon="pi pi-trash"
                size="small"
                severity="danger"
                outlined
                class="min-h-11 w-full"
                :loading="deleting"
                :disabled="isSelf"
                @click="$emit('forceDelete')"
            />
        </template>
        <template v-else>
            <Button
                label="View"
                icon="pi pi-arrow-right"
                size="small"
                outlined
                class="min-h-11 w-full"
                @click="openUser"
            />
            <Button
                v-if="user.role === 'user'"
                label="Edit"
                icon="pi pi-pencil"
                size="small"
                severity="info"
                outlined
                class="min-h-11 w-full"
                @click="$emit('edit')"
            />
            <Button
                label="Delete"
                icon="pi pi-trash"
                size="small"
                severity="danger"
                outlined
                class="min-h-11 w-full"
                :loading="deleting"
                :disabled="isSelf"
                @click="$emit('delete')"
            />
        </template>
    </div>
    <TableActions v-else :align="align">
        <template v-if="trashed">
            <TableActionButton
                action="restore"
                tooltip="Restore user"
                :loading="restoring"
                @click="$emit('restore')"
            />
            <TableActionButton
                action="delete"
                tooltip="Delete forever"
                :loading="deleting"
                :disabled="isSelf"
                @click="$emit('forceDelete')"
            />
        </template>
        <template v-else>
            <TableActionButton
                v-if="user.role === 'user'"
                action="edit"
                tooltip="Edit user"
                @click="$emit('edit')"
            />
            <TableActionButton
                action="delete"
                tooltip="Delete user"
                :loading="deleting"
                :disabled="isSelf"
                @click="$emit('delete')"
            />
            <Link :href="route('users.view', user.id)" aria-label="View user">
                <TableActionButton
                    action="navigate"
                    as="span"
                    tooltip="View user"
                />
            </Link>
        </template>
    </TableActions>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { Link, router } from "@inertiajs/vue3";
import TableActions from "./TableActions.vue";
import TableActionButton from "./TableActionButton.vue";

const props = defineProps<{
    user: { id: number; role?: string };
    trashed?: boolean;
    currentUserId?: number | string | null;
    deleting?: boolean;
    restoring?: boolean;
    align?: "left" | "right";
    labeled?: boolean;
}>();

const labeledColumns = computed(() => {
    if (props.trashed || props.user.role !== "user") {
        return "grid-cols-2";
    }

    return "grid-cols-3";
});

defineEmits<{
    edit: [];
    delete: [];
    restore: [];
    forceDelete: [];
}>();

const openUser = () => {
    router.visit(route("users.view", props.user.id));
};

const isSelf = computed(
    () => Number(props.user.id) === Number(props.currentUserId),
);
</script>
