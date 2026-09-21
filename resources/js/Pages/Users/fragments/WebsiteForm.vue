<template>
    <form class="flex flex-col gap-5" @submit.prevent="$emit('submit')">
        <p
            class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2.5 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200"
        >
            Changing the store domain keeps the same plan and license key. The merchant must use that same key on the new website.
        </p>

        <FormSection title="Store identity" step="1">
            <div class="space-y-4">
                <div class="space-y-1">
                    <label for="website_domain" class="text-sm font-medium">Store domain</label>
                    <InputText
                        id="website_domain"
                        v-model="form.domain"
                        class="!w-full"
                        placeholder="shop.example.com"
                        :invalid="Boolean(form.errors.domain)"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Use this when the merchant moved or replaced their store URL. Do not change it for a second shop — add a new website instead.
                    </p>
                    <p v-if="form.errors.domain" class="text-sm text-rose-500">
                        {{ form.errors.domain }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label for="website_title" class="text-sm font-medium">Display title</label>
                    <InputText
                        id="website_title"
                        v-model="form.title"
                        class="!w-full"
                        placeholder="My WooCommerce store"
                    />
                    <p v-if="form.errors.title" class="text-sm text-rose-500">
                        {{ form.errors.title }}
                    </p>
                </div>
            </div>
        </FormSection>

        <FormSection
            title="WordPress base URL"
            step="2"
            hint="Optional. Required for local dev when WordPress runs on a port or subdirectory."
        >
            <InputText
                id="website_base_url"
                v-model="form.base_url"
                placeholder="http://localhost:8081/wordpress"
                class="!w-full"
                :invalid="Boolean(form.errors.base_url)"
            />
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Backend API calls use this URL when set. Leave empty to derive from the store domain.
            </p>
            <p v-if="form.errors.base_url" class="text-sm text-rose-500">
                {{ form.errors.base_url }}
            </p>
        </FormSection>

        <FormSection title="Website settings" step="3">
            <div class="space-y-4">
                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2.5 dark:border-gray-800">
                    <div>
                        <p class="text-sm font-medium">Primary website</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Used as the default store when no specific website is selected.
                        </p>
                    </div>
                    <ToggleSwitch v-model="form.is_primary" />
                </div>

                <p v-if="form.errors.is_primary" class="text-sm text-rose-500">
                    {{ form.errors.is_primary }}
                </p>
            </div>
        </FormSection>

        <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
            <Button
                type="button"
                label="Cancel"
                severity="secondary"
                outlined
                size="small"
                @click="$emit('close')"
            />
            <Button
                type="submit"
                label="Save website"
                icon="pi pi-check"
                size="small"
                :loading="form.processing"
                :disabled="form.processing"
            />
        </div>
    </form>
</template>

<script setup lang="ts">
import FormSection from "@/components/FormSection.vue";

defineProps<{
    form: {
        domain: string;
        title: string | null;
        base_url: string | null;
        status: boolean;
        is_primary: boolean;
        processing: boolean;
        errors: Record<string, string>;
    };
}>();

defineEmits<{
    submit: [];
    close: [];
}>();
</script>
