<template>
    <form class="flex flex-col gap-5" @submit.prevent="$emit('submit')">
        <FormSection title="Employee profile" step="1" hint="Contact details for this team member.">
            <div class="space-y-4">
                <div class="space-y-1">
                    <label for="employee_name" class="text-sm font-medium">Full name</label>
                    <InputText
                        id="employee_name"
                        v-model="form.name"
                        class="w-full"
                        placeholder="Employee full name"
                    />
                    <p v-if="form.errors.name" class="text-sm text-rose-500">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="employee_phone" class="text-sm font-medium">Phone</label>
                        <InputText
                            id="employee_phone"
                            v-model="form.phone"
                            class="w-full"
                            placeholder="01XXXXXXXXX"
                        />
                        <p v-if="form.errors.phone" class="text-sm text-rose-500">
                            {{ form.errors.phone }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ employeePhoneLoginHint }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <label for="employee_email" class="text-sm font-medium">
                            Email
                            <span class="font-normal text-rose-500">(required)</span>
                        </label>
                        <InputText
                            id="employee_email"
                            v-model="form.email"
                            class="w-full"
                            placeholder="employee@example.com"
                        />
                        <p v-if="form.errors.email" class="text-sm text-rose-500">
                            {{ form.errors.email }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1">
                    <label for="employee_address" class="text-sm font-medium">Address</label>
                    <Textarea
                        id="employee_address"
                        v-model="form.address"
                        rows="2"
                        autoResize
                        class="w-full"
                        placeholder="Street, area, city"
                    />
                    <p v-if="form.errors.address" class="text-sm text-rose-500">
                        {{ form.errors.address }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">
                        Photo
                        <span class="font-normal text-gray-500">(optional)</span>
                    </label>
                    <div class="flex flex-wrap items-center gap-4">
                        <div
                            v-if="photoPreview"
                            class="h-16 w-16 overflow-hidden rounded-full border border-gray-200 dark:border-gray-700"
                        >
                            <img
                                :src="photoPreview"
                                alt="Employee photo preview"
                                class="h-full w-full object-cover"
                            />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <input
                                ref="photoInput"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="onPhotoSelected"
                            />
                            <Button
                                type="button"
                                label="Upload photo"
                                icon="pi pi-upload"
                                size="small"
                                severity="secondary"
                                outlined
                                @click="photoInput?.click()"
                            />
                            <Button
                                v-if="photoPreview || form.remove_photo"
                                type="button"
                                label="Remove"
                                icon="pi pi-times"
                                size="small"
                                severity="secondary"
                                text
                                @click="clearPhoto"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.photo" class="text-sm text-rose-500">
                        {{ form.errors.photo }}
                    </p>
                </div>
            </div>
        </FormSection>

        <FormSection
            title="Role & websites"
            step="2"
            hint="Assign ecommerce responsibilities and one or more store websites."
        >
            <div class="space-y-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Role</label>
                    <Select
                        v-model="form.role_id"
                        :options="roles"
                        option-label="name"
                        option-value="id"
                        placeholder="Select role"
                        class="w-full"
                    />
                    <p
                        v-if="selectedRole?.description"
                        class="text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ selectedRole.description }}
                    </p>
                    <p v-if="form.errors.role_id" class="text-sm text-rose-500">
                        {{ form.errors.role_id }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium">Assigned websites</label>
                    <MultiSelect
                        v-model="form.website_ids"
                        :options="websiteOptions"
                        option-label="label"
                        option-value="value"
                        display="chip"
                        placeholder="Select one or more websites"
                        class="w-full"
                        filter
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Leave empty to allow access across all merchant websites.
                    </p>
                    <p
                        v-if="localDevBaseUrlHint"
                        class="text-xs text-amber-700 dark:text-amber-300"
                    >
                        {{ localDevBaseUrlHint }}
                    </p>
                    <p
                        v-if="unconfiguredWebsiteHint"
                        class="text-xs text-amber-700 dark:text-amber-300"
                    >
                        {{ unconfiguredWebsiteHint }}
                    </p>
                    <p v-if="form.errors.website_ids" class="text-sm text-rose-500">
                        {{ form.errors.website_ids }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2.5 dark:border-gray-800"
                >
                    <div>
                        <p class="text-sm font-medium">Active employee</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Inactive employees are hidden from operational workflows.
                        </p>
                    </div>
                    <ToggleSwitch v-model="form.status" />
                </div>
            </div>
        </FormSection>

        <FormSection title="Internal notes" step="3" optional>
            <Textarea
                v-model="form.notes"
                rows="2"
                autoResize
                class="w-full"
                placeholder="Optional internal notes"
            />
        </FormSection>

        <div class="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                outlined
                @click="$emit('cancel')"
            />
            <Button
                type="submit"
                :label="form.id ? 'Save changes' : 'Create employee'"
                icon="pi pi-check"
                :loading="form.processing"
            />
        </div>
    </form>
</template>

<script setup lang="ts">
import FormSection from "@/components/FormSection.vue";
import type { MerchantEmployeeRoleOption } from "@/data/merchantEmployeeRoles";
import {
    isValidEmployeePhonePassword,
    normalizeEmployeePhone,
} from "@/utils/employeePhonePassword";
import { computed, onBeforeUnmount, ref, watch } from "vue";

const props = defineProps<{
    form: any;
    roles: MerchantEmployeeRoleOption[];
    websites: {
        id: number;
        domain: string;
        title?: string | null;
        base_url?: string | null;
        display_url?: string | null;
        uses_base_url?: boolean;
        sync_configured?: boolean;
    }[];
    existingPhotoUrl?: string | null;
}>();

const emit = defineEmits<{
    submit: [];
    cancel: [];
    "photo-selected": [file: File | null];
    "photo-removed": [];
}>();

const photoInput = ref<HTMLInputElement | null>(null);
const objectPhotoUrl = ref<string | null>(null);

const isLocalDevDomain = (domain: string): boolean => {
    const host = domain.toLowerCase().replace(/^https?:\/\//, "").split("/")[0] ?? "";

    return host === "localhost"
        || host === "127.0.0.1"
        || host.startsWith("localhost:")
        || host.startsWith("127.0.0.1:");
};

const formatWebsiteLabel = (website: (typeof props.websites)[number]): string => {
    const name = website.title
        ? `${website.domain} · ${website.title}`
        : website.domain;

    const label = website.display_url
        ? `${name} — ${website.display_url}`
        : name;

    if (website.sync_configured === false) {
        return `${label} (plugin not connected)`;
    }

    return label;
};

const websiteOptions = computed(() =>
    props.websites.map((website) => ({
        label: formatWebsiteLabel(website),
        value: website.id,
    })),
);

const employeePhoneLoginHint = computed(() => {
    const phone = String(props.form.phone ?? "");
    const normalized = normalizeEmployeePhone(phone);

    if (normalized && isValidEmployeePhonePassword(phone)) {
        return `WordPress login uses email as username and ${normalized} as the default password. Changing the phone updates the password on the next save.`;
    }

    return "WordPress login uses the employee email and a normalized phone password (01XXXXXXXXX).";
});

const localDevBaseUrlHint = computed(() => {
    const selectedIds = Array.isArray(props.form.website_ids)
        ? props.form.website_ids
        : [];

    const relevantWebsites = selectedIds.length
        ? props.websites.filter((website) => selectedIds.includes(website.id))
        : props.websites;

    const needsBaseUrl = relevantWebsites.filter(
        (website) => isLocalDevDomain(website.domain) && !website.base_url,
    );

    if (!needsBaseUrl.length) {
        return "";
    }

    const labels = needsBaseUrl.map((website) => website.display_url || `https://${website.domain}`);

    return `Local development stores without a WordPress base URL will sync using ${labels.join(", ")}. Set the base URL on Websites if WordPress runs on a different port or path.`;
});

const unconfiguredWebsiteHint = computed(() => {
    const selectedIds = Array.isArray(props.form.website_ids)
        ? props.form.website_ids
        : [];

    if (!selectedIds.length) {
        return "";
    }

    const unconfigured = props.websites.filter(
        (website) => selectedIds.includes(website.id) && website.sync_configured === false,
    );

    if (!unconfigured.length) {
        return "";
    }

    const labels = unconfigured.map(
        (website) => website.display_url || website.domain || `Website #${website.id}`,
    );

    return `These stores are not connected to WooEasyLife yet: ${labels.join(", ")}. The employee will be assigned, and WordPress sync will run once the plugin is activated on each store.`;
});

const selectedRole = computed(() =>
    props.roles.find((role) => role.id === props.form.role_id),
);

const photoPreview = computed(() => {
    if (props.form.remove_photo) {
        return null;
    }

    if (objectPhotoUrl.value) {
        return objectPhotoUrl.value;
    }

    return props.existingPhotoUrl ?? null;
});

const onPhotoSelected = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    if (objectPhotoUrl.value) {
        URL.revokeObjectURL(objectPhotoUrl.value);
    }

    objectPhotoUrl.value = URL.createObjectURL(file);
    emit("photo-selected", file);
    props.form.remove_photo = false;
};

const clearPhoto = () => {
    if (objectPhotoUrl.value) {
        URL.revokeObjectURL(objectPhotoUrl.value);
        objectPhotoUrl.value = null;
    }

    emit("photo-removed");

    if (photoInput.value) {
        photoInput.value.value = "";
    }
};

watch(
    () => props.existingPhotoUrl,
    () => {
        if (!props.form.photo) {
            props.form.remove_photo = false;
        }
    },
);

onBeforeUnmount(() => {
    if (objectPhotoUrl.value) {
        URL.revokeObjectURL(objectPhotoUrl.value);
    }
});
</script>
