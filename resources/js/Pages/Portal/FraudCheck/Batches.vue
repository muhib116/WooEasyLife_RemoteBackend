<template>
    <MerchantPortalLayout title="Saved batches">
        <div class="space-y-5">
            <PageHeader
                title="Saved batches"
                description="Open a batch on its own page. Numbers already checked today are not sent to the couriers again."
                icon="PhPackage"
                icon-bg-class="bg-primary-50 dark:bg-primary-500/15"
                icon-class="text-primary-600 dark:text-primary-400"
            />

            <PageCard title="Batches">
                <form class="mb-4 flex flex-wrap items-center gap-2" @submit.prevent="applySearch">
                    <label class="relative min-w-[12rem] flex-1">
                        <span class="sr-only">Search by name</span>
                        <input
                            v-model="query"
                            type="text"
                            maxlength="80"
                            placeholder="Search by name"
                            class="w-full rounded-xl border bg-white px-3 py-1.5 text-sm shadow-sm outline-none dark:bg-[#161616]"
                            style="border-color: var(--mp-line); color: var(--mp-ink)"
                        />
                    </label>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm font-medium"
                        style="border-color: var(--mp-line); color: var(--mp-ink)"
                    >
                        Search
                    </button>
                </form>

                <p v-if="batches.data.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                    <template v-if="filters.q">No batches match that name.</template>
                    <template v-else>
                        No saved batches yet.
                        <Link :href="route('portal.fraud-check')" class="font-medium text-[#8d6a00] dark:text-[#ffd54f]">
                            Start a new batch
                        </Link>
                    </template>
                </p>
                <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="saved in batches.data" :key="saved.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <form v-if="editingId === saved.id" class="flex flex-wrap items-center gap-2" @submit.prevent="saveName(saved)">
                                <input
                                    v-model="editName"
                                    type="text"
                                    maxlength="80"
                                    class="min-w-[12rem] flex-1 rounded-xl border bg-white px-3 py-1.5 text-sm outline-none dark:bg-[#161616]"
                                    style="border-color: var(--mp-line); color: var(--mp-ink)"
                                />
                                <button type="submit" class="text-sm font-medium text-[#8d6a00] dark:text-[#ffd54f]" :disabled="saving">
                                    Save
                                </button>
                                <button type="button" class="text-sm" style="color: var(--mp-muted)" @click="cancelEdit">
                                    Cancel
                                </button>
                            </form>
                            <template v-else>
                                <p class="font-medium text-gray-900 dark:text-white">{{ saved.name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ saved.created_at }} · {{ saved.unique_count }} numbers · {{ saved.status }}
                                </p>
                            </template>
                            <p v-if="editingId === saved.id && renameError" class="mt-1 text-xs text-rose-600">{{ renameError }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="route('portal.fraud-check.batches.show', saved.id)">
                                <Button type="button" label="Open" size="small" severity="secondary" outlined as="span" />
                            </Link>
                            <button type="button" class="text-sm font-medium text-[#8d6a00] dark:text-[#ffd54f]" @click="startEdit(saved)">
                                Rename
                            </button>
                            <button type="button" class="text-sm font-medium text-rose-600" @click="askDelete(saved)">
                                Delete
                            </button>
                        </div>
                    </li>
                </ul>

                <div v-if="batches.prev_page_url || batches.next_page_url" class="mt-4 flex items-center justify-between gap-3">
                    <Link
                        v-if="batches.prev_page_url"
                        :href="batches.prev_page_url"
                        class="text-sm font-medium text-[#8d6a00] dark:text-[#ffd54f]"
                    >
                        Previous
                    </Link>
                    <span v-else />
                    <Link
                        v-if="batches.next_page_url"
                        :href="batches.next_page_url"
                        class="text-sm font-medium text-[#8d6a00] dark:text-[#ffd54f]"
                    >
                        Next
                    </Link>
                </div>
            </PageCard>
        </div>

        <div
            v-if="pendingDelete"
            class="fixed inset-0 z-50 flex items-end justify-center bg-[#1a1408]/45 p-3 backdrop-blur-sm sm:items-center sm:p-6 dark:bg-black/70"
            @click.self="closeDelete"
        >
            <div
                class="w-full max-w-md rounded-2xl border bg-white p-5 shadow-2xl dark:bg-[#111111]"
                style="border-color: var(--mp-line)"
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-batch-title"
            >
                <h2 id="delete-batch-title" class="text-base font-semibold" style="color: var(--mp-ink)">Delete this batch?</h2>
                <p class="mt-2 text-sm" style="color: var(--mp-muted)">
                    <span class="font-medium" style="color: var(--mp-ink)">{{ pendingDelete.name }}</span>
                    and its {{ pendingDelete.unique_count }} {{ pendingDelete.unique_count === 1 ? "number" : "numbers" }} will be removed.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm font-medium disabled:opacity-50"
                        style="border-color: var(--mp-line); color: var(--mp-ink)"
                        :disabled="deleting"
                        @click="closeDelete"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="deleting"
                        @click="remove"
                    >
                        {{ deleting ? "Deleting" : "Delete" }}
                    </button>
                </div>
            </div>
        </div>
    </MerchantPortalLayout>
</template>

<script setup>
import MerchantPortalLayout from "@/layouts/MerchantPortalLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import { Link, router } from "@inertiajs/vue3";
import Button from "primevue/button";
import { onMounted, onUnmounted, ref, watch } from "vue";

const props = defineProps({
    batches: {
        type: Object,
        default: () => ({ data: [], prev_page_url: null, next_page_url: null }),
    },
    filters: {
        type: Object,
        default: () => ({ q: "" }),
    },
});

const query = ref(props.filters.q || "");
const editingId = ref(null);
const editName = ref("");
const renameError = ref("");
const saving = ref(false);
const pendingDelete = ref(null);
const deleting = ref(false);

watch(() => props.filters.q, (value) => {
    query.value = value || "";
});

function applySearch() {
    router.get(route("portal.fraud-check.batches.index"), { q: query.value.trim() }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function startEdit(saved) {
    pendingDelete.value = null;
    editingId.value = saved.id;
    editName.value = saved.name;
    renameError.value = "";
}

function cancelEdit() {
    editingId.value = null;
    renameError.value = "";
}

function saveName(saved) {
    renameError.value = "";
    saving.value = true;
    router.patch(route("portal.fraud-check.batches.update", saved.id), { name: editName.value }, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
        onError: (errors) => {
            renameError.value = errors.name || "Could not rename this batch.";
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

function askDelete(saved) {
    editingId.value = null;
    pendingDelete.value = saved;
}

function closeDelete() {
    if (deleting.value) return;
    pendingDelete.value = null;
}

function onEscape(event) {
    if (event.key === "Escape") closeDelete();
}

function remove() {
    const saved = pendingDelete.value;
    if (!saved || deleting.value) return;
    deleting.value = true;
    router.delete(route("portal.fraud-check.batches.destroy", saved.id), {
        data: { q: props.filters.q || "" },
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            pendingDelete.value = null;
        },
    });
}

onMounted(() => window.addEventListener("keydown", onEscape));
onUnmounted(() => window.removeEventListener("keydown", onEscape));
</script>
