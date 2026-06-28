export function formatStatusLabel(value?: string | null): string {
    if (!value) {
        return "—";
    }

    const normalized = String(value).trim().toLowerCase();

    const map: Record<string, string> = {
        pending: "Pending",
        approved: "Approved",
        cancelled: "Cancelled",
        canceled: "Cancelled",
        active: "Active",
        inactive: "Inactive",
        enabled: "Enabled",
        disabled: "Disabled",
        none: "None",
        connected: "Connected",
        configured: "Configured",
        incomplete: "Incomplete",
        ready: "Connected",
    };

    return map[normalized] ?? normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

export function formatSeverityLabel(value?: string | null): string {
    if (!value) {
        return "—";
    }

    const map: Record<string, string> = {
        danger: "Critical",
        warning: "Warning",
        info: "Info",
    };

    return map[value] ?? formatStatusLabel(value);
}

export function formatUserRoleLabel(role?: string | null): string {
    if (!role) {
        return "—";
    }

    const map: Record<string, string> = {
        user: "Merchant",
        admin: "Admin",
        merchant_staff: "Team member",
    };

    return map[role] ?? formatStatusLabel(role);
}
