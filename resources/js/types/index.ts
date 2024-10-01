export * from './icons'

export interface Customer {
    id: number
    name: string
    phone: string
    email: string
    type: 'customer' | 'lead'
    settings: any
}