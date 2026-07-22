<script setup>
import { computed } from 'vue';

const props = defineProps({
    integrations: {
        type: Array,
        default: () => [
            { name: 'Steadfast', logo: '/images/steadfast.svg' },
            { name: 'Pathao', logo: '/images/pathao.svg' },
            { name: 'Paperfly', logo: '/images/paperfly.png' },
            { name: 'RedX', logo: '/images/redx.svg' },
            { name: 'Carrybee', logo: '/images/carrybee.svg' },
        ],
    },
    locale: { type: String, default: 'bn' },
});

const isEn = computed(() => props.locale === 'en');
</script>

<template>
    <section class="border-y border-white/10 bg-[#111111] py-8 sm:py-10">
        <div class="mx-auto max-w-6xl px-4 text-center lg:px-8">
            <p class="text-sm font-semibold text-slate-400">
                {{ isEn ? 'Couriers we support' : 'যেসব কুরিয়ার আমরা সাপোর্ট করি' }}
            </p>
            <div
                class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5 lg:gap-3"
            >
                <div
                    v-for="item in integrations"
                    :key="item.name"
                    class="flex h-14 w-full items-center justify-center rounded-xl border border-white/20 bg-white px-3 shadow-sm sm:h-14"
                >
                    <img
                        :src="item.logo"
                        :alt="item.name"
                        class="h-8 w-full max-w-[9rem] object-contain"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
