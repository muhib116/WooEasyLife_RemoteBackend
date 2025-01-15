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
                                            "do MMMM yyyy, h:mm a"
                                        )
                                    }}
                                </template>
                            </Column>
                            <Column field="updated_at" header="Updated At">
                                <template #body="{ data }">
                                    {{
                                        format(
                                            new Date(data?.updated_at || null),
                                            "do MMMM yyyy, h:mm a"
                                        )
                                    }}
                                </template>
                            </Column>
                            <Column field="created_by" header="Created By">
                                <template #body="{ data }">
                                    <span
                                        class="py-1 px-4 bg-blue-100 text-blue-800 rounded-full"
                                    >
                                        {{ data.creator?.name }}
                                    </span>
                                </template>
                            </Column>
                            <Column header="Actions">
                                <template #body="{ data }">
                                    <a
                                        :href="
                                            route(
                                                'plugins.downloadVersion',
                                                data.version
                                            )
                                        "
                                        download
                                    >
                                        <Button
                                            class="!size-8"
                                            icon="pi pi-cloud-download"
                                        />
                                    </a>
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
            :style="{ width: '35rem' }"
            :draggable="true"
        >
            <form @submit.prevent="handleCreate">
                <div class="flex items-center gap-4 mb-8">
                    <div for="version" class="font-semibold w-24">Version</div>
                    <div class="flex-auto relative">
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
                <div class="flex items-center gap-4 mb-8">
                    <div for="version" class="font-semibold w-24">Version</div>
                    <div class="flex-auto relative">
                        <Textarea
                            class="w-full"
                            v-model="form.settings"
                            rows="5"
                            cols="30"
                        />
                        <span
                            v-if="form.errors.version"
                            class="absolute -bottom-6 left-0 text-red-500"
                            >{{ form.errors.version }}</span
                        >
                    </div>
                </div>
                <div class="flex items-center gap-4 mb-8">
                    <div for="version" class="font-semibold w-24">File</div>
                    <div class="flex-auto relative">
                        <label
                            class="inline-flex items-center gap-2 bg-slate-200 px-4 py-1 rounded hover:bg-slate-300 cursor-pointer"
                        >
                            <i class="pi pi-cloud-upload !text-xl" />
                            Browse
                            <input
                                type="file"
                                class="hidden"
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
import { useForm } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { get } from "lodash";
import { format } from "date-fns";

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

const handleFileSelect = (event) => {
    const file = get(event, "target.files[0]");
    if (file) {
        form.file = file;
        form.errors.file = "";
    }
};

const handleCreate = () => {
    form.post(route("plugins.createVersion"), {
        onSuccess(event) {
            if (!Object.keys(event.props?.errors || {}).length) {
                form.reset();
                showForm.value = false;
            }
        },
    });
};
</script>
