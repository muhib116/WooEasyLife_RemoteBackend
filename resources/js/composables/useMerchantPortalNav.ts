import { usePermissions } from "@/composables/usePermissions";
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import type { IconName } from "@/types";

export type MerchantNavLink = {
    title: string;
    name: string;
    icon: IconName;
    active?: string;
};

export type MerchantNavItem = {
    title: string;
    name?: string;
    icon: IconName;
    active?: string;
    children?: MerchantNavLink[];
};

export function useMerchantPortalNav() {
    const { can } = usePermissions();
    const page = usePage();
    const courierHistoryEnabled = computed(() =>
        Boolean((page.props.auth as { portal?: { courier_history?: { enabled?: boolean } } })?.portal?.courier_history?.enabled),
    );

    const navItems = computed(() => {
        const items: MerchantNavItem[] = [
            { title: "Dashboard", name: "portal.dashboard", icon: "PhChartBar" },
        ];

        if (courierHistoryEnabled.value) {
            items.push({
                title: "Courier history",
                name: "portal.fraud-check",
                active: "portal.fraud-check*",
                icon: "PhPackage",
            });
        }

        if (can("websites.view")) {
            items.push({ title: "Websites", name: "portal.websites", icon: "PhGlobe" });
        }

        if (can("billing.view")) {
            items.push({ title: "Billing", name: "portal.billing", icon: "PhCreditCard" });
        }

        if (can("employees.view")) {
            items.push({ title: "Team", name: "portal.employees", icon: "PhUsersThree" });
        }

        items.push({
            title: "Settings",
            icon: "PhGearSix",
            children: [
                { title: "Profile", name: "portal.profile", icon: "PhUser" },
                { title: "Change password", name: "portal.settings.password", icon: "PhLockKey" },
            ],
        });

        return items;
    });

    const tabItems = computed<MerchantNavLink[]>(() =>
        navItems.value.flatMap((item) => {
            if (item.children?.length) {
                return [
                    {
                        title: item.title,
                        name: item.children[0].name,
                        icon: item.icon,
                        active: item.children[0].active ?? item.children[0].name,
                    },
                ];
            }

            return item.name
                ? [{ title: item.title, name: item.name, icon: item.icon, active: item.active }]
                : [];
        }),
    );

    return { navItems, tabItems };
}
