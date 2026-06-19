<template>
    <AuthenticatedLayout title="Product List">
        <Table.Basic :items="customers" :options="options">
            <template #headerBefore>
                <Button @click="showForm = true"> Create </Button>
            </template>
            <template #type="{ item }">
                <Tag
                    :value="item.type"
                    :severity="item.type == 'lead' ? 'warn' : 'success'"
                    rounded
                />
            </template>
            <template #action="{ item }">
                <TableActions>
                    <TableActionButton
                        action="map"
                        tooltip="View address"
                        :loading="item.loading"
                        @click="() => (selectedCustomer = item)"
                    />
                    <Link :href="route('followUp.view', item.id)">
                        <TableActionButton
                            action="view"
                            as="span"
                            tooltip="Follow up"
                        />
                    </Link>
                    <TableActionButton
                        action="edit"
                        tooltip="Edit customer"
                        @click="handleEdit(item)"
                    />
                    <TableActionButton
                        action="delete"
                        tooltip="Delete customer"
                        :loading="item.loading"
                        @click="handleDelete($event, item)"
                    />
                </TableActions>
            </template>
        </Table.Basic>
        <Dialog
            v-model:visible="showForm"
            :header="`${customerForm.id ? 'Edit' : 'Create'} Customer`"
            modal
            :style="{ width: '35rem' }"
            :draggable="true"
        >
            <form @submit.prevent="handleSave">
                <div class="flex items-center gap-4 mb-4">
                    <label for="username" class="font-semibold w-24"
                        >Name</label
                    >
                    <InputText
                        v-model="customerForm.name"
                        id="username"
                        class="flex-auto"
                        autocomplete="off"
                    />
                </div>
                <div class="flex items-center gap-4 mb-4">
                    <label for="phone" class="font-semibold w-24">Phone</label>
                    <InputText
                        v-model="customerForm.phone"
                        id="phone"
                        class="flex-auto"
                        autocomplete="off"
                    />
                </div>
                <div class="flex items-center gap-4 mb-4">
                    <label for="email" class="font-semibold w-24">Email</label>
                    <InputText
                        v-model="customerForm.email"
                        id="email"
                        class="flex-auto"
                        autocomplete="off"
                    />
                </div>
                <div class="flex items-center gap-4 mb-8">
                    <label for="address" class="font-semibold w-24"
                        >Address</label
                    >
                    <InputText
                        v-model="customerForm.address"
                        id="address"
                        class="flex-auto"
                        autocomplete="off"
                    />
                </div>
                <div class="flex items-center gap-4 mb-8">
                    <label for="phone" class="font-semibold w-24">Type</label>
                    <div class="flex flex-wrap gap-4">
                        <div class="flex items-center">
                            <RadioButton
                                v-model="customerForm.type"
                                inputId="ingredient2"
                                name="type"
                                value="lead"
                            />
                            <label for="ingredient2" class="ml-2">Lead</label>
                        </div>
                        <div class="flex items-center">
                            <RadioButton
                                v-model="customerForm.type"
                                inputId="ingredient3"
                                name="type"
                                value="customer"
                            />
                            <label for="ingredient3" class="ml-2"
                                >Customer</label
                            >
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        @click="showForm = false"
                    ></Button>
                    <Button
                        type="submit"
                        :label="customerForm.id ? 'Update' : 'Create'"
                        :loading="customerForm.processing"
                        @click="handleSave"
                    ></Button>
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:visible="selectedCustomer"
            header="Flex Scroll"
            :style="{ width: '75vw' }"
            maximizable
            modal
            :contentStyle="{ height: '500px' }"
        >
            <AddressList
                v-if="activeTab == 'list'"
                :customer="selectedCustomer"
            />

            <template #footer>
                <Button
                    label="Ok"
                    icon="pi pi-check"
                    @click="selectedCustomer = false"
                />
            </template>
        </Dialog>

        <!-- <Dialog
            v-model:visible="selectedCustomer"
            :style="{ width: '45rem' }"
            modal
            maximizable
            header="Address"
            :contentStyle="{ height: '300px' }"
        >
            <Tabs class="relative hi" v-model:value="activeTab">
                <TabList class="!sticky top-0">
                    <Tab value="list">Address list</Tab>
                    <Tab value="form">Add new one</Tab>
                </TabList>
            </Tabs>
            <AddressList
                v-if="activeTab == 'list'"
                :customer="selectedCustomer"
            />
            <AddressPopup
                v-if="activeTab == 'form'"
                :customer="selectedCustomer"
                :active="!!selectedCustomer?.id"
            />

            <template #footer>
                <Button label="Close" @click="selectedCustomer = null" />
            </template>
        </Dialog> -->

        <Toast />
        <ConfirmPopup></ConfirmPopup>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Table, Markdown } from "@/plugins";
import { AuthenticatedLayout } from "@/layouts";
import { ref } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import RadioButton from "primevue/radiobutton";
import ConfirmPopup from "primevue/confirmpopup";
import Tag from "primevue/tag";
import { useForm, router, Link } from "@inertiajs/vue3";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import AddressPopup from "./fragments/AddressPopup.vue";
import AddressList from "./fragments/AddressList.vue";
import TableActions from "@/Pages/Users/fragments/TableActions.vue";
import TableActionButton from "@/Pages/Users/fragments/TableActionButton.vue";
import { Customer } from "@/types";

const props = defineProps<{
    customers: any[];
}>();

const confirm = useConfirm();
const toast = useToast();
const showForm = ref(false);
const activeTab = ref<"form" | "list">("list");
const selectedCustomer = ref<Customer>();

const customerForm = useForm({
    id: null,
    name: "",
    phone: "",
    email: "",
    address: "",
    type: "lead",
});

const handleEdit = (item) => {
    customerForm.id = item.id;
    customerForm.name = item.name;
    customerForm.phone = item.phone;
    customerForm.email = item.email;
    customerForm.address = item.address;
    showForm.value = true;
};

const handleDelete = (event, item) => {
    confirm.require({
        target: event.currentTarget,
        message: `Do you want to delete ${item.name} from customer?`,
        icon: "pi pi-info-circle",
        rejectProps: {
            label: "Cancel",
            severity: "secondary",
            outlined: true,
        },
        acceptProps: {
            label: "Delete",
            severity: "danger",
        },
        accept: () => {
            console.log("done");
            router.post(route("customers.delete", item.id));
            item.loading = true;
            // toast.add({ severity: 'info', summary: 'Confirmed', detail: 'Record deleted', life: 3000 });
        },
        reject: () => {
            console.log("un done");
            item.loading = false;
            toast.add({
                severity: "error",
                summary: "Rejected",
                detail: "You have rejected",
                life: 3000,
            });
        },
    });
};

const handleSave = async () => {
    customerForm.post(route("customers.save"), {
        onFinish() {
            showForm.value = false;
            customerForm.reset();
        },
    });
};

const options = [
    {
        title: "Name",
        align: "left",
        sortable: false,
        key: "name",
    },
    {
        title: "Phone",
        align: "left",
        sortable: false,
        key: "phone",
    },
    {
        title: "Email",
        align: "left",
        sortable: false,
        key: "email",
    },
    {
        title: "Type",
        align: "left",
        sortable: false,
        key: "type",
    },
    {
        title: "Action",
        align: "right",
        sortable: false,
        key: "action",
    },
];
</script>
