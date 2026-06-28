<?php

namespace App\Support;

class PackageCatalogFeatures
{
    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_merge(
            config('package_catalog.plugin_feature_keys', []),
            config('package_catalog.app_feature_keys', []),
        );
    }

    /**
     * @param  list<string>|null  $enabledKeys  When set, only these keys are true (others false).
     * @param  list<string>|null  $disabledKeys  Keys forced to false.
     * @return array<string, bool>
     */
    public static function map(
        bool $default = true,
        ?array $enabledKeys = null,
        ?array $disabledKeys = null,
    ): array {
        $features = [];

        foreach (self::allKeys() as $key) {
            $features[$key] = $default;
        }

        if ($enabledKeys !== null) {
            foreach (self::allKeys() as $key) {
                $features[$key] = in_array($key, $enabledKeys, true);
            }
        }

        if ($disabledKeys !== null) {
            foreach ($disabledKeys as $key) {
                $features[$key] = false;
            }
        }

        return $features;
    }

    /**
     * @return array<string, bool>
     */
    public static function starterMap(): array
    {
        return self::map(
            default: true,
            disabledKeys: [
                'ai_image_to_order_create',
                'ai_driven_customer_scoring',
                'courier_webhook_integrations',
                'marketing_tools',
                'database_migration',
                'one_click_app_connect',
                'multistore_order_notifications',
                'cross_store_order_detection',
                'common_dashboard',
                'courier_movement_notification',
                'centralized_notifications',
            ],
        );
    }

    /**
     * @return array<string, bool>
     */
    public static function trialMap(): array
    {
        return self::map(
            default: false,
            enabledKeys: [
                'fraud_customer_checker',
                'duplicate_order_validation',
                'checkout_form_validation',
                'missing_orders',
                'invoice_print',
                'customer_sms_for_order',
            ],
        );
    }
}
