import axios from "axios"
import { ref } from "vue"
import { Customer } from "@/types";

export const useProducts = () => {
    const products = ref<Customer[]>([])

    const fetchProducts = async (payload?: any) => {
        const { data } = await axios.post(route('products.filter'), payload)
        products.value = data || []
    }

    return {
        products,
        fetchProducts
    }
}