<template>
    <component
        :is="$page.props?.auth?.user?.id ? AuthenticatedLayout : 'div'"
        title="Fraud Checker"
    >
        <div class="space-y-5">
            <PageHeader
                v-if="$page.props?.auth?.user?.id"
                title="Fraud Checker"
                description="Check customer delivery success rate across couriers"
                icon="PhUserCheck"
            />

            <PageCard title="Phone Lookup" description="Enter a Bangladesh mobile number to check">
                <div class="mx-auto max-w-md">
                    <InputGroup>
                        <InputMask
                            v-model="form.phone"
                            mask="99999-999999"
                            placeholder="01XXX-XXXXXX"
                            class="w-full"
                        />
                        <Button
                            label="Check"
                            icon="pi pi-search"
                            :loading="isLoading"
                            @click="handleSearch"
                        />
                    </InputGroup>
                </div>

                <div class="flex min-h-[280px] flex-col items-center justify-center py-8">
                    <div v-if="isLoading" class="loader mb-4" />
                    <img
                        v-else-if="!response"
                        :src="SecurityOn"
                        alt=""
                        class="max-w-[220px] opacity-80"
                    />
                    <div
                        v-else
                        class="grid w-full max-w-lg grid-cols-2 gap-4"
                    >
                        <div
                            class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center dark:border-emerald-500/30 dark:bg-emerald-500/10"
                        >
                            <p
                                class="text-sm font-medium text-emerald-700 dark:text-emerald-300"
                            >
                                Successful Deliveries
                            </p>
                            <p
                                class="mt-2 text-4xl font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ response?.confirmed ?? 0 }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-center dark:border-rose-500/30 dark:bg-rose-500/10"
                        >
                            <p
                                class="text-sm font-medium text-rose-700 dark:text-rose-300"
                            >
                                Cancellations
                            </p>
                            <p
                                class="mt-2 text-4xl font-bold text-rose-600 dark:text-rose-400"
                            >
                                {{ response?.cancel ?? 0 }}
                            </p>
                        </div>
                    </div>
                </div>
            </PageCard>

            <PageCard
                v-if="slangs?.length"
                title="Steadfast Fraud Reports"
                :description="`${slangs.length} reported issue${slangs.length === 1 ? '' : 's'}`"
            >
                <SlangItems :items="slangs" />
            </PageCard>
        </div>

        <div v-if="showClick" class="mt-4 text-center">
            <Link
                :href="route('frauds.expire')"
                class="text-sm text-primary-600 hover:underline dark:text-primary-400"
            >
                Admin: Token & CURL settings
            </Link>
        </div>
    </component>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm, Link } from "@inertiajs/vue3";
import SecurityOn from "@/images/security_on.svg";
import { onBeforeUnmount, onMounted, ref } from "vue";
import axios from "axios";
import SlangItems from "./SlangItems.vue";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";

defineOptions({
    name: "FraudCheck",
});

const isLoading = ref(false);
const response = ref<any>(null);
const showClick = ref(false);
const slangs = ref<any[]>([]);

let clickCount = 0;
let timer: ReturnType<typeof setTimeout> | null = null;

function handleWindowClick() {
    clickCount++;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        clickCount = 0;
    }, 200);
    if (clickCount >= 6) {
        showClick.value = true;
        clickCount = 0;
        if (timer) clearTimeout(timer);
        timer = null;
    }
}

onMounted(() => {
    window.addEventListener("click", handleWindowClick);
});

onBeforeUnmount(() => {
    window.removeEventListener("click", handleWindowClick);
    if (timer) clearTimeout(timer);
});

const form = useForm({
    phone: "",
});

const handleSearch = async () => {
    if (!form.phone) {
        return;
    }

    isLoading.value = true;
    response.value = null;
    slangs.value = [];

    try {
        const phone = String(form.phone).replace("-", "");
        const { data } = await axios.post(route("frauds.adminFraudCheck"), {
            phone,
        });
        response.value = data;
        slangs.value = data?.frauds || [];
    } finally {
        isLoading.value = false;
    }
};
</script>

<style scoped>
.loader {
    width: 120px;
    height: 22px;
    border-radius: 40px;
    color: var(--p-primary-color);
    border: 2px solid;
    position: relative;
}
.loader::before {
    content: "";
    position: absolute;
    margin: 2px;
    width: 25%;
    top: 0;
    bottom: 0;
    left: 0;
    border-radius: inherit;
    background: currentColor;
    animation: fraud-loader 1s infinite linear;
}
@keyframes fraud-loader {
    50% {
        left: 100%;
        transform: translateX(calc(-100% - 4px));
    }
}
</style>
