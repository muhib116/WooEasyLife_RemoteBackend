<template>
    <a
        :href="url"
        target="_blank"
        rel="noopener noreferrer"
        class="flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm transition hover:bg-emerald-500/15"
        @click="onClick"
    >
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.126 1.529 5.86L0 24l6.335-1.662A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.82a9.82 9.82 0 01-5.01-1.37l-.36-.214-3.76.987 1.004-3.66-.234-.375A9.82 9.82 0 1112 21.82z" />
            </svg>
        </span>
        <span class="min-w-0 text-left">
            <span class="block font-semibold text-emerald-300">{{ label }}</span>
            <span v-if="phone" class="block text-emerald-100/90">{{ phone }}</span>
        </span>
    </a>
</template>

<script setup>
import { trackContact } from '@/utils/metaPixel';

defineProps({
    url: { type: String, required: true },
    phone: { type: String, default: null },
    label: { type: String, default: 'WhatsApp সাপোর্ট — সরাসরি সাহায্য' },
});

const onClick = () => {
    trackContact({ method: 'whatsapp_support', content_name: 'whatsapp_support' });
};
</script>
