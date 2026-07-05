<template>
    <ConfirmDialog class="admin-confirm-dialog" :draggable="false">
        <template #message="{ message: confirmation }">
            <div class="admin-confirm-dialog__content">
                <div
                    class="admin-confirm-dialog__icon-wrap"
                    :class="iconVariant(confirmation)"
                >
                    <i
                        :class="confirmation.icon || 'pi pi-exclamation-triangle'"
                        aria-hidden="true"
                    />
                </div>
                <div class="admin-confirm-dialog__copy">
                    <p class="admin-confirm-dialog__message">
                        {{ confirmation.message }}
                    </p>
                </div>
            </div>
        </template>
    </ConfirmDialog>
</template>

<script setup lang="ts">
type ConfirmOptions = {
    icon?: string;
    acceptClass?: string;
    acceptProps?: { severity?: string };
};

const iconVariant = (confirmation: ConfirmOptions) => {
    const severity = confirmation.acceptProps?.severity;

    if (severity === "success" || confirmation.acceptClass?.includes("success")) {
        return "is-success";
    }

    if (severity === "danger" || confirmation.acceptClass?.includes("danger")) {
        return "is-danger";
    }

    const icon = confirmation.icon ?? "";

    if (icon.includes("replay")) {
        return "is-success";
    }

    return "is-warning";
};
</script>
