export type PlanCatalogFeature = {
    key: string;
    label: string;
    enabled: boolean;
};

export type DisplayPlan = {
    catalog_features?: PlanCatalogFeature[];
    all_features?: Array<{ key: string; label: string }>;
    enabled_feature_count?: number;
    features_heading?: string | null;
};

export function resolvePlanCatalogFeatures(plan: DisplayPlan): PlanCatalogFeature[] {
    if (plan.catalog_features?.length) {
        return plan.catalog_features;
    }

    return (plan.all_features ?? []).map((feature) => ({
        ...feature,
        enabled: true,
    }));
}

export function planEnabledFeatureCount(
    plan: DisplayPlan,
    features: PlanCatalogFeature[],
): number {
    if (typeof plan.enabled_feature_count === 'number') {
        return plan.enabled_feature_count;
    }

    return features.filter((feature) => feature.enabled).length;
}
