<template>
    <AuthenticatedLayout title="Follow Up">
        <Card>
            <template #title>
                Timeline
                <Button label="Add Follow Up" @click="showModal = true" />
            </template>
            <template #content>
                {{ followUps }}
            </template>
        </Card>
        <Dialog
            v-model:visible="showModal" 
            maximizable
            modal
            header="Header"
            :style="{ width: '50rem' }"
            :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
            as="form"
            @submit.prevent="handleSave"
        >
            <label class="grid gap-1 mb-2">
                <div>Title</div>
                <InputText 
                    v-model="form.title" 
                    id="title" 
                    placeholder="Follow Up Title"
                    class="flex-auto w-full"
                    autocomplete="off" 
                />
            </label>
            <div>
                <div>Details</div>
                <Editor
                    v-model="form.description"
                    placeholder="Write details"
                    editorStyle="min-height: 100px" 
                />
            </div>
            <div class="mt-5 flex justify-end">
                <Button
                    :loading="form.processing"
                    @click="handleSave"
                >
                    {{ form.id ? 'Update' : 'Create' }}
                </Button>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts"
import { ref, reactive } from "vue";
import { useForm } from "@inertiajs/vue3";

defineOptions({
    name: 'FollowUp'
})

const props = defineProps<{
    customer: object,
    followUps: any[]
}>()

const showModal = ref(false)

const form = useForm({
    id: null,
    title: '',
    description: '',
    next_follow_topic: '',
    follow_date: '',
    next_follow_date: '',
})

const handleSave = () => {
    form.post(route('followUp.save', props.customer?.id), {
        onFinish() {
            form.reset()
        }
    })
}

</script>