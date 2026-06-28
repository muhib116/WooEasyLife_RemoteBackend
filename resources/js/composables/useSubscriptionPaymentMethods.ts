import { subscriptionPaymentMethods as defaultMethods } from "@/data/subscriptionPaymentMethods";
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";

export type SubscriptionPaymentMethod = {
    payment_partner: string;
    account: string;
    note?: string | null;
    steps?: string[];
};

export function useSubscriptionPaymentMethods() {
    const page = usePage();

    return computed(() => {
        const fromServer = page.props.subscriptionPaymentMethods as
            | SubscriptionPaymentMethod[]
            | undefined;

        if (Array.isArray(fromServer) && fromServer.length) {
            return fromServer.map((method) => ({
                paymentPartner: method.payment_partner,
                account: method.account,
                note: method.note ?? "",
                steps: method.steps ?? [],
            }));
        }

        return defaultMethods;
    });
}
