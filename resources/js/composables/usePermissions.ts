import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(
        () => (page.props.auth as any)?.permissions ?? [],
    );

    const isSuperAdmin = computed<boolean>(
        () => Boolean((page.props.auth as any)?.is_super_admin),
    );

    const can = (permission: string): boolean => {
        if (isSuperAdmin.value) {
            return true;
        }

        return permissions.value.includes(permission);
    };

    return {
        permissions,
        isSuperAdmin,
        can,
    };
}
