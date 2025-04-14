<template>
    <AuthenticatedLayout title="Route Hit Reports">
        <Card class="dark:bg-slate-900 dark:text-white">
            <template #title>
                <div class="flex items-center justify-between">
                    Use Report
                    <div class="flex gap-3">
                        <Dropdown
                            v-model="selectedUserId"
                            :options="users"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Select User"
                            class="w-[250px]"
                            @change="fetchReport"
                        />
                        <Button
                            label="Reload"
                            icon="pi pi-refresh"
                            @click="fetchReport"
                            :loading="loading"
                        />
                    </div>
                </div>
            </template>

            <template #content>
                <div v-for="item in uniqueLinksItems || []">
                    <a
                        :href="item?.product_url"
                        class="mb-5 block border px-4 py-2"
                        >{{ item?.name }}</a
                    >
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { AuthenticatedLayout } from "@/layouts";
import { isString, each, isArray } from "lodash";

const props = defineProps({
    users: Array,
});

const loading = ref(false);
const selectedUserId = ref();
const report = ref([]);
const uniqueLinks = ref([]);
const uniqueLinksItems = ref([]);

const fetchReport = async () => {
    if (!selectedUserId.value) {
        return;
    }
    loading.value = true;
    const { data } = await axios.post(route("useAnalysis.getUseReport"), {
        user_id: selectedUserId.value,
    });
    loading.value = false;

    report.value = data || [];
    getUniqueLinks(data);
    // console.log(data);
};

const getUniqueLinks = (data) => {
    uniqueLinks.value = [];
    const useDetails = data.flatMap((item) => item.use_details);
    // console.log(useDetails)
    each(useDetails, (item) => {
        if (item?.from == "missing_order") {
            // console.log(item)
        }
        if (isArray(item?.cart_contents)) {
            each(item?.cart_contents, (content) => {
                // console.log(content);
                if (!uniqueLinks.value.includes(content?.product_url)) {
                    uniqueLinks.value.push(content?.product_url);
                    uniqueLinksItems.value.push(content);
                }
            });
        } else {
            if (!uniqueLinks.value.includes(item?.cart_contents?.product_url)) {
                uniqueLinks.value.push(item?.cart_contents?.product_url);
                uniqueLinksItems.value.push(item?.cart_contents);
            }
        }
    });
    console.log(uniqueLinks.value);
};
</script>
