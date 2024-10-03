<template>
    <AuthenticatedLayout title="Follow Up">
        <Card>
            <template #content>
                <div class="relative">
                    <div class="w-full max-w-4xl mx-auto">
                        <DataTable 
                            :value="followUps" 
                            tableStyle="min-width: 50rem"
                            showGridlines
                            stripedRows
                        >
                            <template #header>
                                <div class="flex flex-wrap w-full items-center justify-between gap-2">
                                    <span class="text-xl font-bold">Timeline</span>
                                    <Button 
                                        icon="pi pi-plus" 
                                        rounded 
                                        raised 
                                        v-tooltip.left="'Create'"
                                        @click="showModal = true"
                                    />
                                </div>
                            </template>
                            <Column 
                                field="created_at" 
                                header="Created"
                                style="width:250px"
                            >
                                <template #body="{data}">
                                    {{ format(data.created_at, "MMM dd, yyyy hh:mm a") }}
                                </template>
                            </Column>
                            <Column field="title" header="Title"></Column>
                            <Column field="description" header="Description">
                                <template #body="{data}">
                                    <div 
                                        class="line-clamp-2 ck-content"
                                        v-html="getInnerText(data.description)"
                                    >
                                    </div>
                                </template>
                            </Column>
                            <Column header="Action">
                                <template #body="{data}">
                                    <Button
                                        @click="handleEdit(data)"
                                        icon="pi pi-pencil"
                                        class="!w-8 h-8"
                                    />
                                </template>
                            </Column>
                        </DataTable>
                        <!-- <div class="space-y-8 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:ml-[8.7rem] md:before:translate-x-0 before:h-full before:w-1 before:bg-slate-600">

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

                        </div> -->

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
                    :invalid="!form.title"
                    placeholder="Follow Up Title"
                    class="flex-auto w-full"
                    autocomplete="off" 
                />
                <span v-if="form.errors.title" class="text-red-500">{{ form.errors.title }}</span>
            </label>
            <div>
                <div>Details</div>
                <Editor.Classic
                    v-model="form.description"
                    placeholder="Write details"
                    class="w-full min-h-[500px]"
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
import { format } from 'date-fns'
import { useForm, usePage } from "@inertiajs/vue3";
import { isEmpty } from 'lodash'
import { Customer } from "@/types";
import { Editor } from "@/plugins/form";

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

const getInnerText = (content) => {
    let div = document.createElement('div')
    div.innerHTML = content
    return div.innerText
}

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