import type { PackageFeatures, PowerFeatureKey } from "@/types/packageCatalog";

export type ActivePackage = {
    id: number;
    package_hub_id: number;
    plan_type: string;
    title: string;
    expires_at: string | null;
    remaining_order: number;
    total_order_can_handle: number;
    features?: Partial<PackageFeatures> | Record<string, boolean> | null;
};

export function isFeatureEnabled(
    features: ActivePackage["features"],
    key: PowerFeatureKey,
): boolean {
    return Boolean(features?.[key]);
}

export function hasPackageFeature(
    activePackage: ActivePackage | null | undefined,
    key: PowerFeatureKey,
): boolean {
    if (!activePackage) {
        return false;
    }

    return isFeatureEnabled(activePackage.features, key);
}

export function hasAllPackageFeatures(
    activePackage: ActivePackage | null | undefined,
    keys: PowerFeatureKey[],
): boolean {
    return keys.every((key) => hasPackageFeature(activePackage, key));
}

export function hasAnyPackageFeature(
    activePackage: ActivePackage | null | undefined,
    keys: PowerFeatureKey[],
): boolean {
    return keys.some((key) => hasPackageFeature(activePackage, key));
}
