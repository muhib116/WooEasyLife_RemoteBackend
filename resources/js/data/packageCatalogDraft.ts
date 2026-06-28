import type {
    AppFeatureKey,
    PackageCatalogDraft,
    PackageCatalogPayload,
    PackageDuration,
    PackageFeatureKey,
    PackageFeatures,
    PluginFeatureKey,
    WebsiteConnectLimit,
} from "@/types/packageCatalog";

export const PACKAGE_DURATION_OPTIONS: {
    label: string;
    value: PackageDuration;
}[] = [
    { label: "Free Trial", value: "free_trial" },
    { label: "1 Month", value: "1_month" },
    { label: "5 Month", value: "5_month" },
    { label: "1 Year", value: "1_year" },
];

export function packageDurationLabel(
    value?: PackageDuration | string | null,
): string {
    if (!value) {
        return "—";
    }

    return (
        PACKAGE_DURATION_OPTIONS.find((option) => option.value === value)
            ?.label ?? value
    );
}

export const WEBSITE_CONNECT_OPTIONS: {
    label: string;
    value: WebsiteConnectLimit;
}[] = [
    { label: "1 store", value: 1 },
    { label: "2 stores", value: 2 },
    { label: "3 stores", value: 3 },
    { label: "4 stores", value: 4 },
    { label: "5 stores", value: 5 },
    { label: "Unlimited", value: "unlimited" },
];

export const PLUGIN_FEATURE_DEFINITIONS: {
    key: PluginFeatureKey;
    label: string;
    group: string;
}[] = [
    {
        key: "three_courier_partner_integration",
        label: "Three Courier Partner Integration",
        group: "Courier",
    },
    {
        key: "courier_entry_automation",
        label: "Courier Entry Automation",
        group: "Courier",
    },
    {
        key: "courier_auto_status_sync",
        label: "Automatic Status Sync with Courier Partner",
        group: "Courier",
    },
    {
        key: "courier_webhook_integrations",
        label: "Courier Webhook Integrations",
        group: "Courier",
    },
    {
        key: "fraud_customer_checker",
        label: "Fraud Customer Checker",
        group: "Customer",
    },
    {
        key: "customer_delivery_history",
        label: "AI Driven Customer Delivery History",
        group: "Customer",
    },
    {
        key: "customer_sms_for_order",
        label: "Customer SMS For Order",
        group: "SMS",
    },
    { key: "bulk_sms", label: "Bulk SMS", group: "SMS" },
    {
        key: "ai_text_order_create",
        label: "Text order create",
        group: "AI Featuree",
    },
    {
        key: "ai_image_to_order_create",
        label: "Image To Order Create",
        group: "AI Featuree",
    },
    {
        key: "ai_incomplete_address_autocomplete",
        label: "Incomplete Address Auto Complete",
        group: "AI Featuree",
    },
    {
        key: "ai_driven_customer_scoring",
        label: "AI Driven Customer Scoring",
        group: "AI Featuree",
    },
    {
        key: "checkout_form_validation",
        label: "Checkout Form Validation",
        group: "Checkout",
    },
    {
        key: "duplicate_order_validation",
        label: "Duplicate Order Validation",
        group: "Orders",
    },
    {
        key: "checkout_otp_validation",
        label: "Checkout Page OTP Validation",
        group: "Checkout",
    },
    { key: "ip_block", label: "IP Block", group: "Block & restrict" },
    {
        key: "phone_email_block",
        label: "Phone Number / Email Block",
        group: "Block & restrict",
    },
    { key: "device_block", label: "Device Block", group: "Block & restrict" },
    {
        key: "bd_ip_restriction",
        label: "IP Restriction (Only Bangladeshi IP)",
        group: "Block & restrict",
    },
    {
        key: "store_api_checkout_protection",
        label: "Store API Checkout Protection",
        group: "Checkout",
    },
    {
        key: "daily_order_limit",
        label: "Daily Order Limit",
        group: "Checkout",
    },
    {
        key: "custom_status_manage",
        label: "Custom Status Manage",
        group: "Orders",
    },
    {
        key: "customer_blacklist",
        label: "Customer Black List",
        group: "Block & restrict",
    },
    {
        key: "database_migration",
        label: "Database Migration",
        group: "Tools",
    },
    {
        key: "marketing_tools",
        label: "Marketing Tools",
        group: "Tools",
    },
    { key: "missing_orders", label: "Missing Orders", group: "Orders" },
    {
        key: "missing_order_one_click_create",
        label: "One Click Order Creation From Missing Order",
        group: "Orders",
    },
    {
        key: "pos_sticker_print",
        label: "POS Sticker Print",
        group: "Print",
    },
    { key: "invoice_print", label: "Invoice Print", group: "Print" },
    { key: "order_cloning", label: "Order Cloning", group: "Orders" },
    {
        key: "customer_behavior_track",
        label: "AI Driven Customer Behavior Track",
        group: "Customer",
    },
    {
        key: "repeat_customer_identifier",
        label: "Repeat Customer Identifier",
        group: "Customer",
    },
    {
        key: "order_source_identifier",
        label: "Order Source Identifier",
        group: "Orders",
    },
    {
        key: "inline_shipping_change",
        label: "Inline Shipping Information Change",
        group: "Orders",
    },
    {
        key: "order_note_management",
        label: "Order Note Management",
        group: "Orders",
    },
    { key: "cod_change", label: "COD Change", group: "Orders" },
    {
        key: "ordered_product_management",
        label: "Ordered Product Management",
        group: "Orders",
    },
    {
        key: "order_edit_product_variation",
        label: "Order Edit With Product Variation Handle",
        group: "Orders",
    },
    {
        key: "quick_action_tool",
        label: "Quick Action Tool",
        group: "Orders",
    },
];

export const APP_FEATURE_DEFINITIONS: {
    key: AppFeatureKey;
    label: string;
}[] = [
    {
        key: "one_click_app_connect",
        label: "One Click App Connect",
    },
    {
        key: "multistore_order_notifications",
        label: "Multi Store New Order Notification in App",
    },
    {
        key: "customer_call_identifier",
        label: "Customer Call Identifier",
    },
    {
        key: "cross_store_order_detection",
        label: "Cross Store Order Detection",
    },
    {
        key: "call_history_with_duration",
        label: "Call History with Duration",
    },
    {
        key: "common_dashboard",
        label: "Common Dashboard For All Connected Store",
    },
    {
        key: "courier_movement_notification",
        label: "Courier Product Movement Notification",
    },
    {
        key: "notification_sound_management",
        label: "Individual Notification Sound Management",
    },
    {
        key: "admin_employee_manage",
        label: "Admin and Employee Manage",
    },
    {
        key: "centralized_notifications",
        label: "Centralized Notifications",
    },
];

function defaultFeatureMap(): PackageFeatures {
    const features = {} as PackageFeatures;

    for (const item of PLUGIN_FEATURE_DEFINITIONS) {
        features[item.key] = true;
    }

    for (const item of APP_FEATURE_DEFINITIONS) {
        features[item.key] = true;
    }

    return features;
}

export function buildDefaultPackageDraft(): PackageCatalogDraft {
    return {
        package_name: "",
        package_duration: "1_month",
        trial_days: 14,
        order_rate_token: 1000,
        package_price: 0,
        description: "",
        is_active: true,
        is_special: false,
        app_connect: true,
        total_website_connect: 3,
        features: defaultFeatureMap(),
    };
}

export function groupedPluginFeatures(): Record<string, typeof PLUGIN_FEATURE_DEFINITIONS> {
    return PLUGIN_FEATURE_DEFINITIONS.reduce(
        (groups, item) => {
            if (!groups[item.group]) {
                groups[item.group] = [];
            }

            groups[item.group].push(item);

            return groups;
        },
        {} as Record<string, typeof PLUGIN_FEATURE_DEFINITIONS>,
    );
}

function resolveWebsiteConnectLimit(
    value: WebsiteConnectLimit,
    appConnect: boolean,
): number | null {
    if (!appConnect) {
        return null;
    }

    return value === "unlimited" ? null : value;
}

function normalizeFeaturesForPayload(
    draft: PackageCatalogDraft,
): PackageFeatures {
    const features = { ...draft.features };

    for (const item of APP_FEATURE_DEFINITIONS) {
        features[item.key] = draft.app_connect ? features[item.key] : false;
    }

    return features;
}

export function buildPackagePayload(
    draft: PackageCatalogDraft,
): PackageCatalogPayload {
    const features = normalizeFeaturesForPayload(draft);
    const enabledFeatureCount = Object.values(features).filter(Boolean).length;
    const pluginFeatureCount = PLUGIN_FEATURE_DEFINITIONS.filter(
        (item) => features[item.key],
    ).length;
    const appFeatureCount = APP_FEATURE_DEFINITIONS.filter(
        (item) => features[item.key],
    ).length;

    return {
        package_name: draft.package_name.trim(),
        package_duration: draft.package_duration,
        trial_days:
            draft.package_duration === "free_trial" ? draft.trial_days : null,
        order_rate_token: draft.order_rate_token,
        package_price: draft.package_price,
        description: draft.description.trim(),
        is_active: draft.is_active,
        is_special: draft.is_special,
        app_connect: draft.app_connect,
        total_website_connect: resolveWebsiteConnectLimit(
            draft.total_website_connect,
            draft.app_connect,
        ),
        features,
        meta: {
            enabled_feature_count: enabledFeatureCount,
            plugin_feature_count: pluginFeatureCount,
            app_feature_count: appFeatureCount,
        },
    };
}

export function countEnabledFeatures(features: PackageFeatures): number {
    return Object.values(features).filter(Boolean).length;
}

export function setAllFeatures(
    features: PackageFeatures,
    enabled: boolean,
    keys?: PackageFeatureKey[],
): PackageFeatures {
    const next = { ...features };
    const targetKeys = keys ?? (Object.keys(features) as PackageFeatureKey[]);

    for (const key of targetKeys) {
        next[key] = enabled;
    }

    return next;
}

export function isCatalogPackage(pkg: {
    package_duration?: string | null;
    order_rate_token?: number | null;
}): boolean {
    return pkg.package_duration != null || pkg.order_rate_token != null;
}

function mergePackageFeatures(
    stored?: Partial<PackageFeatures> | Record<string, boolean> | null,
): PackageFeatures {
    const features = defaultFeatureMap();

    if (!stored) {
        return features;
    }

    for (const key of Object.keys(features) as PackageFeatureKey[]) {
        if (key in stored) {
            features[key] = Boolean(stored[key]);
        }
    }

    return features;
}

export function buildDraftFromPackageHub(pkg: {
    title?: string | null;
    package_duration?: PackageDuration | string | null;
    trial_days?: number | null;
    order_rate_token?: number | null;
    package_price?: number | null;
    description?: string | null;
    is_active?: boolean;
    is_special?: boolean;
    app_connect?: boolean;
    total_website_connect?: number | null;
    features?: Partial<PackageFeatures> | Record<string, boolean> | null;
}): PackageCatalogDraft {
    const appConnect = Boolean(pkg.app_connect);

    return {
        package_name: pkg.title?.trim() || "",
        package_duration: (pkg.package_duration as PackageDuration) || "1_month",
        trial_days: pkg.trial_days ?? 14,
        order_rate_token: pkg.order_rate_token ?? 0,
        package_price: Number(pkg.package_price ?? 0),
        description: pkg.description || "",
        is_active: pkg.is_active !== false,
        is_special: Boolean(pkg.is_special),
        app_connect: appConnect,
        total_website_connect:
            pkg.total_website_connect == null
                ? "unlimited"
                : (pkg.total_website_connect as WebsiteConnectLimit),
        features: mergePackageFeatures(pkg.features),
    };
}

export function websiteConnectLabel(
    value?: number | null,
    appConnect?: boolean,
): string {
    if (!appConnect) {
        return "Not included";
    }

    if (value == null) {
        return "Unlimited stores";
    }

    return `${value} store${value === 1 ? "" : "s"}`;
}

export function enabledFeatureLabels(
    features?: Partial<PackageFeatures> | Record<string, boolean> | null,
): { plugin: string[]; app: string[] } {
    const plugin = PLUGIN_FEATURE_DEFINITIONS.filter(
        (item) => features?.[item.key],
    ).map((item) => item.label);

    const app = APP_FEATURE_DEFINITIONS.filter(
        (item) => features?.[item.key],
    ).map((item) => item.label);

    return { plugin, app };
}
