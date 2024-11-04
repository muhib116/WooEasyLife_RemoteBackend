<template>
    <div class="px-2">
        <ul class="space-y-2 font-medium">
            <li
                v-for="(_route, index) in routes"
                :index="index"
            >
                <Link 
                   :href="route(_route.name)"
                    class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group"
                    :class="{
                        'text-black bg-gray-100 dark:!bg-gray-900': route().current(_route.name)
                    }"
                >
                    <Icon
                        :name="_route.icon"
                    />
                    <span class="ms-3">
                        {{ _route.title }}
                    </span>
                </Link>
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import { Icon } from '@/plugins'
import { Link } from '@inertiajs/vue3';
import { IconName } from '@/types';

type SidebarRoutes = {
    title: string
    name: string
    icon: IconName
}

const routes : SidebarRoutes[] = [
    {
        title: 'Dashboard',
        name: 'dashboard',
        icon: 'PhChartBar'
    },
    {
        title: 'Customer',
        name: 'customers.index',
        icon: 'PhUsers'
    },
    {
        title: 'Products',
        name: 'products.index',
        icon: 'PhBookmarks'
    },
    {
        title: 'Orders',
        name: 'orders.index',
        icon: 'PhShoppingCart'
    },
    {
        title: 'Phosphor Icons',
        name: 'icons',
        icon: 'PhListHeart'
    },
    {
        title: 'Prime Icons',
        name: 'icons.prime',
        icon: 'PhListHeart'
    },
]

</script>