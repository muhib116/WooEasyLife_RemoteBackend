<template>
    <UserLayout
        title="SMS History"
        section="SMS History"
        subtitle="Outbound SMS usage and delivery records"
        :user="user"
    >
        <PageCard
            title="SMS Usage Log"
            :description="`${sms_history.length} sent message${sms_history.length === 1 ? '' : 's'}`"
            no-padding
        >
            <DataTable
                :value="sms_history"
                paginator
                :rows="10"
                :rows-per-page-options="[10, 25, 50]"
                responsive-layout="scroll"
                class="professional-table text-sm"
            >
                <Column header="Created">
                    <template #body="{ data }">
                        {{ dateFormat(data?.created_at) }}
                    </template>
                </Column>
                <Column field="amount" header="Cost">
                    <template #body="{ data }">
                        {{ data.amount }} TK
                    </template>
                </Column>
                <Column field="sms_count" header="Count" />
                <Column field="sms_rate" header="Rate" />
                <Column field="sms_text" header="Message">
                    <template #body="{ data }">
                        <span
                            class="line-clamp-2 max-w-xs text-gray-700 dark:text-gray-300"
                            :title="data.sms_text"
                        >
                            {{ data.sms_text || "—" }}
                        </span>
                    </template>
                </Column>
                <Column field="message_id" header="Message ID" />
                <Column field="note" header="Note" />
            </DataTable>
        </PageCard>

        <Toast />
        <ConfirmDialog id="confirm" />
    </UserLayout>
</template>

<script setup lang="ts">
import UserLayout from "./UserLayout.vue";
import PageCard from "./fragments/PageCard.vue";
import { dateFormat } from "@/Helper";

defineOptions({
    name: "SmsHistory",
});

defineProps<{
    user: any;
    sms_history: any[];
}>();
</script>
