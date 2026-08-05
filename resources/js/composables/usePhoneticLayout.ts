import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';

export type PhoneticLayoutMode = 'en' | 'bn';

const STORAGE_KEY = 'wel-phonetic-layout';

type PhoneticLayoutApi = {
    /** Current layout: English (passthrough) or Bangla (Avro phonetic). */
    mode: Ref<PhoneticLayoutMode>;
    isBangla: ComputedRef<boolean>;
    setMode: (value: PhoneticLayoutMode) => void;
    toggle: () => void;
};

let shared: PhoneticLayoutApi | null = null;

function readStoredMode(): PhoneticLayoutMode {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'bn' ? 'bn' : 'en';
    } catch {
        return 'en';
    }
}

function writeStoredMode(value: PhoneticLayoutMode): void {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // ignore quota / private mode
    }
}

function createPhoneticLayout(): PhoneticLayoutApi {
    // Plain ref (not useStorage) so a module-level singleton stays reliably reactive.
    const mode = ref<PhoneticLayoutMode>(readStoredMode());

    watch(mode, (value) => {
        writeStoredMode(value);
    });

    const isBangla = computed(() => mode.value === 'bn');

    const setMode = (value: PhoneticLayoutMode) => {
        mode.value = value === 'bn' ? 'bn' : 'en';
    };

    const toggle = () => {
        mode.value = mode.value === 'bn' ? 'en' : 'bn';
    };

    return { mode, isBangla, setMode, toggle };
}

/**
 * Shared EN ↔ বাং (Avro phonetic) keyboard layout for admin text fields.
 * Persisted in localStorage; Ctrl+M / Ctrl+. toggles while focused on a phonetic field.
 * Ported from woo-easy-life plugin.
 */
export function usePhoneticLayout(): PhoneticLayoutApi {
    if (!shared) {
        shared = createPhoneticLayout();
    }
    return shared;
}
