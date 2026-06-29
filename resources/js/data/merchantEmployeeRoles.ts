export type MerchantEmployeeRoleOption = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
};

export function roleLabel(role?: MerchantEmployeeRoleOption | null): string {
    return role?.name ?? "—";
}
