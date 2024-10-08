import axios from "axios"
import { ref } from "vue"
import { Customer } from "@/types";

export const useCustomers = () => {
    const customers = ref<Customer[]>([])

    const fetchCustomers = async (payload?: any) => {
        const { data } = await axios.post(route('customers.filter'), payload)
        customers.value = data || []
    }

    return {
        customers,
        fetchCustomers
    }
}