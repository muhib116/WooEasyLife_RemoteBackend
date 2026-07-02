<template>
    <div
        v-if="failedRows.length"
        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
    >
        <p class="font-medium">
            WordPress user sync completed with issues on {{ failedRows.length }} store(s).
        </p>
        <p class="mt-1 text-xs text-amber-900/80 dark:text-amber-100/80">
            Failed stores are logged and will be retried automatically when possible.
        </p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li
                v-for="row in failedRows"
                :key="`${row.website_id}-${row.action}`"
            >
                <span class="font-medium">{{ storeLabel(row) }}</span>
                <span class="text-amber-900/80 dark:text-amber-100/80">
                    — {{ actionLabel(row.action) }} failed
                    <template v-if="failureMessage(row)">
                        ({{ failureMessage(row) }})
                    </template>
                </span>
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

type StoreSyncRow = {
    website_id: number;
    domain?: string;
    display_url?: string;
    action?: string;
    success?: boolean;
    message?: string;
    http_status?: number | null;
};

const page = usePage();

const failedRows = computed(() => {
    const storeSync = page.props.flash?.store_sync;

    if (!Array.isArray(storeSync)) {
        return [] as StoreSyncRow[];
    }

    return storeSync.filter(
        (row): row is StoreSyncRow =>
            Boolean(row)
            && typeof row === "object"
            && (row as StoreSyncRow).success === false,
    );
});

const storeLabel = (row: StoreSyncRow) =>
    row.display_url || row.domain || `Website #${row.website_id}`;

const actionLabel = (action?: string) => {
    if (action === "delete") {
        return "Delete";
    }

    if (action === "sync") {
        return "Sync";
    }

    return "Sync";
};

const failureMessage = (row: StoreSyncRow) => {
    if (row.message === "missing_store_target") {
        return "plugin not connected yet";
    }

    if (row.message && row.message !== "forward_failed" && row.message !== "forward_exception") {
        return row.http_status ? `${row.message} [HTTP ${row.http_status}]` : row.message;
    }

    if (row.http_status) {
        return `HTTP ${row.http_status}`;
    }

    if (row.message === "forward_exception") {
        return "network/connection error — see hub logs";
    }

    if (row.message === "forward_failed") {
        return row.http_status
            ? `store rejected the request [HTTP ${row.http_status}]`
            : "store rejected the request — check plugin license";
    }

    return row.message ?? "";
};
</script>
