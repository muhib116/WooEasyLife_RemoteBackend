import { differenceInMilliseconds, parseISO } from "date-fns";

export type SubscriptionExpiryStatus = "none" | "expiring" | "expired";

export const DEFAULT_EXPIRY_WARN_DAYS = 7;

export function parseSubscriptionExpiry(
    value?: string | null,
): Date | null {
    if (!value) {
        return null;
    }

    try {
        return parseISO(value);
    } catch {
        return null;
    }
}

export function getSubscriptionExpiryStatus(
    expiresAt: Date | null,
    warnWithinDays = DEFAULT_EXPIRY_WARN_DAYS,
): SubscriptionExpiryStatus {
    if (!expiresAt) {
        return "none";
    }

    const msLeft = differenceInMilliseconds(expiresAt, new Date());

    if (msLeft <= 0) {
        return "expired";
    }

    const daysLeft = msLeft / (1000 * 60 * 60 * 24);

    if (daysLeft <= warnWithinDays) {
        return "expiring";
    }

    return "none";
}

export function formatExpiryCountdown(
    expiresAt: Date,
    now = new Date(),
): string {
    const diffMs = differenceInMilliseconds(expiresAt, now);
    const expired = diffMs <= 0;
    const absMs = Math.abs(diffMs);

    const days = Math.floor(absMs / 86_400_000);
    const hours = Math.floor((absMs % 86_400_000) / 3_600_000);
    const minutes = Math.floor((absMs % 3_600_000) / 60_000);
    const seconds = Math.floor((absMs % 60_000) / 1_000);

    if (expired) {
        if (days > 0) {
            return `Expired ${days} day${days === 1 ? "" : "s"} ago`;
        }

        if (hours > 0) {
            return `Expired ${hours} hour${hours === 1 ? "" : "s"} ago`;
        }

        if (minutes > 0) {
            return `Expired ${minutes} minute${minutes === 1 ? "" : "s"} ago`;
        }

        return "Expired just now";
    }

    const parts: string[] = [];

    if (days > 0) {
        parts.push(`${days}d`);
    }

    parts.push(`${hours}h`, `${minutes}m`, `${seconds}s`);

    return parts.join(" ");
}

export function subscriptionExpiryTitle(
    status: SubscriptionExpiryStatus,
): string | null {
    if (status === "expired") {
        return "Subscription expired";
    }

    if (status === "expiring") {
        return "Subscription expiring soon";
    }

    return null;
}
