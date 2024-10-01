<template>
    <AuthenticatedLayout title="Follow Up">
        <Card>
            <template #content>
                <div class="relative">
                    <div class="w-full max-w-3xl mx-auto">
                        <div class="flex items-center justify-between">
                            <div class="text-xl">
                                Timeline
                            </div>
                            <Button
                                icon="pi pi-plus"
                                aria-label="Save"
                                v-tooltip.left="'Create'"
                                @click="showModal = true"
                            />
                        </div>
                        <div class="space-y-8 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:ml-[8.7rem] md:before:translate-x-0 before:h-full before:w-1 before:bg-slate-600">

                            <div
                                v-for="(item, index) in followUps"
                                class="relative"
                            >
                                <div class="md:flex items-center md:space-x-4 mb-3">
                                    <div class="flex items-center space-x-4 md:space-x-2 md:space-x-reverse">
                                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-white shadow md:order-1">
                                            <Icon
                                                name="PhAcorn"
                                            />
                                        </div>
                                        
                                        <time class="text-sm font-medium text-indigo-500 md:w-28">
                                            {{ item.created_at }}
                                        </time>
                                    </div>
                                    <div class="text-slate-500 flex items-center justify-between w-full ml-14">
                                        <div>
                                            <span class="text-slate-900 font-bold">Mark Mikrol</span>
                                            {{ item.title }}
                                        </div>
                                        <div>
                                            <Button
                                                @click="handleEdit(item)"
                                            >
                                                <span class="pi pi-pencil"></span>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-html="item.description"
                                    class="bg-white p-4 rounded border border-slate-200 text-slate-500 shadow ml-14 md:ml-44"
                                >
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
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
                    :invalid="true"
                    placeholder="Follow Up Title"
                    class="flex-auto w-full"
                    autocomplete="off" 
                />
                <span v-if="form.errors.title" class="text-red-500">{{ form.errors.title }}</span>
            </label>
            <div>
                <div>Details</div>
                <textarea
                    v-model="form.description"
                    placeholder="Write details"
                    class="w-full min-h-[100px]"
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
import { Icon } from "@/plugins";
import { useForm, usePage } from "@inertiajs/vue3";
import { isEmpty } from 'lodash'
import { Customer } from "@/types";

defineOptions({
    name: 'FollowUp'
})

const props = defineProps<{
    customer: Customer,
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

const handleEdit = (item) => {
    form.id = item.id
    form.title = item.title
    form.description = item.description
    form.next_follow_topic = item.next_follow_topic
    form.follow_date = item.follow_date
    form.next_follow_date = item.next_follow_date
    showModal.value = true
}

const handleSave = () => {
    form.post(route('followUp.save', props.customer.id), {
        onFinish() {
            if(isEmpty(usePage().props.errors)) {
                form.reset()
                showModal.value = false
            }
        }
    })
}

</script>