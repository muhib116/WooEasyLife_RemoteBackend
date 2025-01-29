<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-800">
            <template #content>
                <div class="min-h-[400px]">
                    <div class="relative flex justify-center">
                        <InputGroup class="max-w-[400px]">
                            <InputMask
                                id="basic"
                                v-model="form.phone"
                                mask="99999-999999"
                                placeholder="99999-999999"
                            />
                            <!-- <Button @click="handleSearch" :loading="isLoading">
                                <i class=""></i>
                                Check
                            </Button> -->

                            <Button
                                type="button"
                                label="Search"
                                icon="pi pi-check"
                                :loading="isLoading"
                                @click="handleSearch"
                            />
                        </InputGroup>
                    </div>

                    <div
                        class="grid min-h-[300px] place-content-center pt-[50px] pb-10"
                    >
                        <div v-if="isLoading" class="loader mb-5"></div>
                        <Image
                            v-else-if="!response"
                            :src="SecurityOn"
                            alt=""
                            class="max-w-[250px]"
                        />
                        <div
                            v-else
                            class="grid grid-cols-2 gap-5 max-w-[400px]"
                        >
                            <div
                                class="rounded flex flex-col text-green-600 border border-current justify-center items-center"
                            >
                                <span class="font-bold">Success</span>
                                <p class="text-3xl font-bold text-center">
                                    {{ response?.confirmed }}
                                </p>
                            </div>
                            <div
                                class="rounded flex flex-col text-red-600 border border-current justify-center items-center"
                            >
                                <span class="font-bold p-2">Cancellation</span>
                                <p class="m-0 text-3xl font-bold text-center">
                                    {{ response?.cancel }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import SecurityOn from "@/images/security_on.svg";
import { ref } from "vue";
import axios from "axios";
import { find } from "lodash";

defineOptions({
    name: "FraudCheck",
});

const isLoading = ref(false);
const response = ref();

const form = useForm({
    phone: "",
});
// 01752-360254

const postStreamRequest = async (url: string, payload: any, config: any) => {
    const axiosDefaultUrl = axios.defaults?.baseURL || "";
    try {
        const response = await fetch(`${axiosDefaultUrl}${url}`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                Authorization: String(
                    axios.defaults?.headers?.common["Authorization"]
                ),
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok)
            throw new Error(`HTTP error! Status: ${response.status}`);

        const reader = response.body?.getReader();
        if (!reader) throw new Error("Failed to get response reader.");

        let buffer = "",
            decoder = new TextDecoder();

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            buffer.split("\n\n").forEach((event) => {
                const [type, data] = event
                    .split("\n")
                    .map((line) => line.split(": ")[1]);
                if (!type || !data) return;
                (type === "user_report" ? config?.onData : config?.onDone)?.(
                    JSON.parse(data)
                );
            });

            buffer = buffer.slice(buffer.lastIndexOf("\n\n") + 2);
        }
    } catch (error) {
        config?.onError?.(error);
    }
};

const handleSearch = async () => {
    postStreamRequest(
        route("checkStream"),
        {
            data: [
                {
                    id: 535,
                    phone: "01727897763",
                },
                {
                    id: 534,
                    phone: "01700501035",
                },
            ],
        },
        {
            onData: ({ data, progress }) => {
                console.log({ data, progress });
            },
            onDone: (data) => {
                console.log(data);
            },
        }
    );
    if (form.phone) {
        // isLoading.value = true;
        // const { data } = await axios.post(route("adminCheckStream"), {
        //     data: [
        //         {
        //             id: 535,
        //             phone: "01727897763",
        //         },
        //         {
        //             id: 534,
        //             phone: "01700501035",
        //         },
        //     ],
        // });
    }
    // if (form.phone) {
    //     isLoading.value = true;
    //     const phone = String(form.phone).replace("-", "");
    //     const { data } = await axios.post(route("adminFraudCheck"), {
    //         phone,
    //     });
    //     isLoading.value = false;
    //     response.value = data;
    // }
};
</script>

<style>
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
    animation: l3 1s infinite linear;
}
@keyframes l3 {
    50% {
        left: 100%;
        transform: translateX(calc(-100% - 4px));
    }
}
</style>
