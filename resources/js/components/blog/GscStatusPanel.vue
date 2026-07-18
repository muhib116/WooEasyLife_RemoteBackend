<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Google Search Console API
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
                Click <strong>Connect Search Console</strong> once, approve Google — the token saves automatically.
                Also set <code class="text-[11px]">SEO_GSC_SITE_URL</code> in <code class="text-[11px]">.env</code>.
            </template>
            <template v-else-if="!canConnect">
                Set <code class="text-[11px]">GOOGLE_CLIENT_ID</code> and
                <code class="text-[11px]">GOOGLE_CLIENT_SECRET</code> first.
            </template>
            <template v-else>
                Set <code class="text-[11px]">SEO_GSC_SITE_URL</code> to your verified Search Console property URL, then probe.
            </template>
        </p>

        <div class="flex flex-wrap gap-2">
            <Button
                v-if="canConnect && connectUrl"
                as="a"
                :href="connectUrl"
                :label="hasRefreshToken ? 'Reconnect Search Console' : 'Connect Search Console'"
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
                label="Probe GSC API"
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
import { computed, ref } from 'vue';
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

const emit = defineEmits(['probe', 'disconnected']);

const toast = useToast();
const confirm = useConfirm();
const disconnecting = ref(false);

const ready = computed(() => Boolean(props.data?.ready));
const canConnect = computed(() => Boolean(props.data?.can_connect));
const hasRefreshToken = computed(() => Boolean(props.data?.has_refresh_token));
const connectUrl = computed(() => props.data?.connect_url || null);
const disconnectUrl = computed(() => props.data?.disconnect_url || null);

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
        { label: 'Site URL', value: d.site_url || '(missing)', ok: Boolean(d.has_site_url) },
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

const disconnect = () => {
    if (!disconnectUrl.value || disconnecting.value) {
        return;
    }

    confirm.require({
        header: 'Disconnect Search Console?',
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
            summary: 'Search Console',
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
            detail: error?.response?.data?.message || 'Could not clear Search Console token.',
            life: 6000,
            group: 'br',
        });
    } finally {
        disconnecting.value = false;
    }
};
</script>
