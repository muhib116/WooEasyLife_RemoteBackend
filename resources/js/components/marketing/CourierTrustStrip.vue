<script setup>
defineProps({
    integrations: {
        type: Array,
        default: () => [
            { name: 'Steadfast', logo: '/images/steadfast.svg' },
            { name: 'Pathao', logo: '/images/pathao.svg' },
            { name: 'RedX', logo: '/images/redx.svg' },
        ],
    },
});
</script>

<template>
    <section class="border-y border-white/10 bg-[#0a0f1c] py-8 sm:py-10">
        <div class="mx-auto max-w-6xl px-4 text-center lg:px-8">
            <p class="text-sm font-semibold text-slate-400">যে কুরিয়ার দিয়ে কাজ করেন — আমরা সাপোর্ট করি</p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-3 sm:gap-4">
                <div
                    v-for="item in integrations"
                    :key="item.name"
                    class="flex h-12 min-w-[7rem] items-center justify-center rounded-xl border border-white/20 bg-white px-5 shadow-sm sm:h-14 sm:min-w-[8rem] sm:px-8"
                >
                    <img :src="item.logo" :alt="item.name" class="max-h-7 max-w-[5.5rem] object-contain sm:max-h-8 sm:max-w-[6rem]" />
                </div>
            </div>
        </div>
    </section>
</template>
