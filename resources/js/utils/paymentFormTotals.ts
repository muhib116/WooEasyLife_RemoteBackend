import { isCatalogPackage } from "@/data/packageCatalogDraft";

type PaymentPlan = {
    id: number;
    plan_type?: string | null;
    per_order_rate?: number | null;
    package_price?: number | null;
    order_rate_token?: number | null;
    package_duration?: string | null;
};

export function syncPaymentFormTotals(
    form: {
        package_hub_id: number | null;
        order_limit: number;
        total_amount: number | null;
    },
    plans: PaymentPlan[],
): void {
    const plan = plans.find((item) => item.id === form.package_hub_id);

    if (!plan) {
        return;
    }

    if (isCatalogPackage(plan)) {
        form.total_amount = Number((plan.package_price ?? 0).toFixed(2));
        form.order_limit = plan.order_rate_token ?? 0;

        return;
    }

    if (!form.order_limit) {
        return;
    }

    form.total_amount = Number(
        (Number(plan.per_order_rate ?? 0) * form.order_limit).toFixed(2),
    );
}
