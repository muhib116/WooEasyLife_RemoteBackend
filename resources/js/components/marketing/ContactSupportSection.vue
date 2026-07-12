<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    whatsappContactUrl: { type: String, default: null },
    headline: { type: String, default: 'আরও প্রশ্ন আছে?' },
    subheadline: { type: String, default: 'আমাদের সাপোর্ট টিম সবসময় প্রস্তুত' },
    buttonLabel: { type: String, default: 'যোগাযোগ করুন' },
});

const page = usePage();

const contactUrl = computed(
    () => props.whatsappContactUrl || page.props.marketing?.whatsapp_contact_url || null,
);

const adminEmail = computed(() => page.props.marketing?.admin_email || null);
const helpline = computed(() => page.props.marketing?.helpline || null);
</script>

<template>
    <section v-if="contactUrl || adminEmail || helpline" class="px-4 py-10 sm:py-14">
        <div class="mx-auto max-w-4xl">
            <div
                class="flex flex-col items-center justify-between gap-6 rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-md sm:flex-row sm:gap-8 sm:p-8"
            >
                <div class="text-center sm:text-left">
                    <h2 class="text-xl font-bold text-white sm:text-2xl">{{ headline }}</h2>
                    <p class="mt-1.5 text-sm text-slate-400 sm:text-base">{{ subheadline }}</p>
                    <p class="mt-3 inline-flex max-w-full flex-wrap items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3.5 py-2 text-left text-xs leading-snug text-slate-300 sm:justify-start sm:rounded-full sm:py-1.5 sm:text-sm">
                        <svg class="h-4 w-4 shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        প্রতিটি ফিচারের পেজেই বিল্ট-ইন ভিডিও টিউটোরিয়াল — আটকে গেলে সাথে সাথেই সমাধান
                    </p>
                    <div
                        v-if="helpline || adminEmail"
                        class="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-slate-400 sm:justify-start sm:text-sm"
                    >
                        <a
                            v-if="helpline"
                            :href="`tel:${helpline}`"
                            class="transition hover:text-white"
                        >
                            {{ helpline }}
                        </a>
                        <a
                            v-if="adminEmail"
                            :href="`mailto:${adminEmail}`"
                            class="transition hover:text-white"
                        >
                            {{ adminEmail }}
                        </a>
                    </div>
                </div>

                <a
                    v-if="contactUrl"
                    :href="contactUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex shrink-0 items-center gap-2.5 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 px-7 py-3 text-sm font-bold text-white shadow-lg shadow-purple-500/25 transition hover:from-blue-500 hover:to-purple-500"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                        />
                    </svg>
                    {{ buttonLabel }}
                </a>
            </div>
        </div>
    </section>
</template>
