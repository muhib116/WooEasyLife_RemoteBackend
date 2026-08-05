import { parseAvro } from '@/lib/avroPhonetic';

/** Index of the start of the word before `caret` (after the previous whitespace). */
export function findPhoneticWordStart(value: string, caret: number): number {
    let i = Math.min(caret, value.length) - 1;
    while (i >= 0) {
        if (/\s/.test(value.charAt(i))) {
            return i + 1;
        }
        i--;
    }
    return 0;
}

export type PhoneticCommitResult = {
    value: string;
    caret: number;
    changed: boolean;
};

/**
 * Convert the Banglish word under the caret to Bangla (Avro).
 * Mirrors the plugin: convert on Space / Enter / Tab — not per keystroke.
 */
export function commitPhoneticWord(
    value: string,
    caret: number,
    selectionEnd: number = caret,
): PhoneticCommitResult {
    if (caret !== selectionEnd) {
        return { value, caret, changed: false };
    }

    const start = findPhoneticWordStart(value, caret);
    const chunk = value.substring(start, caret);
    if (!chunk) {
        return { value, caret, changed: false };
    }

    const bangla = parseAvro(chunk);
    if (bangla === chunk) {
        return { value, caret, changed: false };
    }

    return {
        value: value.substring(0, start) + bangla + value.substring(caret),
        caret: start + bangla.length,
        changed: true,
    };
}

export function isPhoneticToggleShortcut(e: {
    ctrlKey: boolean;
    altKey: boolean;
    shiftKey: boolean;
    metaKey: boolean;
    key: string;
}): boolean {
    if (!e.ctrlKey || e.altKey || e.shiftKey || e.metaKey) {
        return false;
    }
    return e.key === 'm' || e.key === 'M' || e.key === '.';
}

export const PHONETIC_SKIP_INPUT_TYPES = new Set([
    'password',
    'email',
    'tel',
    'number',
    'url',
    'date',
    'time',
    'datetime-local',
    'month',
    'week',
    'color',
    'file',
    'checkbox',
    'radio',
    'range',
    'hidden',
]);

export function isPhoneticEligibleInputType(type: string | undefined | null): boolean {
    const normalized = String(type || 'text').toLowerCase();
    return !PHONETIC_SKIP_INPUT_TYPES.has(normalized);
}
