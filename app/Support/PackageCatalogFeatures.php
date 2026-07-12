<?php

namespace App\Support;

class PackageCatalogFeatures
{
    /**
     * @return list<string>
     */
    public static function powerKeys(): array
    {
        return config('package_catalog.power_feature_keys', []);
    }

    /**
     * @return list<string>
     */
    public static function legacyPluginKeys(): array
    {
        return config('package_catalog.plugin_feature_keys', []);
    }

    /**
     * @return list<string>
     */
    public static function legacyAppKeys(): array
    {
        return config('package_catalog.app_feature_keys', []);
    }

    /**
     * @return list<string>
     */
    public static function legacyKeys(): array
    {
        return array_merge(self::legacyPluginKeys(), self::legacyAppKeys());
    }

    /**
     * @return array<string, list<string>>
     */
    public static function powerToLegacyMap(): array
    {
        return config('package_catalog.power_to_legacy', []);
    }

    /**
     * @return array<string, string>
     */
    public static function powerLabelsBn(): array
    {
        return config('package_catalog.power_feature_labels_bn', []);
    }

    /**
     * @return array<string, string>
     */
    public static function powerLabelsEn(): array
    {
        return config('package_catalog.power_feature_labels_en', []);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    public static function buildPluginFeatureLines(array $plan): array
    {
        if (! PlanDisplayPresenter::isCatalogPlan($plan)) {
            return [];
        }

        return array_column(PlanDisplayPresenter::buildAllFeatures($plan), 'label');
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    public static function buildPluginSummaryLines(array $plan): array
    {
        return PlanDisplayPresenter::buildSummaryLines($plan);
    }

    public static function countEnabledPowerFeatures(?array $features): int
    {
        $power = self::collapseToPower($features);

        return collect($power)->filter(fn ($enabled) => (bool) $enabled)->count();
    }

    /**
     * @return array<string, bool>
     */
    public static function map(
        bool $default = true,
        ?array $enabledKeys = null,
        ?array $disabledKeys = null,
    ): array {
        $features = [];

        foreach (self::powerKeys() as $key) {
            $features[$key] = $default;
        }

        if ($enabledKeys !== null) {
            foreach (self::powerKeys() as $key) {
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
                'app_connect',
                'ai_intelligence',
                'employee_management',
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
                'sms_management',
                'missing_orders',
                'fake_order_protection',
                'label_and_pos_sticker_print',
            ],
        );
    }

    /**
     * Normalize persisted/admin input to the configured power keys.
     *
     * @param  array<string, mixed>|null  $input
     * @param  array<string, bool>|null  $fallback
     * @return array<string, bool>
     */
    public static function normalize(?array $input, ?array $fallback = null): array
    {
        $collapsed = self::collapseToPower($input);
        $base = self::collapseToPower($fallback) ?? self::map(default: false);

        $normalized = [];

        foreach (self::powerKeys() as $key) {
            if (array_key_exists($key, $collapsed)) {
                $normalized[$key] = filter_var($collapsed[$key], FILTER_VALIDATE_BOOLEAN);
            } else {
                $normalized[$key] = (bool) ($base[$key] ?? false);
            }
        }

        self::applyLegacyPowerKeyInference($normalized, $input);

        return $normalized;
    }

    /**
     * Infer newly split power keys from parent toggles on packages saved before the split.
     *
     * @param  array<string, bool>  $normalized
     * @param  array<string, mixed>|null  $input
     */
    private static function applyLegacyPowerKeyInference(array &$normalized, ?array $input): void
    {
        if ($input === null) {
            return;
        }

        $explicit = static fn (string $key): bool => array_key_exists($key, $input);

        if (! $explicit('customer_delivery_history') && ($normalized['ai_intelligence'] ?? false)) {
            $normalized['customer_delivery_history'] = true;
        }

        if (! $explicit('customer_behavior') && ($normalized['ai_intelligence'] ?? false)) {
            $normalized['customer_behavior'] = true;
        }

        if (! $explicit('call_and_status_log') && ($normalized['app_connect'] ?? false)) {
            $normalized['call_and_status_log'] = true;
        }
    }

    /**
     * Collapse legacy granular keys (or mixed input) into power keys.
     *
     * @param  array<string, mixed>|null  $features
     * @return array<string, bool>
     */
    public static function collapseToPower(?array $features): array
    {
        if ($features === null) {
            return [];
        }

        $power = [];

        foreach (self::powerKeys() as $key) {
            if (array_key_exists($key, $features)) {
                $power[$key] = filter_var($features[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (self::looksLikePowerFormat($power, $features)) {
            return $power;
        }

        $collapsed = [];

        foreach (self::powerKeys() as $powerKey) {
            $collapsed[$powerKey] = false;
        }

        foreach (self::powerToLegacyMap() as $powerKey => $legacyKeys) {
            foreach ($legacyKeys as $legacyKey) {
                if (filter_var($features[$legacyKey] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $collapsed[$powerKey] = true;
                    break;
                }
            }
        }

        foreach (self::powerKeys() as $powerKey) {
            if (array_key_exists($powerKey, $features)) {
                $collapsed[$powerKey] = filter_var($features[$powerKey], FILTER_VALIDATE_BOOLEAN)
                    || ($collapsed[$powerKey] ?? false);
            }
        }

        return $collapsed;
    }

    /**
     * Expand power keys to legacy granular keys for plugin API / landing configs.
     *
     * @param  array<string, mixed>|null  $features
     * @return array<string, bool>
     */
    public static function expandForLegacyApi(?array $features): array
    {
        $power = self::collapseToPower($features);
        $legacy = [];

        foreach (self::legacyKeys() as $key) {
            $legacy[$key] = false;
        }

        foreach (self::powerToLegacyMap() as $powerKey => $legacyKeys) {
            if (! ($power[$powerKey] ?? false)) {
                continue;
            }

            foreach ($legacyKeys as $legacyKey) {
                $legacy[$legacyKey] = true;
            }
        }

        return $legacy;
    }

    /**
     * @param  array<string, bool>  $power
     * @param  array<string, mixed>  $original
     */
    private static function looksLikePowerFormat(array $power, array $original): bool
    {
        $powerKeyHits = count(array_intersect(array_keys($power), self::powerKeys()));
        $legacyKeyHits = count(array_intersect(array_keys($original), self::legacyKeys()));

        if ($powerKeyHits === 0) {
            return false;
        }

        return $legacyKeyHits === 0 || $powerKeyHits >= $legacyKeyHits;
    }
}
