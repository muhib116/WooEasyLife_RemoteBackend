

export interface User {
    id: number,
    name: string
    phone: string
    email?: string
    created_at: Date
}
export interface Customer {
    id: number,
    name: string
    phone: string
    email?: string
    type: 'lead'|'customer'
    settings: any
    converted_at: Date
}

export interface Address {
    id: any;
    customer_id: any;
    phone: string;
    district: string;
    thana: string;
    address: string;
}