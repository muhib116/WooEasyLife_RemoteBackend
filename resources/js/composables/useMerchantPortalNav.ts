import { usePermissions } from "@/composables/usePermissions";
import { computed } from "vue";
import type { IconName } from "@/types";

export function useMerchantPortalNav() {
    const { can } = usePermissions();

    const navItems = computed(() => {
        const items: Array<{ title: string; name: string; icon: IconName }> = [
            { title: "Dashboard", name: "portal.dashboard", icon: "PhChartBar" },
        ];

        if (can("websites.view")) {
            items.push({ title: "Websites", name: "portal.websites", icon: "PhGlobe" });
        }

        if (can("billing.view")) {
            items.push({ title: "Billing", name: "portal.billing", icon: "PhCreditCard" });
        }

        if (can("employees.view")) {
            items.push({ title: "Team", name: "portal.employees", icon: "PhUsersThree" });
        }

        return items;
    });

    return { navItems };
}
