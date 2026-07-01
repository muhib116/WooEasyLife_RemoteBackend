<template>
    <div class="space-y-4">
        <section class="space-y-3">
            <div
                v-if="!embedded"
                class="flex flex-wrap items-center justify-between gap-2"
            >
                <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Power Full Features
                </h4>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        label="Check all"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="setAllPowerFeatures(true)"
                    />
                    <Button
                        type="button"
                        label="Uncheck all"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="setAllPowerFeatures(false)"
                    />
                </div>
            </div>

            <div
                v-else
                class="flex justify-end gap-2"
            >
                <Button
                    type="button"
                    label="Check all"
                    size="small"
                    severity="secondary"
                    outlined
                    @click="setAllPowerFeatures(true)"
                />
                <Button
                    type="button"
                    label="Uncheck all"
                    size="small"
                    severity="secondary"
                    outlined
                    @click="setAllPowerFeatures(false)"
                />
            </div>

            <div
                class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        v-for="item in powerFeatureDefinitions"
                        :key="item.key"
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-transparent px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-slate-800/60"
                        :class="{
                            'opacity-60': item.key === 'app_store_limit' && !features.app_connect,
                        }"
                    >
                        <Checkbox
                            v-model="features[item.key]"
                            :input-id="`power-${item.key}`"
                            binary
                            :disabled="item.key === 'app_store_limit' && !features.app_connect"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            {{ item.label }}
                        </span>
                    </label>
                </div>
            </div>
        </section>

        <div
            v-if="showWebsiteConnect && features.app_connect"
            class="space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700"
        >
            <div>
                <label
                    for="adjust_total_website_connect"
                    class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                >
                    Total Website Connect
                </label>
                <Select
                    id="adjust_total_website_connect"
                    v-model="websiteConnectLimit"
                    :options="websiteConnectOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Select store limit"
                    class="w-full"
                    :disabled="!features.app_store_limit"
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Enable “অ্যাপ স্টোর লিমিট” to allow more than one store.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    POWER_FULL_FEATURE_DEFINITIONS,
    setAllFeatures,
    WEBSITE_CONNECT_OPTIONS,
} from "@/data/packageCatalogDraft";
import type { PackageFeatures, WebsiteConnectLimit } from "@/types/packageCatalog";
import { watch } from "vue";

withDefaults(
    defineProps<{
        embedded?: boolean;
        showWebsiteConnect?: boolean;
    }>(),
    {
        embedded: false,
        showWebsiteConnect: false,
    },
);

const features = defineModel<PackageFeatures>("features", { required: true });
const websiteConnectLimit = defineModel<WebsiteConnectLimit>("websiteConnectLimit");

const powerFeatureDefinitions = POWER_FULL_FEATURE_DEFINITIONS;
const websiteConnectOptions = WEBSITE_CONNECT_OPTIONS;

function setAllPowerFeatures(enabled: boolean) {
    features.value = setAllFeatures(
        features.value,
        enabled,
        POWER_FULL_FEATURE_DEFINITIONS.map((item) => item.key),
    );
}

watch(
    () => features.value.app_connect,
    (enabled) => {
        if (!websiteConnectLimit.value) {
            return;
        }

        if (!enabled) {
            features.value.app_store_limit = false;
            websiteConnectLimit.value = 1;
        }
    },
);

watch(
    () => features.value.app_store_limit,
    (enabled) => {
        if (!websiteConnectLimit.value || !features.value.app_connect) {
            if (!features.value.app_connect) {
                features.value.app_store_limit = false;
            }

            return;
        }

        if (!enabled) {
            websiteConnectLimit.value = 1;
        } else if (websiteConnectLimit.value === 1) {
            websiteConnectLimit.value = 3;
        }
    },
);

watch(
    () => websiteConnectLimit.value,
    (value) => {
        if (!value || !features.value.app_connect) {
            return;
        }

        features.value.app_store_limit =
            value === "unlimited" || Number(value) > 1;
    },
);
</script>
