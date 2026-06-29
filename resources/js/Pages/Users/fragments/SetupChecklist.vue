<template>
    <PageCard
        :title="cardTitle"
        :description="`${setup.complete} of ${setup.total} required steps complete`"
    >
        <template #actions>
            <Button
                type="button"
                :icon="expanded ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                size="small"
                severity="secondary"
                text
                rounded
                :aria-label="expanded ? 'Collapse setup checklist' : 'Expand setup checklist'"
                @click="expanded = !expanded"
            />
        </template>

        <p
            v-if="!expanded"
            class="text-sm text-gray-600 dark:text-gray-300"
        >
            {{ collapsedSummary }}
        </p>

        <div v-show="expanded" class="space-y-4">
            <div
                v-if="!readyForPlugin && setup.needs_wizard"
                class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100 sm:flex-row sm:items-center sm:justify-between"
            >
                <span>Finish setup with the guided wizard for the first website.</span>
                <Link :href="route('users.setup', userId)">
                    <Button
                        label="Complete Setup"
                        icon="pi pi-play"
                        size="small"
                        as="span"
                    />
                </Link>
            </div>

            <div
                v-if="configuredForPlugin && !readyForPlugin"
                class="flex items-center gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200"
            >
                <Icon name="PhPlugsConnected" class="text-lg" />
                <span>Plan and license are ready. Waiting for the plugin to connect.</span>
            </div>

            <div
                v-if="readyForPlugin"
                class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
            >
                <Icon name="PhCheckCircle" class="text-lg" />
                <span>This merchant has connected the WooCommerce plugin.</span>
            </div>

            <ul class="space-y-3">
                <li
                    v-for="step in setup.steps"
                    :key="step.key"
                    class="flex items-start justify-between gap-3 rounded-xl border border-gray-100 px-4 py-3 dark:border-gray-700/80"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                            :class="
                                step.complete
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                            "
                        >
                            <Icon
                                v-if="step.complete"
                                name="PhCheck"
                                class="text-sm"
                            />
                            <span v-else>!</span>
                        </span>
                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ step.label }}
                            </p>
                            <p
                                v-if="step.hint && !step.complete"
                                class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ step.hint }}
                            </p>
                        </div>
                    </div>
                    <Link
                        v-if="!step.complete && step.action_route"
                        :href="fixUrl(step)"
                    >
                        <Button
                            label="Fix"
                            size="small"
                            severity="secondary"
                            outlined
                            as="span"
                        />
                    </Link>
                </li>
            </ul>
        </div>
    </PageCard>
</template>

<script setup lang="ts">
import { Icon } from "@/plugins";
import { Link } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import PageCard from "./PageCard.vue";

const props = defineProps<{
    userId: number;
    setup: {
        complete: number;
        total: number;
        ready_for_plugin: boolean;
        configured_for_plugin?: boolean;
        needs_wizard?: boolean;
        steps: {
            key: string;
            label: string;
            complete: boolean;
            hint: string | null;
            action_route: string | null;
            action_query?: Record<string, string> | null;
        }[];
    };
}>();

const readyForPlugin = computed(() => props.setup.ready_for_plugin);
const configuredForPlugin = computed(
    () => props.setup.configured_for_plugin ?? false,
);

const needsAttention = computed(
    () =>
        Boolean(props.setup.needs_wizard) ||
        props.setup.complete < props.setup.total,
);

const expanded = ref(needsAttention.value);

const cardTitle = computed(() => {
    if (readyForPlugin.value) {
        return "Plugin connected";
    }

    if (configuredForPlugin.value) {
        return "Configured for plugin";
    }

    return "Setup progress";
});

const collapsedSummary = computed(() => {
    if (readyForPlugin.value) {
        return "All setup steps complete and the WooCommerce plugin is connected.";
    }

    if (configuredForPlugin.value) {
        return "Plan and license are ready. Waiting for the plugin to connect.";
    }

    const incomplete = props.setup.steps.filter((step) => !step.complete);

    if (incomplete.length === 1) {
        return `Next: ${incomplete[0].label}`;
    }

    return `${incomplete.length} setup steps still need attention. Expand to view details.`;
});

const fixUrl = (step: {
    action_route: string | null;
    action_query?: Record<string, string> | null;
}) => {
    if (!step.action_route) {
        return "#";
    }

    return route(step.action_route, {
        user_id: props.userId,
        ...(step.action_query || {}),
    });
};
</script>
