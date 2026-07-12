export type SubscriptionPaymentMethod = {
    paymentPartner: string;
    account: string;
    note: string;
    steps: string[];
};

/**
 * @deprecated Prefer useSubscriptionPaymentMethods() — accounts come from Landing Settings.
 * Kept as an empty list so accidental imports never show hardcoded numbers.
 */
export const subscriptionPaymentMethods: SubscriptionPaymentMethod[] = [];
