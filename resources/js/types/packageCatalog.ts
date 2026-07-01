export type PackageDuration =
    | "free_trial"
    | "1_month"
    | "5_month"
    | "1_year";

export type WebsiteConnectLimit = 1 | 2 | 3 | 4 | 5 | "unlimited";

export type PowerFeatureKey =
    | "app_connect"
    | "app_store_limit"
    | "fraud_customer_checker"
    | "sms_management"
    | "missing_orders"
    | "fake_order_protection"
    | "customer_blacklist"
    | "custom_status_management"
    | "employee_management"
    | "courier_automation"
    | "ai_intelligence"
    | "label_and_pos_sticker_print";

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
