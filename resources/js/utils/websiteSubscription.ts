import { packageDurationLabel } from "@/data/packageCatalogDraft";
import { format, parseISO } from "date-fns";

export type WebsiteSubscription = {
    id: number;
    package_hub_id?: number | null;
    title: string;
    plan_type?: string | null;
    is_active?: boolean;
    remaining_order: number;
    total_order_can_handle: number;
    total_order_handled?: number;
    total_cost?: number;
    per_order_rate?: number;
    order_rate_token?: number | null;
    package_duration?: string | null;
    expires_at?: string | null;
};

export function isCatalogSubscription(
    subscription?: WebsiteSubscription | null,
): boolean {
    return subscription?.plan_type === "catalog";
}

export function subscriptionQuotaLabel(
    subscription?: WebsiteSubscription | null,
): string {
    return isCatalogSubscription(subscription) ? "tokens" : "orders";
}

export function subscriptionPlanBadge(
    subscription?: WebsiteSubscription | null,
): string {
    return isCatalogSubscription(subscription) ? "Catalog plan" : "Legacy plan";
}

export function formatSubscriptionPrice(
    subscription?: WebsiteSubscription | null,
): string {
    if (!subscription) {
        return "—";
    }

    if (isCatalogSubscription(subscription)) {
        const amount = Number(subscription.total_cost ?? 0);
        return amount === 0 ? "Free" : `${amount.toLocaleString()} TK`;
    }

    return `${subscription.per_order_rate ?? 0} TK per order`;
}

export function formatSubscriptionExpiry(value?: string | null): string | null {
    if (!value) {
        return null;
    }

    try {
        return format(parseISO(value), "MMM d, yyyy");
    } catch {
        return value;
    }
}

export function subscriptionDurationLabel(
    subscription?: WebsiteSubscription | null,
): string | null {
    if (!subscription?.package_duration) {
        return null;
    }

    return packageDurationLabel(subscription.package_duration);
}

export function subscriptionUsagePercent(
    subscription?: WebsiteSubscription | null,
): number {
    const quota = Number(subscription?.total_order_can_handle) || 0;
    const used = Number(subscription?.total_order_handled) || 0;

    if (quota <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((used / quota) * 100));
}

export function subscriptionUsageSummary(
    subscription?: WebsiteSubscription | null,
): string {
    if (!subscription) {
        return "No plan assigned";
    }

    const unit = subscriptionQuotaLabel(subscription);
    return `${subscription.remaining_order.toLocaleString()} of ${subscription.total_order_can_handle.toLocaleString()} ${unit} left`;
}

export function primaryWebsiteIssue(issues?: string[]): string | null {
    if (!issues?.length) {
        return null;
    }

    return issues[0] ?? null;
}

export function healthStatusLabel(status?: string): string {
    if (status === "connected") return "Connected";
    if (status === "configured") return "Ready to connect";
    if (status === "disabled") return "Inactive";
    return "Setup needed";
}

export function healthStatusVariant(
    status?: string,
): "success" | "info" | "warning" | "danger" | "neutral" {
    if (status === "connected") return "success";
    if (status === "configured") return "info";
    if (status === "disabled") return "danger";
    return "warning";
}
