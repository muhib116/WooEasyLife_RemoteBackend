import axios from "axios"
import { ref } from "vue"
import { Customer } from "@/types";

export const useCustomers = () => {
    const customers = ref<Customer[]>([])

    const fetchCustomers = async (payload?: any) => {
        const { data } = await axios.post(route('customers.filter'), payload)
        customers.value = data || []
    }

    const getCustomerAddress = async (customerId: number) => {
        const { data } = await axios.get(route('customers.address', customerId))
        return data
    }

    return {
        customers,
        fetchCustomers,
        getCustomerAddress
    }
}