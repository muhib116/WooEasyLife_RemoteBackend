import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";

export type SubscriptionPaymentMethod = {
    payment_partner: string;
    account: string;
    note?: string | null;
    steps?: string[];
};

/**
 * Payment instructions from Admin → Landing Settings (via shared Inertia props).
 */
export function useSubscriptionPaymentMethods() {
    const page = usePage();

    return computed(() => {
        const fromServer = page.props.subscriptionPaymentMethods as
            | SubscriptionPaymentMethod[]
            | undefined;

        if (!Array.isArray(fromServer)) {
            return [];
        }

        return fromServer
            .filter((method) => method?.payment_partner && method?.account)
            .map((method) => ({
                paymentPartner: method.payment_partner,
                account: method.account,
                note: method.note ?? "",
                steps: method.steps ?? [],
            }));
    });
}
