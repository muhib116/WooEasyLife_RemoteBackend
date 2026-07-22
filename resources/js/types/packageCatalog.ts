export type PackageDuration =
    | "free_trial"
    | "1_month"
    | "5_month"
    | "1_year";

export type WebsiteConnectLimit = 1 | 2 | 3 | 4 | 5 | "unlimited";

export type PowerFeatureKey =
    | "create_order"
    | "order_cloning"
    | "call_and_status_log"
    | "ai_intelligence"
    | "app_connect"
    | "unlimited_website_connectivity"
    | "unlimited_app_connectivity"
    | "courier_automation"
    | "custom_status_management"
    | "customer_blacklist"
    | "employee_management"
    | "fake_order_protection"
    | "fraud_customer_checker"
    | "customer_delivery_history"
    | "customer_behavior"
    | "label_and_pos_sticker_print"
    | "missing_orders"
    | "sms_management"
    | "pixel_protection"
    | "parcel_note_history";

export type PackageFeatureKey = PowerFeatureKey;

export type PackageFeatures = Record<PowerFeatureKey, boolean>;

export interface PackageCatalogDraft {
    package_name: string;
    package_duration: PackageDuration;
    /** Number of trial days when package_duration is free_trial */
    trial_days: number;
    order_rate_token: number;
    package_price: number;
    description: string;
    is_active: boolean;
    is_special: boolean;
    app_connect: boolean;
    total_website_connect: WebsiteConnectLimit;
    features: PackageFeatures;
}

export interface PackageCatalogPayload {
    package_name: string;
    package_duration: PackageDuration;
    trial_days: number | null;
    order_rate_token: number;
    package_price: number;
    description: string;
    is_active: boolean;
    is_special: boolean;
    app_connect: boolean;
    total_website_connect: number | null;
    features: PackageFeatures;
    meta: {
        enabled_feature_count: number;
        power_feature_count: number;
    };
}
