<template>
    <AuthenticatedLayout title="Product List">
        <Table.Basic
            :items="data"
            :options="options"
            title="Products"
        />
        <!-- 
        <div class="card flex justify-center">
            <Button label="Check" icon="pi pi-check" />
        </div> -->
        <!-- <SelectButton v-model="size" :options="sizeOptions" optionLabel="label" dataKey="label" /> -->
        <!-- <DataTable 
            :value="data" 
            stripedRows
            size="large"
            tableStyle="min-width: 50rem"
        >
            <Column field="name" header="Name"></Column>
        </DataTable> -->
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Table } from "@/plugins";
import { AuthenticatedLayout } from "@/layouts";
import Button from "primevue/button"
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'


const data = [
    {
        name: "Frozen Yogurt",
        calories: 159,
        fat: 6.0,
        carbs: 24,
        protein: 4.0,
        iron: "1",
    },
    {
        name: "Jelly bean",
        calories: 375,
        fat: 0.0,
        carbs: 94,
        protein: 0.0,
        iron: "0",
    },
    {
        name: "KitKat",
        calories: 518,
        fat: 26.0,
        carbs: 65,
        protein: 7,
        iron: "6",
    },
    {
        name: "Eclair",
        calories: 262,
        fat: 16.0,
        carbs: 23,
        protein: 6.0,
        iron: "7",
    },
    {
        name: "Gingerbread",
        calories: 356,
        fat: 16.0,
        carbs: 49,
        protein: 3.9,
        iron: "16",
    }
];

const options = [
    {
        title: "Dessert (100g serving)",
        align: "left",
        sortable: false,
        key: "name",
    },
    { title: "Calories", key: "calories", align: "left" },
    { title: "Fat (g)", key: "fat", align: "left" },
    { title: "Carbs (g)", key: "carbs", align: "left" },
    { title: "Protein (g)", key: "protein", align: "left" },
    { title: "Iron (%)", key: "iron", align: "right" },
];
</script>
