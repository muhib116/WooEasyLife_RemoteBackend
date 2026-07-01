<template>
    <div
        v-if="rows.length"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-950 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100"
    >
        <p class="font-medium">
            Unresolved WordPress sync issues ({{ rows.length }})
        </p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li
                v-for="row in rows"
                :key="row.id"
            >
                <span class="font-medium">{{ row.employee_name || `Employee #${row.employee_id}` }}</span>
                <span class="text-rose-900/80 dark:text-rose-100/80">
                    — {{ row.display_url || row.domain || `Website #${row.website_id}` }}
                    · {{ actionLabel(row.action) }} failed
                    <template v-if="failureDetail(row)">
                        ({{ failureDetail(row) }})
                    </template>
                    <template v-if="row.retry_scheduled">
                        · retry scheduled
                    </template>
                    <template v-else-if="row.attempt_count >= row.max_attempts">
                        · retries exhausted
                    </template>
                </span>
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

type SyncFailureRow = {
    id: number;
    employee_id: number;
    employee_name?: string | null;
    website_id: number;
    domain?: string | null;
    display_url?: string | null;
    action: string;
    message?: string | null;
    http_status?: number | null;
    attempt_count: number;
    max_attempts: number;
    retry_scheduled: boolean;
};

const page = usePage();

const rows = computed(() => {
    const failures = page.props.recent_sync_failures;

    if (!Array.isArray(failures)) {
        return [] as SyncFailureRow[];
    }

    return failures.filter(
        (row): row is SyncFailureRow =>
            Boolean(row)
            && typeof row === "object"
            && typeof (row as SyncFailureRow).id === "number",
    );
});

const actionLabel = (action?: string) => {
    if (action === "delete") {
        return "Delete";
    }

    return "Sync";
};

const failureDetail = (row: SyncFailureRow) => {
    if (row.message && row.message !== "forward_failed" && row.message !== "forward_exception") {
        return row.http_status ? `${row.message} [HTTP ${row.http_status}]` : row.message;
    }

    if (row.http_status) {
        return `HTTP ${row.http_status}`;
    }

    if (row.message === "forward_exception") {
        return "network/connection error — see hub logs";
    }

    return row.message ?? "";
};
</script>
