<template>
    <AuthenticatedLayout title="Orders">
        <Card>
            <template #content>
                <div class="relative">
                    <div class="w-full">
                        <DataTable 
                            :value="[]" 
                            tableStyle="min-width: 50rem"
                            showGridlines
                            stripedRows
                        >
                            <template #header>
                                <div class="flex flex-wrap w-full items-center justify-between gap-2">
                                    <span class="text-xl font-bold">Orders</span>
                                    <Button 
                                        icon="pi pi-plus" 
                                        rounded 
                                        raised 
                                        v-tooltip.left="'Create Order'"
                                        @click="() => {
                                            form.reset()
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
                                    <!-- {{ format(data.follow_date, "MMM dd, yyyy hh:mm a") }} -->
                                    {{ format(data.follow_date, "MMM dd, yyyy") }}
                                </template>
                            </Column>
                            <Column 
                                field="next_follow_date" 
                                header="Next Follow"
                                style="width:250px"
                            >
                                <template #body="{data}">
                                    <span v-if="data.next_follow_date">
                                        {{ format(data.next_follow_date, "MMM dd, yyyy") }}
                                    </span>
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
            as="form"
            @submit.prevent="handleSave"
        >
            <div class="relative">
                <label class="grid gap-1 mb-2">
                    <div>Customer</div>
                    <Dropdown.PrimeVue
                        v-model="form.customer_id"
                        :suggestions="customers" 
                        :filter="handleCustomerFilter" 
                        optionKey="name"
                        value="id"
                    />
                    <span v-if="form.errors.customer_id" class="text-red-500">{{ form.errors.customer_id }}</span>
                </label>
                <label class="grid gap-1 mb-2">
                    <div>Product</div>
                    <Dropdown.PrimeVue
                        v-model="form.product_id"
                        :suggestions="products" 
                        :filter="handleProductFilter" 
                        optionKey="name"
                        value="id"
                    />
                    <span v-if="form.errors.customer_id" class="text-red-500">{{ form.errors.customer_id }}</span>
                </label>
                
                <div class="grid grid-cols-2 gap-2">
                    <label class="grid gap-1 mb-2">
                        <div>Follow Up Date</div>
                        <DatePicker
                            v-model="form.follow_date"
                            dateFormat="yy-mm-dd"
                            placeholder="Follow Up Date"
                            class="flex-auto w-full"
                        />
                        <span v-if="form.errors.follow_date" class="text-red-500">{{ form.errors.follow_date }}</span>
                    </label>
                    <label class="grid gap-1 mb-2">
                        <div>Next Follow Date</div>
                        <DatePicker
                            v-model="form.next_follow_date"
                            dateFormat="yy-mm-dd"
                            placeholder="Next Follow Up Date"
                            class="flex-auto w-full"
                        />
                        <span v-if="form.errors.next_follow_date" class="text-red-500">{{ form.errors.next_follow_date }}</span>
                    </label>
                </div>
            </div>
            <template #footer>
                <div class="mt-5 flex justify-end">
                    <Button
                        :disabled="form.processing"
                        @click="handleSave"
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
import { ref, onMounted } from "vue";
import { format } from 'date-fns'
import { useForm, usePage } from "@inertiajs/vue3";
import { Dropdown } from "@/plugins/form";
import { isEmpty } from 'lodash'
import { useCustomers } from "@/composable/useCustomers";
import { useProducts } from "@/composable/useProducts";

defineOptions({
    name: 'Orders'
})

const { products, fetchProducts } = useProducts()
const { customers, fetchCustomers } = useCustomers()

const showModal = ref(false)

const form = useForm({
    id: null,
    customer_id: '',
    product_id: '',
    total_price: null,
    discount: null,
    delivery_charge: null,
    delivery_method: '',
    order_status: '',
    payment_status: '',
    source: '',
    order_date: '',
    shipping_date: '',
    delivery_date: '',
    return_date: '',
    settings: '',
})

const getInnerText = (content) => {
    let div = document.createElement('div')
    div.innerHTML = content
    return div.innerText
}

const handleEdit = (item) => {
    form.reset()
    form.id = item.id
    showModal.value = true
}

const handleSave = () => {
    if(form.follow_date) {
        form.follow_date = format(form.follow_date, 'yyyy/MM/dd')
    }
    if(form.next_follow_date) {
        form.next_follow_date = format(form.next_follow_date, 'yyyy/MM/dd')
    }
    // form.post(route('followUp.save', props.customer.id), {
    //     onFinish() {
    //         if(isEmpty(usePage().props.errors)) {
    //             form.reset()
    //             showModal.value = false
    //         }
    //     }
    // })
}

const handleCustomerFilter = async (event) => {
    await fetchCustomers({
        name: event.query
    })
}
const handleProductFilter = async (event) => {
    await fetchProducts({
        name: event.query
    })
}

onMounted(async () => {
    await fetchCustomers()
    await fetchProducts()
})

</script>