<template>
    <AuthenticatedLayout title="Tutorials">
        <div class="space-y-5">
            <PageHeader
                title="Tutorials"
                description="Manage plugin help videos by page route. Keys must match plugin route names (dashboard, orders, …)."
                icon="PhYoutubeLogo"
                icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                icon-class="text-rose-600 dark:text-rose-400"
            >
                <template #actions>
                    <Button
                        label="Add Category"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreateCategory"
                    />
                </template>
            </PageHeader>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard title="Categories" :value="categories.length" icon="PhFolders" />
                <StatCard
                    title="Videos"
                    :value="totalVideos"
                    icon="PhYoutubeLogo"
                    accent-class="bg-rose-500"
                    icon-bg-class="bg-rose-50 dark:bg-rose-500/15"
                    icon-class="text-rose-600 dark:text-rose-400"
                />
                <StatCard
                    title="Selected"
                    :value="selectedCategory ? selectedCategory.videos.length : 0"
                    icon="PhPlayCircle"
                    subtitle="Videos in active category"
                    accent-class="bg-sky-500"
                    icon-bg-class="bg-sky-50 dark:bg-sky-500/15"
                    icon-class="text-sky-600 dark:text-sky-400"
                />
                <StatCard
                    title="Known Keys"
                    :value="knownKeys.length"
                    icon="PhBracketsCurly"
                    subtitle="Suggested plugin routes"
                    accent-class="bg-violet-500"
                    icon-bg-class="bg-violet-50 dark:bg-violet-500/15"
                    icon-class="text-violet-600 dark:text-violet-400"
                />
            </div>

            <div
                v-if="categories.length"
                class="grid grid-cols-1 gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]"
            >
                <PageCard title="Categories" :description="`${categories.length} route keys`" no-padding>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="cat in categories" :key="cat.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm transition"
                                :class="
                                    selectedId === cat.id
                                        ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800/60'
                                "
                                @click="selectedId = cat.id"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ cat.label }}</span>
                                    <span class="block truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                                        {{ cat.key }}
                                    </span>
                                </span>
                                <Tag :value="String(cat.videos.length)" severity="secondary" />
                            </button>
                        </li>
                    </ul>
                </PageCard>

                <div class="space-y-4">
                    <PageCard
                        v-if="selectedCategory"
                        :title="selectedCategory.label"
                        :description="`JSON key: ${selectedCategory.key}`"
                        no-padding
                    >
                        <template #actions>
                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    label="Edit Category"
                                    icon="pi pi-pencil"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    @click="openEditCategory(selectedCategory)"
                                />
                                <Button
                                    label="Add Video"
                                    icon="pi pi-plus"
                                    size="small"
                                    @click="openCreateVideo"
                                />
                            </div>
                        </template>

                        <DataTable
                            v-if="selectedCategory.videos.length"
                            :value="selectedCategory.videos"
                            class="professional-table text-sm"
                        >
                            <Column header="SL" headerStyle="width:3rem">
                                <template #body="slotProps">
                                    {{ slotProps.index + 1 }}
                                </template>
                            </Column>
                            <Column header="Title" style="min-width: 10rem">
                                <template #body="{ data }">
                                    <span class="text-sm">
                                        {{ data.title?.trim() ? data.title : "—" }}
                                    </span>
                                    <span
                                        v-if="!data.title?.trim()"
                                        class="mt-0.5 block text-xs text-gray-400"
                                    >
                                        Plugin fills title via YouTube oEmbed
                                    </span>
                                </template>
                            </Column>
                            <Column header="Path" style="min-width: 16rem">
                                <template #body="{ data }">
                                    <a
                                        :href="data.path"
                                        target="_blank"
                                        rel="noopener"
                                        class="break-all text-sky-600 hover:underline dark:text-sky-400"
                                    >
                                        {{ data.path }}
                                    </a>
                                </template>
                            </Column>
                            <Column header="Sort" headerStyle="width:4.5rem">
                                <template #body="{ data }">
                                    {{ data.sort_order }}
                                </template>
                            </Column>
                            <Column header="Actions" header-class="text-right" headerStyle="width:9rem">
                                <template #body="{ data }">
                                    <TableActions>
                                        <TableActionButton
                                            action="edit"
                                            tooltip="Edit video"
                                            @click="openEditVideo(data)"
                                        />
                                        <TableActionButton
                                            action="delete"
                                            tooltip="Delete video"
                                            @click="confirmRemoveVideo(data)"
                                        />
                                    </TableActions>
                                </template>
                            </Column>
                        </DataTable>

                        <EmptyState
                            v-else
                            icon="PhYoutubeLogo"
                            title="No videos in this category"
                            description="Add a YouTube URL — same shape as tutorial.json: { title, path }"
                        >
                            <Button
                                label="Add Video"
                                icon="pi pi-plus"
                                size="small"
                                class="mt-4"
                                @click="openCreateVideo"
                            />
                        </EmptyState>
                    </PageCard>

                    <PageCard title="API payload preview" description="What /api/get-tutorials returns in data">
                        <pre
                            class="max-h-72 overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-emerald-300"
                        >{{ payloadJson }}</pre>
                    </PageCard>

                    <div class="flex justify-end">
                        <Button
                            label="Delete Category"
                            icon="pi pi-trash"
                            size="small"
                            severity="danger"
                            outlined
                            :disabled="!selectedCategory"
                            @click="confirmRemoveCategory"
                        />
                    </div>
                </div>
            </div>

            <PageCard v-else title="Tutorials" no-padding>
                <EmptyState
                    icon="PhYoutubeLogo"
                    title="No tutorial categories"
                    description="Create a category using a plugin route key, then add YouTube videos under it."
                >
                    <Button
                        label="Add Category"
                        icon="pi pi-plus"
                        size="small"
                        class="mt-4"
                        @click="openCreateCategory"
                    />
                </EmptyState>
            </PageCard>
        </div>

        <AdminDialog
            v-model:visible="showCategoryForm"
            :header="editingCategory ? 'Edit Category' : 'Add Category'"
            :style="{ width: '32rem' }"
            @hide="resetCategoryForm"
        >
            <form class="space-y-5 p-1" @submit.prevent="submitCategory">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Key (plugin route name)
                    </label>
                    <Select
                        v-model="categoryForm.key"
                        :options="keyOptions"
                        option-label="label"
                        option-value="value"
                        editable
                        filter
                        placeholder="e.g. dashboard"
                        class="w-full"
                    />
                    <small class="mt-1 block text-gray-500 dark:text-gray-400">
                        Must match the Vue route name in the plugin (e.g. missingOrders).
                    </small>
                    <small v-if="categoryForm.errors.key" class="mt-1 block text-rose-500">
                        {{ categoryForm.errors.key }}
                    </small>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Label
                    </label>
                    <InputText
                        v-model="categoryForm.label"
                        class="w-full"
                        placeholder="Orders"
                    />
                    <small v-if="categoryForm.errors.label" class="mt-1 block text-rose-500">
                        {{ categoryForm.errors.label }}
                    </small>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Sort order
                    </label>
                    <InputNumber v-model="categoryForm.sort_order" class="w-full" :min="0" />
                </div>
            </form>

            <template #footer>
                <div class="mt-3 flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="showCategoryForm = false" />
                    <Button
                        label="Save"
                        icon="pi pi-check"
                        severity="info"
                        :loading="categoryForm.processing"
                        @click="submitCategory"
                    />
                </div>
            </template>
        </AdminDialog>

        <AdminDialog
            v-model:visible="showVideoForm"
            :header="editingVideo ? 'Edit Video' : 'Add Video'"
            :style="{ width: '36rem' }"
            @hide="resetVideoForm"
        >
            <form class="space-y-5 p-1" @submit.prevent="submitVideo">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Category
                    </label>
                    <Select
                        v-model="videoForm.tutorial_category_id"
                        :options="categoryOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                    <small v-if="videoForm.errors.tutorial_category_id" class="mt-1 block text-rose-500">
                        {{ videoForm.errors.tutorial_category_id }}
                    </small>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Title (optional)
                    </label>
                    <InputText
                        v-model="videoForm.title"
                        class="w-full"
                        placeholder="Leave empty — plugin uses YouTube title"
                    />
                    <small v-if="videoForm.errors.title" class="mt-1 block text-rose-500">
                        {{ videoForm.errors.title }}
                    </small>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Path (YouTube URL)
                    </label>
                    <InputText
                        v-model="videoForm.path"
                        class="w-full"
                        placeholder="https://www.youtube.com/watch?v=..."
                    />
                    <small class="mt-1 block text-gray-500 dark:text-gray-400">
                        Must be youtube.com/watch?v=… or youtu.be/… (same formats the plugin player supports).
                    </small>
                    <small v-if="videoForm.errors.path" class="mt-1 block text-rose-500">
                        {{ videoForm.errors.path }}
                    </small>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Sort order
                    </label>
                    <InputNumber v-model="videoForm.sort_order" class="w-full" :min="0" />
                </div>
            </form>

            <template #footer>
                <div class="mt-3 flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="showVideoForm = false" />
                    <Button
                        label="Save"
                        icon="pi pi-check"
                        severity="info"
                        :loading="videoForm.processing"
                        @click="submitVideo"
                    />
                </div>
            </template>
        </AdminDialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { useConfirm } from "primevue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatCard from "@/Pages/Users/fragments/StatCard.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import AdminDialog from "@/Pages/Users/fragments/AdminDialog.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";

defineOptions({ name: "TutorialsIndex" });

type TutorialVideoRow = {
    id: number;
    tutorial_category_id: number;
    title: string | null;
    path: string;
    sort_order: number;
};

type TutorialCategoryRow = {
    id: number;
    key: string;
    label: string;
    sort_order: number;
    videos: TutorialVideoRow[];
};

const props = defineProps<{
    categories: TutorialCategoryRow[];
    knownKeys: Array<{ value: string; label: string }>;
    payloadPreview: Record<string, Array<{ title: string; path: string }>> | Record<string, never>;
}>();

const confirm = useConfirm();
const categories = computed(() => props.categories);
const selectedId = ref<number | null>(props.categories[0]?.id ?? null);

watch(
    () => props.categories,
    (list) => {
        if (!list.length) {
            selectedId.value = null;
            return;
        }
        if (!list.some((c) => c.id === selectedId.value)) {
            selectedId.value = list[0].id;
        }
    },
    { deep: true },
);

const selectedCategory = computed(
    () => categories.value.find((c) => c.id === selectedId.value) ?? null,
);

const totalVideos = computed(() =>
    categories.value.reduce((sum, c) => sum + (c.videos?.length ?? 0), 0),
);

const payloadJson = computed(() => JSON.stringify(props.payloadPreview ?? {}, null, 2));

const keyOptions = computed(() => props.knownKeys);

const categoryOptions = computed(() =>
    categories.value.map((c) => ({ value: c.id, label: `${c.label} (${c.key})` })),
);

const showCategoryForm = ref(false);
const editingCategory = ref<{ id: number } | null>(null);
const categoryForm = useForm({
    key: "",
    label: "",
    sort_order: 0 as number | null,
});

watch(
    () => categoryForm.key,
    (key) => {
        if (editingCategory.value || !key) {
            return;
        }
        const known = props.knownKeys.find((item) => item.value === key);
        if (!known) {
            return;
        }
        const labelIsEmpty = !categoryForm.label?.trim();
        const labelMatchesAnotherKnown = props.knownKeys.some(
            (item) => item.label === categoryForm.label && item.value !== key,
        );
        if (labelIsEmpty || labelMatchesAnotherKnown || categoryForm.label === known.label) {
            categoryForm.label = known.label;
        }
    },
);

const showVideoForm = ref(false);
const editingVideo = ref<{ id: number } | null>(null);
const videoForm = useForm({
    tutorial_category_id: null as number | null,
    title: "",
    path: "",
    sort_order: 0 as number | null,
});

const resetCategoryForm = () => {
    categoryForm.reset();
    categoryForm.clearErrors();
    editingCategory.value = null;
};

const openCreateCategory = () => {
    resetCategoryForm();
    categoryForm.sort_order = categories.value.length;
    showCategoryForm.value = true;
};

const openEditCategory = (cat: TutorialCategoryRow) => {
    editingCategory.value = { id: cat.id };
    categoryForm.key = cat.key;
    categoryForm.label = cat.label;
    categoryForm.sort_order = cat.sort_order;
    categoryForm.clearErrors();
    showCategoryForm.value = true;
};

const submitCategory = () => {
    const onSuccess = () => {
        showCategoryForm.value = false;
        resetCategoryForm();
    };

    if (editingCategory.value) {
        categoryForm.put(route("tutorials.categories.update", editingCategory.value.id), {
            onSuccess,
        });
        return;
    }

    categoryForm.post(route("tutorials.categories.store"), { onSuccess });
};

const confirmRemoveCategory = () => {
    const cat = selectedCategory.value;
    if (!cat) return;

    confirm.require({
        header: "Delete category?",
        message: `Delete "${cat.label}" (${cat.key}) and all its videos? Plugin pages using this key will have no tutorials.`,
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Cancel",
        acceptLabel: "Delete",
        acceptClass: "p-button-danger",
        accept: () => {
            useForm({}).delete(route("tutorials.categories.destroy", cat.id), {
                onSuccess: () => {
                    selectedId.value = null;
                },
            });
        },
    });
};

const resetVideoForm = () => {
    videoForm.reset();
    videoForm.clearErrors();
    editingVideo.value = null;
};

const openCreateVideo = () => {
    resetVideoForm();
    videoForm.tutorial_category_id = selectedCategory.value?.id ?? null;
    videoForm.sort_order = selectedCategory.value?.videos.length ?? 0;
    showVideoForm.value = true;
};

const openEditVideo = (video: TutorialVideoRow) => {
    editingVideo.value = { id: video.id };
    videoForm.tutorial_category_id = video.tutorial_category_id;
    videoForm.title = video.title ?? "";
    videoForm.path = video.path;
    videoForm.sort_order = video.sort_order;
    videoForm.clearErrors();
    showVideoForm.value = true;
};

const submitVideo = () => {
    const onSuccess = () => {
        showVideoForm.value = false;
        resetVideoForm();
    };

    if (editingVideo.value) {
        videoForm.put(route("tutorials.videos.update", editingVideo.value.id), { onSuccess });
        return;
    }

    videoForm.post(route("tutorials.videos.store"), { onSuccess });
};

const confirmRemoveVideo = (video: TutorialVideoRow) => {
    confirm.require({
        header: "Delete video?",
        message: `Remove this video from the playlist?\n${video.path}`,
        icon: "pi pi-exclamation-triangle",
        rejectLabel: "Cancel",
        acceptLabel: "Delete",
        acceptClass: "p-button-danger",
        accept: () => {
            useForm({}).delete(route("tutorials.videos.destroy", video.id));
        },
    });
};
</script>
