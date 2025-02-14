<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between">
                    Plugins Versions
                    <Button
                        icon="pi pi-plus"
                        label="Create"
                        @click="showForm = true"
                    />
                </div>
            </template>
            <template #content>
                <div class="min-h-[400px]">
                    <div>
                        <DataTable :value="versions" class="!bg-red-500">
                            <Column field="version" header="Version"></Column>
                            <Column
                                field="download_count"
                                header="Download Count"
                            ></Column>
                            <Column field="created_at" header="Created At">
                                <template #body="{ data }">
                                    {{
                                        format(
                                            new Date(data?.created_at || null),
                                            "do MMMM yyyy, h:mm a",
                                        )
                                    }}
                                </template>
                            </Column>
                            <Column field="updated_at" header="Updated At">
                                <template #body="{ data }">
                                    {{
                                        format(
                                            new Date(data?.updated_at || null),
                                            "do MMMM yyyy, h:mm a",
                                        )
                                    }}
                                </template>
                            </Column>
                            <Column field="created_by" header="Created By">
                                <template #body="{ data }">
                                    <span
                                        class="rounded-full bg-blue-100 px-4 py-1 text-blue-800"
                                    >
                                        {{ data.creator?.name }}
                                    </span>
                                </template>
                            </Column>
                            <Column header="Actions">
                                <template #body="{ data }">
                                    <div class="flex gap-3">
                                        <a
                                            :href="
                                                route(
                                                    'plugins.downloadVersion',
                                                    data.version,
                                                )
                                            "
                                            download
                                        >
                                            <Button
                                                class="!size-8"
                                                icon="pi pi-cloud-download"
                                            />
                                        </a>
                                        <Button
                                            @click="handleEdit(data)"
                                            class="!size-8"
                                            severity="help"
                                            icon="pi pi-file-edit"
                                        />
                                        <Button
                                            @click="handleDelete(data.id)"
                                            class="!size-8"
                                            severity="danger"
                                            icon="pi pi-trash"
                                        />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </template>
        </Card>
        <Dialog
            v-model:visible="showForm"
            :header="`${form.id ? 'Edit' : 'Create'} Version`"
            modal
            maximizable
            :style="{ width: '35rem' }"
            :draggable="true"
        >
            <form @submit.prevent="handleCreate">
                <div class="mb-8 flex items-center gap-4">
                    <div for="version" class="w-24 font-semibold">Version</div>
                    <div class="relative flex-auto">
                        <InputText
                            v-model="form.version"
                            @update:model-value="
                                () => (form.errors.version = '')
                            "
                            id="version"
                            class="!w-full"
                            autocomplete="off"
                        />
                        <span
                            v-if="form.errors.version"
                            class="absolute -bottom-6 left-0 text-red-500"
                            >{{ form.errors.version }}</span
                        >
                    </div>
                </div>
                <div class="mb-8 flex items-center gap-4">
                    <div for="version" class="w-24 font-semibold">Version</div>
                    <div class="relative flex-auto">
                        <!-- <CodeEditor.Base
                            class="w-full"
                            v-model="form.settings"
                        /> -->
                        <textarea
                            class="w-full"
                            rows="8"
                            @input="
                                (ev) => {
                                    form.settings = ev.target?.value;
                                }
                            "
                            >{{ form.settings }}</textarea
                        >
                        <span
                            v-if="form.errors.settings"
                            class="absolute -bottom-6 left-0 text-red-500"
                            >{{ form.errors.settings }}</span
                        >
                    </div>
                </div>
                <div class="mb-8 flex items-center gap-4">
                    <div for="version" class="w-24 font-semibold">File</div>
                    <div class="relative flex-auto">
                        <label
                            class="inline-flex cursor-pointer items-center gap-2 rounded bg-slate-200 px-4 py-1 hover:bg-slate-300 dark:bg-indigo-600"
                        >
                            <i class="pi pi-cloud-upload !text-xl" />
                            Browse
                            <input
                                type="file"
                                class="hidden"
                                accept=".zip"
                                @change="handleFileSelect"
                            />
                        </label>
                        <span
                            v-if="form.errors.file"
                            class="absolute -bottom-6 left-0 text-red-500"
                            >{{ form.errors.file }}</span
                        >
                        <div>
                            {{ fileName }}
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        @click="showForm = false"
                    ></Button>
                    <Button
                        type="submit"
                        :label="form.id ? 'Update' : 'Create'"
                        :loading="form.processing"
                        @click="handleCreate"
                    ></Button>
                </div>
            </form>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { PluginsVersion } from "@/types";
import { router, useForm } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { get } from "lodash";
import { format } from "date-fns";
import { CodeEditor } from "@/plugins";

defineOptions({
    name: "Plugins",
});

const props = defineProps<{
    plugins_link: string;
    versions: PluginsVersion[];
}>();

const form = useForm({
    id: null,
    version: "",
    file: null,
    settings: null,
});

const fileName = computed(() => {
    return get(form, "file.name");
});

const showForm = ref(false);

watch(showForm, () => {
    if (!showForm.value) {
        form.reset();
    }
});

const handleEdit = (item: PluginsVersion) => {
    form.id = item.id;
    form.version = item.version;
    form.settings = item.settings;
    showForm.value = true;
};

const handleFileSelect = (event) => {
    const file = get(event, "target.files[0]");
    if (file) {
        form.file = file;
        form.errors.file = "";
    }
};

const handleDelete = (id) => {
    if (confirm("Do you want to delete this?")) {
        router.post(route("plugins.deleteVersion", id));
    }
};

const handleCreate = () => {
    if (form.id) {
        form.settings = form.settings;
        form.post(route("plugins.updateVersion", form.id), {
            onSuccess(event) {
                if (!Object.keys(event.props?.errors || {}).length) {
                    form.reset();
                    showForm.value = false;
                }
            },
        });
    } else {
        form.post(route("plugins.createVersion"), {
            onSuccess(event) {
                if (!Object.keys(event.props?.errors || {}).length) {
                    form.reset();
                    showForm.value = false;
                }
            },
        });
    }
};
</script>
