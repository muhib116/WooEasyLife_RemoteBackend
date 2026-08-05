/**
 * Thin ESM wrapper around OmicronLab Avro Phonetic (avro-phonetic package).
 * Ported from woo-easy-life plugin — upstream assigns module-local `OmicronLab` with no exports.
 */
import avroLibSource from 'avro-phonetic/src/avro-lib.js?raw';

type AvroPhoneticApi = {
    parse: (input: string) => string;
};

let cached: AvroPhoneticApi | null = null;

function loadAvroPhonetic(): AvroPhoneticApi {
    if (cached) {
        return cached;
    }

    // eslint-disable-next-line no-new-func
    const factory = new Function(`${avroLibSource}\nreturn OmicronLab.Avro.Phonetic;`);
    const api = factory() as AvroPhoneticApi;

    if (!api || typeof api.parse !== 'function') {
        throw new Error('Failed to load Avro Phonetic engine');
    }

    cached = api;
    return api;
}

/** Convert a Banglish (Avro phonetic) chunk to Bangla script. */
export function parseAvro(input: string): string {
    if (!input) {
        return input;
    }

    return loadAvroPhonetic().parse(input);
}
