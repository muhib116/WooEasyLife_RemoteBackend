<template>
    <AuthenticatedLayout title="Orders">
        <Card class="dark:bg-slate-900">
            <template #content>
                <div class="min-h-[400px]">
                    <Button @click="handleGenerate" :loading="form.processing">
                        Generate Token
                    </Button>
                </div>
            </template>
        </Card>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts";
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import axios from "axios"

defineOptions({
    name: "FraudCheck",
});

const isLoading = ref(false);
const response = ref();

const form = useForm({
    phone: "",
});

const handleGenerate = () => {
    // isLoading.value = !isLoading.value;
    form.post(route("apiKeys.create"));
};
</script>
