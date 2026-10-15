<template>
    <MerchantPortalLayout title="New batch">
        <div class="space-y-5">
            <PageHeader
                title="New batch"
                description="Duplicates are removed first. Each remaining number is checked one by one and saved. The same number is not sent to the couriers again on the same day."
                icon="PhPackage"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <PageCard title="New batch" description="One phone number per line. 017…, 880…, and +880… of the same number count once.">
                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <label for="batch-name" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            Batch name
                        </label>
                        <input
                            id="batch-name"
                            v-model="batchName"
                            type="text"
                            maxlength="80"
                            class="merchant-field w-full rounded-xl px-3 py-2.5 text-sm outline-none"
                            :disabled="starting"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label for="bulk-phones" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            Phone numbers (one per row)
                        </label>
                        <textarea
                            id="bulk-phones"
                            v-model="rawPhones"
                            rows="8"
                            placeholder="01712345678"
                            class="bulk-phones merchant-field w-full rounded-xl px-3 py-2.5 font-mono text-sm outline-none"
                            :disabled="starting"
                        />
                        <p class="text-xs" :class="overLimit ? 'text-rose-600' : 'text-gray-500 dark:text-gray-400'">
                            {{ parsed.unique }} / {{ maxNumbers }} unique
                            <span v-if="parsed.duplicates"> · {{ parsed.duplicates }} duplicate{{ parsed.duplicates === 1 ? '' : 's' }} will be skipped</span>
                        </p>
                        <p class="text-xs" :class="quota.remaining < 1 ? 'text-rose-600' : 'text-gray-500 dark:text-gray-400'">
                            {{ quota.remaining }} of {{ quota.daily_limit }} successful checks left today. The same number is counted once.
                        </p>
                        <p v-if="formError" class="text-sm text-rose-600">{{ formError }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            label="Start bulk check"
                            icon="pi pi-search"
                            :disabled="parsed.unique === 0 || overLimit || starting || quota.remaining < 1"
                            :loading="starting"
                            @click="start"
                        />
                        <p v-if="starting" class="text-sm text-gray-600 dark:text-gray-300">
                            Opening the results page…
                        </p>
                    </div>
                </div>
            </PageCard>
        </div>
    </MerchantPortalLayout>
</template>

<script setup>
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import { router, usePage } from "@inertiajs/vue3";
import axios from "axios";
import Button from "primevue/button";
import { computed, ref } from "vue";

const props = defineProps({
    maxNumbers: { type: Number, default: 1000 },
});

const batchName = ref(defaultBatchName());
const rawPhones = ref("");
const starting = ref(false);
const formError = ref("");

const page = usePage();
const quota = computed(() => page.props.auth?.portal?.courier_history ?? {
    remaining: 0,
    daily_limit: 0,
    used_today: 0,
    enabled: false,
});
const parsed = computed(() => parsePhones(rawPhones.value));
const overLimit = computed(() => parsed.value.unique > props.maxNumbers);

function defaultBatchName() {
    const parts = new Intl.DateTimeFormat("en-GB", {
        timeZone: "Asia/Dhaka",
        day: "2-digit",
        month: "2-digit",
        year: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23",
    }).formatToParts(new Date());
    const get = (type) => parts.find((part) => part.type === type)?.value ?? "00";
    return `Bulk-${get("day")}-${get("month")}-${get("year")}-${get("hour")}-${get("minute")}`;
}

function parsePhones(raw) {
    const seen = new Set();
    let submitted = 0;
    for (const line of String(raw).split(/\r?\n/)) {
        let phone = line.trim().replace(/\D/g, "");
        if (line.trim() === "") continue;
        submitted += 1;
        if (phone.startsWith("880")) phone = phone.slice(2);
        const key = phone || line.trim();
        seen.add(key);
    }
    return { unique: seen.size, duplicates: submitted - seen.size };
}

async function start() {
    formError.value = "";
    if (parsed.value.unique === 0 || overLimit.value || starting.value) return;
    starting.value = true;
    try {
        const { data } = await axios.post(route("portal.fraud-check.batches.store"), {
            name: batchName.value,
            phones: rawPhones.value,
        });
        router.visit(route("portal.fraud-check.batches.show", data.batch.id));
    } catch (error) {
        formError.value = error?.response?.data?.errors?.phones?.[0]
            || error?.response?.data?.message
            || "Could not start this batch.";
        starting.value = false;
    }
}
</script>

<style scoped>
.bulk-phones:disabled {
    opacity: 0.7;
}
</style>
