<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Google Analytics API
                </p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    One-click OAuth ·
                    <code class="text-[11px]">GOOGLE_CLIENT_ID</code>
                </p>
            </div>
            <span
                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                :class="ready
                    ? 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-200'
                    : 'bg-amber-500/15 text-amber-900 dark:text-amber-200'"
            >
                {{ ready ? 'Ready' : 'Not ready' }}
            </span>
        </div>

        <div
            v-if="canEditProperty"
            class="space-y-1.5 rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-600"
        >
            <label class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                GA4 Property ID
                <span
                    v-if="propertySource"
                    class="ml-1 rounded bg-slate-100 px-1 py-0.5 text-[10px] uppercase dark:bg-slate-700"
                >{{ propertySource }}</span>
            </label>
            <div class="flex flex-wrap gap-2">
                <input
                    v-model="propertyDraft"
                    type="text"
                    class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900"
                    placeholder="123456789 or G-XXXX"
                    :disabled="disabled || savingProperty"
                    @keyup.enter="saveProperty"
                >
                <Button
                    label="Save"
                    icon="pi pi-check"
                    size="small"
                    severity="secondary"
                    outlined
                    :loading="savingProperty"
                    :disabled="disabled || savingProperty || !propertyDirty"
                    @click="saveProperty"
                />
            </div>
        </div>

        <div
            v-if="canEditMeasurement"
            class="space-y-2 rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-600"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <label class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                    Public gtag Measurement ID
                    <span
                        v-if="measurementSource"
                        class="ml-1 rounded bg-slate-100 px-1 py-0.5 text-[10px] uppercase dark:bg-slate-700"
                    >{{ measurementSource }}</span>
                </label>
                <span
                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                    :class="publicGtagActive
                        ? 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-200'
                        : 'bg-slate-500/15 text-slate-700 dark:text-slate-300'"
                >
                    {{ publicGtagActive ? 'Live on site' : 'Not injecting' }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                Controls the visitor-facing <code class="text-[10px]">gtag.js</code> snippet (separate from API Property ID).
            </p>
            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                <input
                    v-model="measurementEnabled"
                    type="checkbox"
                    class="rounded border-slate-300"
                    :disabled="disabled || savingMeasurement"
                >
                Enable public Google Analytics tag
            </label>
            <div class="flex flex-wrap gap-2">
                <input
                    v-model="measurementDraft"
                    type="text"
                    class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900"
                    placeholder="G-V3TDVR7ED9"
                    :disabled="disabled || savingMeasurement"
                    @keyup.enter="saveMeasurement"
                >
                <Button
                    label="Save"
                    icon="pi pi-check"
                    size="small"
                    severity="secondary"
                    outlined
                    :loading="savingMeasurement"
                    :disabled="disabled || savingMeasurement || !measurementDirty"
                    @click="saveMeasurement"
                />
            </div>
        </div>

        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2 text-xs">
            <div
                v-for="row in rows"
                :key="row.label"
                class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-600"
            >
                <dt class="text-slate-500 dark:text-slate-400">{{ row.label }}</dt>
                <dd
                    class="font-medium"
                    :class="row.ok ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300'"
                >
                    {{ row.value }}
                </dd>
            </div>
        </dl>

        <p
            v-if="!ready"
            class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
        >
            <template v-if="!hasRefreshToken && canConnect">
                Click <strong>Connect Google Analytics</strong> once, approve Google — the token saves automatically.
                Also save the GA4 Property ID above (or on Blog AI Settings).
            </template>
            <template v-else-if="!canConnect">
                Set <code class="text-[11px]">GOOGLE_CLIENT_ID</code> and
                <code class="text-[11px]">GOOGLE_CLIENT_SECRET</code> first.
            </template>
            <template v-else>
                Save your GA4 numeric property ID above, then probe.
            </template>
        </p>

        <div class="flex flex-wrap gap-2">
            <Button
                v-if="canConnect && connectUrl"
                as="a"
                :href="connectUrl"
                :label="hasRefreshToken ? 'Reconnect Google Analytics' : 'Connect Google Analytics'"
                icon="pi pi-google"
                size="small"
                :disabled="disabled"
            />
            <Button
                v-if="disconnectUrl"
                label="Disconnect"
                icon="pi pi-times"
                size="small"
                severity="secondary"
                outlined
                :disabled="disabled || disconnecting"
                :loading="disconnecting"
                @click="disconnect"
            />
            <Button
                v-if="showProbeButton"
                label="Probe GA API"
                icon="pi pi-bolt"
                size="small"
                severity="secondary"
                outlined
                :loading="probing"
                :disabled="disabled || probing"
                @click="$emit('probe')"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import Button from 'primevue/button';
import { useConfirm } from 'primevue';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    data: { type: Object, default: null },
    probing: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    showProbeButton: { type: Boolean, default: true },
});

const emit = defineEmits(['probe', 'disconnected', 'updated']);

const toast = useToast();
const confirm = useConfirm();
const disconnecting = ref(false);
const savingProperty = ref(false);
const savingMeasurement = ref(false);
const propertyDraft = ref(props.data?.property_id || '');
const measurementDraft = ref(props.data?.measurement_id || '');
const measurementEnabled = ref(props.data?.measurement_enabled !== false);

watch(
    () => props.data?.property_id,
    (next) => {
        propertyDraft.value = next || '';
    },
);

watch(
    () => [props.data?.measurement_id, props.data?.measurement_enabled],
    ([id, enabled]) => {
        measurementDraft.value = id || '';
        measurementEnabled.value = enabled !== false;
    },
);

const ready = computed(() => Boolean(props.data?.ready));
const canConnect = computed(() => Boolean(props.data?.can_connect));
const hasRefreshToken = computed(() => Boolean(props.data?.has_refresh_token));
const connectUrl = computed(() => props.data?.connect_url || null);
const disconnectUrl = computed(() => props.data?.disconnect_url || null);
const propertySaveUrl = computed(() => props.data?.property_id_save_url || null);
const propertySource = computed(() => props.data?.property_id_source || null);
const canEditProperty = computed(() => Boolean(propertySaveUrl.value));
const propertyDirty = computed(
    () => String(propertyDraft.value || '').trim() !== String(props.data?.property_id || '').trim(),
);

const measurementSaveUrl = computed(() => props.data?.measurement_save_url || null);
const measurementSource = computed(() => props.data?.measurement_id_source || null);
const canEditMeasurement = computed(() => Boolean(measurementSaveUrl.value));
const publicGtagActive = computed(() => Boolean(props.data?.public_gtag_active));
const measurementDirty = computed(() => {
    const idChanged = String(measurementDraft.value || '').trim() !== String(props.data?.measurement_id || '').trim();
    const enabledChanged = Boolean(measurementEnabled.value) !== (props.data?.measurement_enabled !== false);
    return idChanged || enabledChanged;
});

const yesNo = (ok) => (ok ? 'set' : 'missing');

const rows = computed(() => {
    const d = props.data || {};
    const source = d.refresh_token_source;
    const refreshLabel = source === 'database'
        ? 'set (saved)'
        : source === 'env'
            ? 'set (.env)'
            : yesNo(d.has_refresh_token);

    const usingOauth = Boolean(d.has_refresh_token);
    const accessLabel = d.has_static_access_token
        ? 'set'
        : usingOauth
            ? 'not needed'
            : 'missing';

    return [
        { label: 'Auth mode', value: d.auth_mode || 'missing', ok: d.auth_mode === 'oauth_refresh' || d.auth_mode === 'static_token' },
        { label: 'GOOGLE_CLIENT_ID', value: yesNo(d.has_client_id), ok: Boolean(d.has_client_id) },
        { label: 'GOOGLE_CLIENT_SECRET', value: yesNo(d.has_client_secret), ok: Boolean(d.has_client_secret) },
        { label: 'Refresh token', value: refreshLabel, ok: Boolean(d.has_refresh_token) },
        {
            label: 'Access token (legacy)',
            value: accessLabel,
            ok: Boolean(d.has_static_access_token) || usingOauth,
        },
    ];
});

const saveProperty = async () => {
    if (!propertySaveUrl.value || savingProperty.value || !propertyDirty.value) {
        return;
    }

    savingProperty.value = true;
    try {
        const { data } = await axios.put(propertySaveUrl.value, {
            property_id: propertyDraft.value,
        });
        toast.add({
            severity: 'success',
            summary: 'Google Analytics',
            detail: data?.message || 'Property ID saved.',
            life: 4000,
            group: 'br',
        });
        emit('updated', data?.ga_status || null);
        if (!data?.ga_status) {
            window.location.reload();
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Save failed',
            detail: error?.response?.data?.message || 'Could not save property ID.',
            life: 6000,
            group: 'br',
        });
    } finally {
        savingProperty.value = false;
    }
};

const saveMeasurement = async () => {
    if (!measurementSaveUrl.value || savingMeasurement.value || !measurementDirty.value) {
        return;
    }

    savingMeasurement.value = true;
    try {
        const { data } = await axios.put(measurementSaveUrl.value, {
            measurement_id: measurementDraft.value,
            enabled: measurementEnabled.value,
        });
        toast.add({
            severity: 'success',
            summary: 'Public gtag',
            detail: data?.message || 'Measurement ID saved.',
            life: 4000,
            group: 'br',
        });
        emit('updated', data?.ga_status || null);
        if (!data?.ga_status) {
            window.location.reload();
        }
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Save failed',
            detail: error?.response?.data?.message || 'Could not save Measurement ID.',
            life: 6000,
            group: 'br',
        });
    } finally {
        savingMeasurement.value = false;
    }
};

const disconnect = () => {
    if (!disconnectUrl.value || disconnecting.value) {
        return;
    }

    confirm.require({
        header: 'Disconnect Google Analytics?',
        message: 'This clears the saved refresh token. You can Connect again anytime.',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Disconnect',
        rejectLabel: 'Cancel',
        acceptClass: 'p-button-danger',
        accept: () => runDisconnect(),
    });
};

const runDisconnect = async () => {
    disconnecting.value = true;
    try {
        await axios.post(disconnectUrl.value);
        toast.add({
            severity: 'success',
            summary: 'Google Analytics',
            detail: 'Disconnected — saved refresh token cleared.',
            life: 5000,
            group: 'br',
        });
        emit('disconnected');
        window.location.reload();
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Disconnect failed',
            detail: error?.response?.data?.message || 'Could not clear Google Analytics token.',
            life: 6000,
            group: 'br',
        });
    } finally {
        disconnecting.value = false;
    }
};
</script>
