export type PackageDuration =
    | "free_trial"
    | "1_month"
    | "5_month"
    | "1_year";

export type WebsiteConnectLimit = 1 | 2 | 3 | 4 | 5 | "unlimited";

export type PluginFeatureKey =
    | "fraud_customer_checker"
    | "three_courier_partner_integration"
    | "courier_entry_automation"
    | "customer_delivery_history"
    | "customer_sms_for_order"
    | "bulk_sms"
    | "ai_text_order_create"
    | "ai_image_to_order_create"
    | "ai_incomplete_address_autocomplete"
    | "ai_driven_customer_scoring"
    | "daily_order_limit"
    | "checkout_form_validation"
    | "duplicate_order_validation"
    | "checkout_otp_validation"
    | "ip_block"
    | "phone_email_block"
    | "device_block"
    | "bd_ip_restriction"
    | "store_api_checkout_protection"
    | "custom_status_manage"
    | "customer_blacklist"
    | "marketing_tools"
    | "database_migration"
    | "missing_orders"
    | "missing_order_one_click_create"
    | "pos_sticker_print"
    | "invoice_print"
    | "order_cloning"
    | "customer_behavior_track"
    | "repeat_customer_identifier"
    | "order_source_identifier"
    | "inline_shipping_change"
    | "order_note_management"
    | "cod_change"
    | "ordered_product_management"
    | "order_edit_product_variation"
    | "quick_action_tool"
    | "courier_auto_status_sync"
    | "courier_webhook_integrations";

export type AppFeatureKey =
    | "one_click_app_connect"
    | "multistore_order_notifications"
    | "customer_call_identifier"
    | "cross_store_order_detection"
    | "call_history_with_duration"
    | "common_dashboard"
    | "courier_movement_notification"
    | "notification_sound_management"
    | "centralized_notifications"
    | "admin_employee_manage";

export type PackageFeatureKey = PluginFeatureKey | AppFeatureKey;

export type PackageFeatures = Record<PackageFeatureKey, boolean>;

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
        plugin_feature_count: number;
        app_feature_count: number;
    };
}
