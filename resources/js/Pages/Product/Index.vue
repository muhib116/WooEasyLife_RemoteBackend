<template>
    <AuthenticatedLayout title="Orders">
        <Card>
            <template #content>
                <div class="relative">
                    <div class="w-full">
                        <DataTable 
                            :value="products" 
                            tableStyle="min-width: 50rem"
                            showGridlines
                            stripedRows
                        >
                            <template #header>
                                <div class="flex flex-wrap w-full items-center justify-between gap-2">
                                    <span class="text-xl font-bold">Products</span>
                                    <Button 
                                        icon="pi pi-plus" 
                                        rounded 
                                        raised 
                                        v-tooltip.left="'Create Product'"
                                        @click="() => {
                                            form.reset('id', 'name', 'price')
                                            console.log(form.defaults())
                                            showModal = true
                                        }"
                                    />
                                </div>
                            </template>
                            <Column 
                                field="follow_date" 
                                header="Follow Date"
                                style="width:250px"
                            >
                                <template #body="{data}">
                                    {{ format(data.created_at, "MMM dd, yyyy hh:mm a") }}
                                </template>
                            </Column>
                            <Column field="name" header="Name"></Column>
                            <!-- <Column field="purchase_price" header="Purchase Price"></Column>
                            <Column field="discount" header="Discount"></Column>
                            <Column field="sell_price" header="Sell Price"></Column>
                            <Column field="sell_price" header="Final Price">
                                <template #body="{data}">
                                    {{ data.sell_price - data.discount }}
                                </template>
                            </Column> -->
                            <Column header="Action" header-class="text-right">
                                <template #body="{data}">
                                    <TableActions>
                                        <TableActionButton
                                            action="edit"
                                            tooltip="Edit product"
                                            @click="handleEdit(data)"
                                        />
                                    </TableActions>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </template>
        </Card>
        <Dialog
            v-model:visible="showModal"
            maximizable
            modal
            header="New Order"
            :style="{ width: '50rem' }"
            :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
        >
            <form 
                @submit.prevent="handleSave"
                id="form"
            >
                <div class="relative">
                    <label class="grid gap-1 mb-2">
                        <div>Name</div>
                        <InputText 
                            v-model="form.name" 
                        />
                        <span v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</span>
                    </label>
                </div>
                <!-- <div class="relative">
                    <label class="grid gap-1 mb-2">
                        <div>Description</div>
                        <InputText
                            v-model="form.description" 
                        />
                        <span v-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</span>
                    </label>
                </div> -->
                <div class="relative">
                    <label class="grid gap-1 mb-2">
                        <div>Sell Price</div>
                        <InputNumber
                            v-model="form.price" 
                            :minFractionDigits="0" 
                            :maxFractionDigits="5"
                            fluid
                        />
                        <span v-if="form.errors.price" class="text-red-500">{{ form.errors.purchase_price }}</span>
                    </label>
                </div>
                <!-- 
                <div class="relative">
                    <label class="grid gap-1 mb-2">
                        <div>Sell Price</div>
                        <InputNumber
                            v-model="form.sell_price" 
                            :minFractionDigits="0" 
                            :maxFractionDigits="5"
                            fluid
                        />
                        <span v-if="form.errors.sell_price" class="text-red-500">{{ form.errors.sell_price }}</span>
                    </label>
                </div>
                <div class="relative">
                    <label class="grid gap-1 mb-2">
                        <div>Discount</div>
                        <InputNumber
                            v-model="form.discount" 
                            :minFractionDigits="0" 
                            :maxFractionDigits="5"
                            fluid
                        />
                        <span v-if="form.errors.discount" class="text-red-500">{{ form.errors.discount }}</span>
                    </label>
                </div> -->
            </form>
            <template #footer>
                <div class="mt-5 flex justify-end">
                    <Button
                        :disabled="form.processing"
                        @click="handleSave"
                        for="form"
                    >
                        <span 
                            :class="form.processing ? 'pi pi-spinner animate-spin' : 'pi pi-save'"
                        />
                        {{ form.id ? 'Update' : 'Create' }}
                    </Button>
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { AuthenticatedLayout } from "@/layouts"
import { ref } from "vue";
import { format } from 'date-fns'
import { useForm, usePage } from "@inertiajs/vue3";
import { isEmpty } from 'lodash'
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";

defineOptions({
    name: 'FollowUp'
})

defineProps<{
    products: any[]
}>()

const showModal = ref(false)

const form = useForm({
    id: null,
    name: '',
    description: '',
    price: null,
    settings: null,
})

const handleEdit = (item) => {
    form.transform((data) => {
        console.log(data)
        return data
    })
    form.id = item.id
    form.name = item.name
    form.description = item.description
    form.price = item.price
    showModal.value = true
}

const handleSave = () => {
    form.post(route('products.save'), {
        onFinish() {
            if(isEmpty(usePage().props.errors)) {
                form.reset()
                showModal.value = false
            }
        }
    })
}

</script>