<?php

namespace App\WiseAi\Governance;

use App\Models\WiseAi\WiseApiKey;

/**
 * Merchant policy overlay from wise_api_keys.meta.governance (sparse).
 */
class MerchantPolicy
{
    /**
     * @return array{
     *     mode: string,
     *     allow_auto: bool,
     *     policy_version: string,
     *     feature_flags: array<string, bool>,
     *     sandbox: bool
     * }
     */
    public function resolve(WiseApiKey $apiKey): array
    {
        $defaults = PolicyPack::defaults();
        $gov = is_array($apiKey->meta['governance'] ?? null) ? $apiKey->meta['governance'] : [];

        $mode = strtolower((string) ($gov['mode'] ?? $defaults['mode']));
        if (! in_array($mode, PolicyPack::ALLOWED_MODES, true)) {
            $mode = PolicyPack::DEFAULT_MODE;
        }

        $allowAuto = (bool) ($gov['allow_auto'] ?? $defaults['allow_auto']);
        // Constitution: Auto never assumed.
        if ($mode === 'auto' && ! $allowAuto) {
            $mode = 'assist';
        }

        $flags = $defaults['feature_flags'];
        $overlayFlags = is_array($gov['feature_flags'] ?? null) ? $gov['feature_flags'] : [];
        foreach ($overlayFlags as $key => $value) {
            if (is_string($key)) {
                $flags[$key] = (bool) $value;
            }
        }
        $flags['auto_send'] = $allowAuto && $mode === 'auto';

        $policyVersion = trim((string) ($gov['policy_version'] ?? ''));
        if ($policyVersion === '') {
            $policyVersion = 'merchant-default';
        }

        return [
            'mode' => $mode,
            'allow_auto' => $allowAuto,
            'policy_version' => $policyVersion,
            'feature_flags' => $flags,
            'sandbox' => (bool) ($gov['sandbox'] ?? $apiKey->meta['sandbox'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>  Normalized governance meta fragment
     */
    public function normalizeUpdate(array $input, ?array $current = null): array
    {
        $current = is_array($current) ? $current : [];
        $mode = strtolower((string) ($input['mode'] ?? $current['mode'] ?? PolicyPack::DEFAULT_MODE));
        if (! in_array($mode, PolicyPack::ALLOWED_MODES, true)) {
            $mode = PolicyPack::DEFAULT_MODE;
        }

        $allowAuto = array_key_exists('allow_auto', $input)
            ? (bool) $input['allow_auto']
            : (bool) ($current['allow_auto'] ?? false);

        if ($mode === 'auto' && ! $allowAuto) {
            $mode = 'assist';
        }

        $flags = is_array($current['feature_flags'] ?? null) ? $current['feature_flags'] : [];
        if (isset($input['feature_flags']) && is_array($input['feature_flags'])) {
            foreach ($input['feature_flags'] as $key => $value) {
                if (is_string($key)) {
                    $flags[$key] = (bool) $value;
                }
            }
        }

        $sandbox = array_key_exists('sandbox', $input)
            ? (bool) $input['sandbox']
            : (bool) ($current['sandbox'] ?? false);

        // Bump merchant policy version on every intentional save.
        $prev = (string) ($current['policy_version'] ?? 'merchant-default');
        if (preg_match('/^merchant-(\d+)$/', $prev, $m)) {
            $policyVersion = 'merchant-'.((int) $m[1] + 1);
        } else {
            $policyVersion = 'merchant-1';
        }

        return [
            'mode' => $mode,
            'allow_auto' => $allowAuto,
            'sandbox' => $sandbox,
            'feature_flags' => $flags,
            'policy_version' => $policyVersion,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
