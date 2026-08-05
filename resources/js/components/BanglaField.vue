<script setup lang="ts">
import { computed, nextTick, useAttrs } from 'vue';
import {
    commitPhoneticWord,
    isPhoneticToggleShortcut,
} from '@/composables/phoneticHelpers';
import { usePhoneticLayout } from '@/composables/usePhoneticLayout';

defineOptions({ inheritAttrs: false });

const model = defineModel<string>({ default: '' });

const props = withDefaults(
    defineProps<{
        multiline?: boolean;
        rows?: number;
        placeholder?: string;
        disabled?: boolean;
        id?: string;
        name?: string;
        /** Show EN/বাং toggle (default true). */
        showToggle?: boolean;
    }>(),
    {
        multiline: false,
        rows: 3,
        placeholder: '',
        disabled: false,
        showToggle: true,
    },
);

const attrs = useAttrs();
const { isBangla, toggle } = usePhoneticLayout();

const fieldClass = computed(() => {
    const base = props.multiline
        ? 'w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900'
        : 'h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900';

    const pad = props.showToggle ? 'pr-14' : '';
    const extra =
        typeof attrs.class === 'string'
            ? attrs.class
            : Array.isArray(attrs.class)
              ? attrs.class.join(' ')
              : '';

    return [base, pad, extra].filter(Boolean).join(' ');
});

const toggleClass = computed(() => {
    const pos = props.multiline ? 'top-2' : 'top-1/2 -translate-y-1/2';
    const tone = isBangla.value
        ? 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-700/70 dark:bg-emerald-950/50 dark:text-emerald-200 dark:hover:bg-emerald-900/50'
        : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:bg-slate-800 dark:text-gray-300 dark:hover:bg-slate-700';
    return [pos, tone].join(' ');
});

const toggleTitle = computed(() =>
    isBangla.value
        ? 'Bangla (Avro) — type Banglish, Space to convert · click or Ctrl+M for English'
        : 'English — click or Ctrl+M for Bangla (Avro)',
);

const passthrough = computed(() => {
    const { class: _c, ...rest } = attrs as Record<string, unknown>;
    return rest;
});

function applyPhoneticCommit(el: HTMLInputElement | HTMLTextAreaElement): void {
    const cur = el.selectionStart ?? 0;
    const end = el.selectionEnd ?? cur;
    const result = commitPhoneticWord(el.value, cur, end);
    if (!result.changed) {
        return;
    }

    // Update v-model directly — native input events can race with Vue.
    model.value = result.value;
    void nextTick(() => {
        el.setSelectionRange(result.caret, result.caret);
    });
}

function onToggle(event: Event): void {
    if (props.disabled) return;
    event.preventDefault();
    event.stopPropagation();
    toggle();
}

function onKeydown(event: KeyboardEvent): void {
    if (isPhoneticToggleShortcut(event)) {
        event.preventDefault();
        toggle();
        return;
    }

    if (!isBangla.value) {
        return;
    }
    if (event.isComposing) {
        return;
    }

    // Convert Banglish word on Space / Enter / Tab (not per keystroke).
    if (event.key === ' ' || event.key === 'Enter' || event.key === 'Tab') {
        const el = event.target as HTMLInputElement | HTMLTextAreaElement | null;
        if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA')) {
            applyPhoneticCommit(el);
        }
    }
}
</script>

<template>
    <div class="relative w-full">
        <textarea
            v-if="multiline"
            :id="id"
            :name="name"
            v-model="model"
            :rows="rows"
            :placeholder="placeholder"
            :disabled="disabled"
            :class="fieldClass"
            lang="bn"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            v-bind="passthrough"
            @keydown="onKeydown"
        />
        <input
            v-else
            :id="id"
            :name="name"
            v-model="model"
            type="text"
            :placeholder="placeholder"
            :disabled="disabled"
            :class="fieldClass"
            lang="bn"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            v-bind="passthrough"
            @keydown="onKeydown"
        />

        <button
            v-if="showToggle"
            type="button"
            class="absolute right-2 z-20 inline-flex h-7 min-w-[2rem] select-none items-center justify-center rounded-lg border px-2 text-[11px] font-bold leading-none transition"
            :class="toggleClass"
            :title="toggleTitle"
            :aria-label="toggleTitle"
            :aria-pressed="isBangla"
            :disabled="disabled"
            tabindex="-1"
            @pointerdown.prevent.stop="onToggle"
        >
            {{ isBangla ? 'বাং' : 'EN' }}
        </button>
    </div>
</template>
