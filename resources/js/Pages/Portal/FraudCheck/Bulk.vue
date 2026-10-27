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

            <PageCard title="New batch" description="One phone number per line, or upload one or more CSVs. 017…, 880…, and +880… of the same number count once.">
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
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="bulk-phones" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Phone numbers (one per row)
                            </label>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                                style="border-color: var(--mp-line); color: var(--mp-ink)"
                                :disabled="starting"
                                @click="fileInput?.click()"
                            >
                                Upload CSVs
                            </button>
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".csv,.txt,text/csv,text/plain"
                                multiple
                                class="sr-only"
                                @change="onFile"
                            />
                        </div>
                        <textarea
                            id="bulk-phones"
                            v-model="rawPhones"
                            rows="8"
                            placeholder="01712345678"
                            class="bulk-phones merchant-field w-full rounded-xl px-3 py-2.5 font-mono text-sm outline-none"
                            :disabled="starting"
                        />
                        <p class="text-xs" :class="countTone">
                            {{ parsed.unique }} / {{ todayCap }} unique
                            <span v-if="parsed.duplicates"> · {{ parsed.duplicates }} duplicate{{ parsed.duplicates === 1 ? '' : 's' }} will be skipped</span>
                            <span v-if="overDaily"> · {{ parsed.unique - quota.remaining }} more than today's limit. Those stay waiting.</span>
                        </p>
                        <p class="text-xs" :class="quota.remaining < 1 ? 'text-rose-600' : 'text-gray-500 dark:text-gray-400'">
                            {{ quota.remaining }} of {{ quota.daily_limit }} successful checks left today. The same number is counted once.
                        </p>
                        <p v-if="fileNote" class="text-xs text-emerald-700 dark:text-emerald-300">{{ fileNote }}</p>
                        <p v-if="fileError" class="text-sm text-rose-600">{{ fileError }}</p>
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
const fileInput = ref(null);
const fileNote = ref("");
const fileError = ref("");

const page = usePage();
const quota = computed(() => page.props.auth?.portal?.courier_history ?? {
    remaining: 0,
    daily_limit: 0,
    used_today: 0,
    enabled: false,
});
const parsed = computed(() => parsePhones(rawPhones.value));
const todayCap = computed(() => Math.min(props.maxNumbers, Number(quota.value.remaining ?? 0)));
const overLimit = computed(() => parsed.value.unique > props.maxNumbers);
const overDaily = computed(() => parsed.value.unique > Number(quota.value.remaining ?? 0));
const countTone = computed(() => (overLimit.value || overDaily.value ? "text-rose-600" : "text-gray-500 dark:text-gray-400"));

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

async function onFile(event) {
    const files = Array.from(event.target.files || []);
    event.target.value = "";
    fileNote.value = "";
    fileError.value = "";
    if (!files.length) return;

    const errors = [];
    const loadedNames = [];
    const collected = [];
    for (const file of files) {
        if (file.size > 1024 * 1024) {
            errors.push(`${file.name} is larger than 1 MB.`);
            continue;
        }
        let text = "";
        try {
            text = await readFileText(file);
        } catch {
            errors.push(`Could not read ${file.name}.`);
            continue;
        }
        const phones = phonesFromFile(text);
        if (!phones.length) {
            errors.push(`No phone numbers found in ${file.name}.`);
            continue;
        }
        collected.push(...phones);
        loadedNames.push(file.name);
    }

    if (loadedNames.length) {
        const { added, skipped } = appendPhones(collected);
        const names = loadedNames.join(", ");
        fileNote.value = added
            ? `Added ${added} number${added === 1 ? "" : "s"} from ${names}.`
            : `No new numbers in ${names}.`;
        if (skipped) fileNote.value += ` ${skipped} duplicate${skipped === 1 ? "" : "s"} skipped.`;
    }
    if (errors.length) fileError.value = errors.join(" ");
}

function readFileText(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ""));
        reader.onerror = () => reject(new Error("read"));
        reader.readAsText(file);
    });
}

function appendPhones(phones) {
    const lines = rawPhones.value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
    const seen = new Set(lines.map((line) => phoneKey(line)));
    const addedLines = [];
    let skipped = 0;
    for (const phone of phones) {
        const key = phoneKey(phone);
        if (seen.has(key)) {
            skipped += 1;
            continue;
        }
        seen.add(key);
        addedLines.push(phone);
    }
    if (addedLines.length) rawPhones.value = [...lines, ...addedLines].join("\n");
    return { added: addedLines.length, skipped };
}

function phoneKey(line) {
    const phone = normalizePhoneDigits(line);
    return /^01[3-9]\d{8}$/.test(phone) ? phone : `invalid:${phone || String(line).trim()}`;
}

function phonesFromFile(text) {
    const phones = [];
    const body = text.replace(/^\uFEFF/, "");
    for (const rawLine of body.split(/\r?\n/)) {
        const line = rawLine.trim();
        if (line === "") continue;
        for (const cell of splitRow(line)) {
            const value = cell.trim().replace(/^["']|["']$/g, "").trim();
            if (looksLikePhone(value)) phones.push(value);
        }
    }
    return phones;
}

function splitRow(line) {
    const cells = [];
    let current = "";
    let quoted = false;
    for (const char of line) {
        if (char === '"') {
            quoted = !quoted;
            continue;
        }
        if (!quoted && (char === "," || char === ";" || char === "\t")) {
            cells.push(current);
            current = "";
            continue;
        }
        current += char;
    }
    cells.push(current);
    return cells;
}

function normalizePhoneDigits(rawValue) {
    const ascii = String(rawValue || "").replace(/[০-৯٠-٩]/g, (ch) => {
        const bn = "০১২৩৪৫৬৭৮৯".indexOf(ch);
        if (bn !== -1) return String(bn);
        const ar = "٠١٢٣٤٥٦٧٨٩".indexOf(ch);
        return ar !== -1 ? String(ar) : ch;
    });
    let digits = ascii.replace(/\D/g, "");
    if (digits.startsWith("00880")) digits = digits.slice(4);
    else if (digits.startsWith("880")) digits = digits.slice(2);
    else if (digits.length === 12 && /^881[3-9]\d{8}$/.test(digits)) digits = "0" + digits.slice(2);
    else if (digits.length === 10 && /^1[3-9]\d{8}$/.test(digits)) digits = "0" + digits;
    return digits;
}

function looksLikePhone(value) {
    const digits = normalizePhoneDigits(value);
    return digits.length >= 10 && digits.length <= 13;
}

function parsePhones(raw) {
    const seen = new Set();
    let submitted = 0;
    for (const line of String(raw).split(/\r?\n/)) {
        if (line.trim() === "") continue;
        submitted += 1;
        seen.add(phoneKey(line));
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
