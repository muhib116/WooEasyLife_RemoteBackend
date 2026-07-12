import type {
    PackageCatalogDraft,
    PackageCatalogPayload,
    PackageDuration,
    PackageFeatureKey,
    PackageFeatures,
    PowerFeatureKey,
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

export const POWER_TO_LEGACY_MAP: Record<PowerFeatureKey, string[]> = {
    create_order: ["customer_order_create"],
    order_cloning: ["order_cloning"],
    call_and_status_log: [
        "call_history_with_duration",
        "customer_call_identifier",
        "order_note_management",
        "order_source_identifier",
    ],
    ai_intelligence: [
        "ai_text_order_create",
        "ai_image_to_order_create",
        "ai_incomplete_address_autocomplete",
        "ai_driven_customer_scoring",
    ],
    app_connect: [
        "one_click_app_connect",
        "multistore_order_notifications",
        "cross_store_order_detection",
        "common_dashboard",
        "courier_movement_notification",
        "notification_sound_management",
        "centralized_notifications",
    ],
    courier_automation: [
        "three_courier_partner_integration",
        "courier_entry_automation",
        "courier_auto_status_sync",
        "courier_webhook_integrations",
    ],
    custom_status_management: ["custom_status_manage"],
    customer_blacklist: ["customer_blacklist"],
    employee_management: ["admin_employee_manage"],
    fake_order_protection: [
        "duplicate_order_validation",
        "checkout_form_validation",
        "checkout_otp_validation",
        "daily_order_limit",
        "store_api_checkout_protection",
        "ip_block",
        "phone_email_block",
        "device_block",
        "bd_ip_restriction",
    ],
    fraud_customer_checker: ["fraud_customer_checker"],
    customer_delivery_history: ["customer_delivery_history"],
    customer_behavior: ["customer_behavior_track", "repeat_customer_identifier"],
    label_and_pos_sticker_print: ["pos_sticker_print", "invoice_print"],
    missing_orders: ["missing_orders", "missing_order_one_click_create"],
    sms_management: ["customer_sms_for_order", "bulk_sms"],
    pixel_protection: ["pixel_protection"],
};

export const POWER_FULL_FEATURE_DEFINITIONS: {
    key: PowerFeatureKey;
    label: string;
}[] = [
    { key: "create_order", label: "কাস্টম অর্ডার তৈরি" },
    { key: "order_cloning", label: "অর্ডার ক্লোনিং" },
    { key: "call_and_status_log", label: "কল ও স্ট্যাটাস লগ" },
    { key: "ai_intelligence", label: "এআই ইন্টেলিজেন্স" },
    { key: "app_connect", label: "অ্যাপ কানেক্ট" },
    { key: "courier_automation", label: "কুরিয়ার অটোমেশন" },
    { key: "custom_status_management", label: "কাস্টম স্ট্যাটাস ম্যানেজমেন্ট" },
    { key: "customer_blacklist", label: "কাস্টমার ব্ল্যাকলিস্ট" },
    { key: "employee_management", label: "এমপ্লয়ী ম্যানেজমেন্ট" },
    { key: "fake_order_protection", label: "ফেক অর্ডার প্রোটেকশন" },
    { key: "fraud_customer_checker", label: "ফ্রড কাস্টমার চেকার" },
    { key: "customer_delivery_history", label: "কাস্টমার ডেলিভারি হিস্ট্রি" },
    { key: "customer_behavior", label: "কাস্টমার বিহেভিয়ার" },
    { key: "label_and_pos_sticker_print", label: "লেবেল ও POS স্টিকার প্রিন্ট" },
    { key: "missing_orders", label: "মিসিং অর্ডার" },
    { key: "sms_management", label: "এসএমএস ম্যানেজমেন্ট" },
    { key: "pixel_protection", label: "পিক্সেল প্রোটেকশন" },
];

const LEGACY_KEYS = new Set(
    Object.values(POWER_TO_LEGACY_MAP).flatMap((keys) => keys),
);

function defaultFeatureMap(): PackageFeatures {
    const features = {} as PackageFeatures;

    for (const item of POWER_FULL_FEATURE_DEFINITIONS) {
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

function resolveWebsiteConnectLimit(
    value: WebsiteConnectLimit,
    appConnect: boolean,
): number | null {
    if (!appConnect) {
        return null;
    }

    return value === "unlimited" ? null : value;
}

function looksLikePowerFormat(
    power: Partial<PackageFeatures>,
    original: Record<string, boolean>,
): boolean {
    const powerKeyHits = Object.keys(power).filter((key) =>
        POWER_FULL_FEATURE_DEFINITIONS.some((item) => item.key === key),
    ).length;
    const legacyKeyHits = Object.keys(original).filter((key) =>
        LEGACY_KEYS.has(key),
    ).length;

    if (powerKeyHits === 0) {
        return false;
    }

    return legacyKeyHits === 0 || powerKeyHits >= legacyKeyHits;
}

export function collapseToPowerFeatures(
    features?: Partial<PackageFeatures> | Record<string, boolean> | null,
): PackageFeatures {
    const source = features ?? {};
    const power = {} as PackageFeatures;

    for (const item of POWER_FULL_FEATURE_DEFINITIONS) {
        if (item.key in source) {
            power[item.key] = Boolean(source[item.key]);
        }
    }

    if (looksLikePowerFormat(power, source as Record<string, boolean>)) {
        for (const item of POWER_FULL_FEATURE_DEFINITIONS) {
            if (!(item.key in power)) {
                power[item.key] = false;
            }
        }

        return power;
    }

    const collapsed = {} as PackageFeatures;

    for (const item of POWER_FULL_FEATURE_DEFINITIONS) {
        collapsed[item.key] = false;
    }

    for (const item of POWER_FULL_FEATURE_DEFINITIONS) {
        const legacyKeys = POWER_TO_LEGACY_MAP[item.key] ?? [];

        collapsed[item.key] = legacyKeys.some((legacyKey) =>
            Boolean(source[legacyKey]),
        );

        if (item.key in source) {
            collapsed[item.key] =
                Boolean(source[item.key]) || collapsed[item.key];
        }
    }

    return collapsed;
}

export function syncDraftAppFields(draft: PackageCatalogDraft): void {
    draft.features.app_connect = draft.app_connect;

    if (!draft.app_connect) {
        draft.total_website_connect = 1;
    }
}

export function applyFeatureDrivenAppFields(draft: PackageCatalogDraft): void {
    draft.app_connect = Boolean(draft.features.app_connect);

    if (!draft.app_connect) {
        draft.total_website_connect = 1;
    }
}

function normalizeFeaturesForPayload(
    draft: PackageCatalogDraft,
): PackageFeatures {
    syncDraftAppFields(draft);

    return collapseToPowerFeatures(draft.features);
}

export function buildPackagePayload(
    draft: PackageCatalogDraft,
): PackageCatalogPayload {
    applyFeatureDrivenAppFields(draft);

    const features = normalizeFeaturesForPayload(draft);
    const enabledFeatureCount = Object.values(features).filter(Boolean).length;

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
            power_feature_count: enabledFeatureCount,
        },
    };
}

export function buildAdjustAppFieldsFromSubscription(
    userPackage?: {
        id?: number | null;
        app_connect?: boolean | null;
        total_website_connect?: number | null;
        features?: Partial<PackageFeatures> | Record<string, boolean> | null;
    } | null,
    hubPackage?: {
        app_connect?: boolean;
        total_website_connect?: number | null;
        features?: Partial<PackageFeatures> | Record<string, boolean> | null;
    } | null,
): {
    app_connect: boolean;
    total_website_connect: WebsiteConnectLimit;
    features: PackageFeatures;
} {
    // Existing subscriptions must use the merchant snapshot only.
    // Falling back to PackageHub features is wrong: catalog plans like Pro Plus
    // are often "all on" while user_packages.features may disable some keys.
    // get-user reads the snapshot — the Adjust UI must show the same source.
    const rawFeatures =
        userPackage?.id != null
            ? (userPackage.features ?? {})
            : (userPackage?.features ?? hubPackage?.features);

    const features = normalizeFeatureMap(rawFeatures);

    const appConnect =
        userPackage?.app_connect ??
        (userPackage?.id != null
            ? Boolean(features.app_connect)
            : (hubPackage?.app_connect ?? Boolean(features.app_connect)));

    features.app_connect = appConnect;

    let totalWebsiteConnect: WebsiteConnectLimit;

    if (userPackage?.app_connect !== null && userPackage?.app_connect !== undefined) {
        totalWebsiteConnect =
            userPackage.total_website_connect == null
                ? "unlimited"
                : (userPackage.total_website_connect as WebsiteConnectLimit);
    } else if (hubPackage) {
        totalWebsiteConnect =
            hubPackage.total_website_connect == null
                ? "unlimited"
                : (hubPackage.total_website_connect as WebsiteConnectLimit);
    } else {
        totalWebsiteConnect = 1;
    }

    if (!appConnect) {
        totalWebsiteConnect = 1;
    }

    return {
        app_connect: appConnect,
        total_website_connect: totalWebsiteConnect,
        features,
    };
}

export function buildAdjustSubscriptionPayload<
    T extends Record<string, unknown> & {
        features: PackageFeatures;
        app_connect: boolean;
        total_website_connect: WebsiteConnectLimit;
        plan_type?: string;
    },
>(form: T): T {
    if (form.plan_type !== "catalog") {
        return form;
    }

    form.app_connect = Boolean(form.features.app_connect);
    form.features = { ...form.features, app_connect: form.app_connect };

    if (!form.app_connect) {
        form.total_website_connect = 1;
    }

    const features = collapseToPowerFeatures(form.features);

    return {
        ...form,
        app_connect: form.app_connect,
        total_website_connect: resolveWebsiteConnectLimit(
            form.total_website_connect,
            form.app_connect,
        ) as unknown as WebsiteConnectLimit,
        features,
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
    const targetKeys =
        keys ?? POWER_FULL_FEATURE_DEFINITIONS.map((item) => item.key);

    for (const key of targetKeys) {
        next[key] = enabled;
    }

    return next;
}

export function normalizeFeatureMap(
    features?: Partial<PackageFeatures> | Record<string, boolean> | null,
): PackageFeatures {
    return collapseToPowerFeatures(features);
}

export function isCatalogPackage(pkg: {
    plan_type?: string | null;
    package_duration?: string | null;
    order_rate_token?: number | null;
}): boolean {
    if (pkg.plan_type === "catalog") {
        return true;
    }

    if (pkg.plan_type === "legacy") {
        return false;
    }

    return pkg.package_duration != null || pkg.order_rate_token != null;
}

export function planDropdownLabel(plan: {
    title: string;
    plan_type?: string | null;
    per_order_rate?: number | null;
    package_price?: number | null;
}): string {
    if (isCatalogPackage(plan)) {
        const price = Number(plan.package_price ?? 0);
        return price === 0 ? "Free" : `${price.toLocaleString()} TK`;
    }

    return `${plan.per_order_rate ?? 0} TK/order`;
}

export function planOptionLabel(plan: {
    title: string;
    plan_type?: string | null;
    per_order_rate?: number | null;
    package_price?: number | null;
    order_rate_token?: number | null;
    package_duration?: string | null;
}): string {
    if (isCatalogPackage(plan)) {
        const price = Number(plan.package_price ?? 0);
        const priceLabel = price === 0 ? "Free" : `${price.toLocaleString()} TK`;
        const tokens = (plan.order_rate_token ?? 0).toLocaleString();
        const duration = plan.package_duration
            ? packageDurationLabel(plan.package_duration)
            : null;

        return duration
            ? `${plan.title} · ${priceLabel} · ${duration} · ${tokens} tokens`
            : `${plan.title} · ${priceLabel} · ${tokens} tokens`;
    }

    return `(${plan.per_order_rate ?? 0} TK/order) ${plan.title}`;
}

export function groupPlansForSelect(
    plans: Array<{
        id: number;
        title: string;
        plan_type?: string | null;
        per_order_rate?: number | null;
        package_price?: number | null;
        order_rate_token?: number | null;
        package_duration?: string | null;
    }>,
): Array<{ label: string; items: typeof plans }> {
    const catalog = plans.filter((plan) => isCatalogPackage(plan));
    const legacy = plans.filter((plan) => !isCatalogPackage(plan));

    const groups: Array<{ label: string; items: typeof plans }> = [];

    if (catalog.length) {
        groups.push({ label: "Subscription plans", items: catalog });
    }

    if (legacy.length) {
        groups.push({ label: "Legacy (pay per order)", items: legacy });
    }

    return groups;
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
    const features = collapseToPowerFeatures(pkg.features);

    features.app_connect = appConnect;

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
            !appConnect
                ? 1
                : pkg.total_website_connect == null
                    ? "unlimited"
                    : (pkg.total_website_connect as WebsiteConnectLimit),
        features,
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

export function enabledPowerFeatureLabels(
    features?: Partial<PackageFeatures> | Record<string, boolean> | null,
): string[] {
    const normalized = collapseToPowerFeatures(features);

    return POWER_FULL_FEATURE_DEFINITIONS.filter(
        (item) => normalized[item.key],
    ).map((item) => item.label);
}

/** @deprecated Use enabledPowerFeatureLabels */
export function enabledFeatureLabels(
    features?: Partial<PackageFeatures> | Record<string, boolean> | null,
): { plugin: string[]; app: string[] } {
    const labels = enabledPowerFeatureLabels(features);

    return {
        plugin: labels.filter(
            (label) =>
                !["অ্যাপ কানেক্ট", "এমপ্লয়ী ম্যানেজমেন্ট"].includes(label),
        ),
        app: labels.filter((label) =>
            ["অ্যাপ কানেক্ট", "এমপ্লয়ী ম্যানেজমেন্ট"].includes(label),
        ),
    };
}
