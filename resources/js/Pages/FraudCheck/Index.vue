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
                icon-bg-class="bg-emerald-50 dark:bg-emerald-500/15"
                icon-class="text-emerald-600 dark:text-emerald-400"
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

                <div
                    v-if="errorMessage"
                    class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300"
                >
                    {{ errorMessage }}
                </div>

                <div class="flex min-h-[280px] flex-col items-center justify-center py-8">
                    <div
                        v-if="isLoading"
                        class="flex flex-col items-center gap-4"
                    >
                        <div class="skeleton-block h-12 w-12 rounded-full" />
                        <div class="skeleton-block h-3 w-32" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Checking courier history...
                        </p>
                    </div>
                    <img
                        v-else-if="!response"
                        :src="SecurityOn"
                        alt=""
                        class="max-w-[220px] opacity-80"
                    />
                    <div v-else class="mx-auto w-full max-w-2xl space-y-6">
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center dark:border-slate-600 dark:bg-slate-900/40"
                        >
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                Success Rate
                            </p>
                            <p class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">
                                {{ response?.success_rate }}
                            </p>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div
                                class="flex flex-col items-center rounded-xl border border-slate-200 p-3 dark:border-slate-600"
                            >
                                <span class="text-xs font-medium text-slate-500">Total Orders</span>
                                <p class="text-2xl font-bold">{{ response?.total_order ?? 0 }}</p>
                            </div>
                            <div
                                class="flex flex-col items-center rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-emerald-600 dark:border-emerald-500/30 dark:bg-emerald-500/10"
                            >
                                <span class="text-xs font-medium">Delivered</span>
                                <p class="text-2xl font-bold">{{ response?.confirmed ?? 0 }}</p>
                            </div>
                            <div
                                class="flex flex-col items-center rounded-xl border border-rose-200 bg-rose-50 p-3 text-rose-600 dark:border-rose-500/30 dark:bg-rose-500/10"
                            >
                                <span class="text-xs font-medium">Cancelled</span>
                                <p class="text-2xl font-bold">{{ response?.cancel ?? 0 }}</p>
                            </div>
                        </div>

                        <div v-if="response?.courier?.length" class="space-y-3">
                            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                                Courier breakdown
                            </p>
                            <div
                                v-for="item in response.courier"
                                :key="item.title"
                                class="rounded-xl border border-slate-200 p-3 dark:border-slate-600"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium">{{ item.title }}</span>
                                    <span
                                        class="text-sm font-semibold"
                                        :class="courierRateClass(item.report)"
                                    >
                                        {{ item.report?.success_rate }}
                                    </span>
                                </div>
                                <p
                                    v-if="item.report?.total_order > 0"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{ item.report.confirmed }} delivered ·
                                    {{ item.report.cancel }} cancelled
                                </p>
                                <p
                                    v-else-if="item.report?.data_type === 'rating'"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    Rating only (no delivery counts)
                                    <span v-if="item.report.estimated_success_rate">
                                        · est. {{ item.report.estimated_success_rate }}
                                    </span>
                                </p>
                                <p
                                    v-else-if="item.report?.message"
                                    class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                                >
                                    {{ item.report.message }}
                                </p>
                            </div>
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
const errorMessage = ref("");
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

const courierRateClass = (report: any) => {
    const rate = String(report?.success_rate ?? "").toLowerCase();

    if (rate.includes("good") || rate.includes("excellent") || rate.includes("%")) {
        return "text-emerald-600 dark:text-emerald-400";
    }

    if (rate.includes("poor") || rate.includes("risky") || rate.includes("average")) {
        return "text-rose-600 dark:text-rose-400";
    }

    return "text-slate-500";
};

const handleSearch = async () => {
    if (!form.phone) {
        return;
    }

    isLoading.value = true;
    response.value = null;
    errorMessage.value = "";
    slangs.value = [];

    try {
        const phone = String(form.phone).replace(/-/g, "");
        const { data } = await axios.post(route("frauds.adminFraudCheck"), {
            phone,
        });
        response.value = data;
        slangs.value = data?.frauds || [];
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message ||
            "Unable to complete fraud check. Please try again.";
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
