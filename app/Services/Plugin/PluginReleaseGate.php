<?php

namespace App\Services\Plugin;

/**
 * Server-side plugin rollout eligibility.
 *
 * A client cannot opt itself into a release. Query parameters such as
 * channel, rollout, eligible, and force are ignored. Identity is the
 * license bearer the site already sends. The Origin header is recorded
 * for the audit line and is never an allowlist key.
 *
 * Versions with no rollout block stay globally visible. That preserves
 * releases already on the hub. A newly stored release defaults to hold
 * in PluginsController, so a new upload is not offered until an operator
 * sets a mode.
 *
 * Republishing an older version does not roll a site backward. This gate
 * offers the highest eligible version. WordPress only installs a version
 * greater than the one already installed, so a corrective release must
 * use a higher version number.
 */
class PluginReleaseGate
{
    public const MODES = ['hold', 'paused', 'revoked', 'internal', 'allowlist', 'percent', 'global'];

    /**
     * @param  array<string, mixed>  $query
     * @return array{legacy: bool, license_hash: string}
     */
    public static function caller(?string $authorization, ?string $origin, array $query = []): array
    {
        unset($origin, $query);

        $token = '';
        if (is_string($authorization) && preg_match('/^Bearer\s+(\S+)/i', trim($authorization), $matches) === 1) {
            $token = $matches[1];
        }

        if ($token === '') {
            return ['legacy' => true, 'license_hash' => ''];
        }

        return [
            'legacy' => false,
            'license_hash' => hash('sha256', $token),
        ];
    }

    /**
     * @param  list<array{version: string, created_at?: int, settings?: array<string, mixed>, path?: string}>  $versions
     * @param  array{legacy: bool, license_hash: string}  $caller
     * @return array{version: string, created_at?: int, settings?: array<string, mixed>, path?: string}|null
     */
    public static function select(array $versions, array $caller): ?array
    {
        $ranked = $versions;
        usort($ranked, function (array $a, array $b): int {
            $byVersion = version_compare((string) ($b['version'] ?? ''), (string) ($a['version'] ?? ''));
            if ($byVersion !== 0) {
                return $byVersion;
            }

            return ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0));
        });

        foreach ($ranked as $version) {
            if (self::eligible($version, $caller)) {
                return $version;
            }
        }

        return null;
    }

    /**
     * @param  array{version?: string, settings?: array<string, mixed>}  $version
     * @param  array{legacy: bool, license_hash: string}  $caller
     */
    public static function eligible(array $version, array $caller): bool
    {
        $settings = is_array($version['settings'] ?? null) ? $version['settings'] : [];
        $rollout = $settings['rollout'] ?? null;

        if (! is_array($rollout) || ! isset($rollout['mode'])) {
            return true;
        }

        $mode = strtolower(trim((string) $rollout['mode']));
        if (! in_array($mode, self::MODES, true)) {
            return false;
        }

        if (in_array($mode, ['hold', 'paused', 'revoked'], true)) {
            return false;
        }

        if ($mode === 'global') {
            return true;
        }

        $hash = (string) ($caller['license_hash'] ?? '');
        if ($hash === '' || ! empty($caller['legacy'])) {
            return false;
        }

        if ($mode === 'percent') {
            $percent = (int) ($rollout['percent'] ?? 0);
            if ($percent <= 0) {
                return false;
            }
            if ($percent >= 100) {
                return true;
            }

            $bucket = hexdec(substr(hash('sha256', $hash), 0, 8)) % 100;

            return $bucket < $percent;
        }

        $allowed = $rollout['license_hashes'] ?? [];
        if (! is_array($allowed)) {
            return false;
        }

        return in_array($hash, $allowed, true);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function holdUnlessSpecified(array $settings): array
    {
        if (! isset($settings['rollout']) || ! is_array($settings['rollout']) || ! isset($settings['rollout']['mode'])) {
            $settings['rollout'] = ['mode' => 'hold'];
        }

        return $settings;
    }

    /**
     * @param  array{version?: string, settings?: array<string, mixed>}  $version
     * @param  array{legacy: bool, license_hash: string}  $caller
     * @return array<string, mixed>
     */
    public static function audit(array $version, array $caller, bool $served): array
    {
        $settings = is_array($version['settings'] ?? null) ? $version['settings'] : [];
        $mode = is_array($settings['rollout'] ?? null) ? (string) ($settings['rollout']['mode'] ?? 'legacy') : 'legacy';

        return [
            'version' => (string) ($version['version'] ?? ''),
            'mode' => $mode,
            'served' => $served,
            'legacy_client' => ! empty($caller['legacy']),
            'license_hash_prefix' => substr((string) ($caller['license_hash'] ?? ''), 0, 12),
        ];
    }
}
