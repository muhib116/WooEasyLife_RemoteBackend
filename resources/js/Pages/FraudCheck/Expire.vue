<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-800">
            <template #content>
                <div class="min-h-[400px]">
                    <div class="flex justify-between">
                        Pathao time
                        <div class="space-x-4">
                            <Button
                                @click="getExpire"
                                size="small"
                                icon="pi pi-clock"
                                label="Time Left"
                            />
                            <Button
                                severity="success"
                                @click="renewExpireExpire"
                                size="small"
                                icon="pi pi-refresh"
                                label="Re new"
                            />
                        </div>
                    </div>
                    <div>
                        <pre>{{ time_left }}</pre>
                    </div>

                    <div>
                        <div>Steadfast Curl Code</div>
                        <Textarea
                            v-model="curlForm.curl_text"
                            class="w-full h-[400px]"
                        />
                        <div>
                            <Button @click="saveCurl">Save</Button>
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

const props = defineProps({
    steadfast_curl: String
})

const isLoading = ref(false);

const curlText = ref(props.steadfast_curl || '')

const time_left = ref();

const form = useForm({
    phone: "",
});

const curlForm = useForm({
    curl_text: props.steadfast_curl
})

const saveCurl = () => {
    curlForm.post(route('frauds.saveSteadfastCurl'))
}

// 01752-360254

const getExpire = async () => {
    const { data } = await axios.post(route("frauds.getExpire"));
    time_left.value = data;
};
const renewExpireExpire = async () => {
    const { data } = await axios.post(route("frauds.renewExpire"));
    time_left.value = data;
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
