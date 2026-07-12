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
                    >
                        <Checkbox
                            :model-value="Boolean(features[item.key])"
                            :input-id="`power-${item.key}`"
                            binary
                            @update:model-value="
                                (value) => (features[item.key] = Boolean(value))
                            "
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            {{ item.label }}
                        </span>
                    </label>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import {
    POWER_FULL_FEATURE_DEFINITIONS,
    setAllFeatures,
} from "@/data/packageCatalogDraft";
import type { PackageFeatures } from "@/types/packageCatalog";

withDefaults(
    defineProps<{
        embedded?: boolean;
    }>(),
    {
        embedded: false,
    },
);

const features = defineModel<PackageFeatures>("features", { required: true });

const powerFeatureDefinitions = POWER_FULL_FEATURE_DEFINITIONS;

function setAllPowerFeatures(enabled: boolean) {
    features.value = setAllFeatures(
        features.value,
        enabled,
        POWER_FULL_FEATURE_DEFINITIONS.map((item) => item.key),
    );
}
</script>
