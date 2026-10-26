<?php

namespace Tests\Unit;

use App\Services\Plugin\PluginReleaseGate;
use PHPUnit\Framework\TestCase;

class PluginReleaseGateTest extends TestCase
{
    private function version(string $version, ?array $rollout = null, int $createdAt = 0): array
    {
        $settings = ['version' => $version, 'name' => 'WooEasyLife'];
        if ($rollout !== null) {
            $settings['rollout'] = $rollout;
        }

        return [
            'version' => $version,
            'created_at' => $createdAt,
            'settings' => $settings,
            'path' => 'app/private/wpsalehub-'.$version.'.zip',
        ];
    }

    public function test_a_client_channel_parameter_does_not_change_identity(): void
    {
        $plain = PluginReleaseGate::caller('Bearer site-key', 'https://example.test', []);
        $spoofed = PluginReleaseGate::caller('Bearer site-key', 'https://other.test', [
            'channel' => 'global',
            'rollout' => 'global',
            'eligible' => '1',
            'force' => '1',
        ]);

        $this->assertSame($plain, $spoofed);
        $this->assertFalse($plain['legacy']);
    }

    public function test_a_missing_license_is_a_legacy_client(): void
    {
        $caller = PluginReleaseGate::caller(null, 'https://example.test', ['channel' => 'internal']);

        $this->assertTrue($caller['legacy']);
        $this->assertSame('', $caller['license_hash']);
    }

    public function test_existing_versions_without_rollout_stay_visible(): void
    {
        $legacy = PluginReleaseGate::caller(null, null);

        $this->assertTrue(PluginReleaseGate::eligible($this->version('1.7.9'), $legacy));
    }

    public function test_hold_allowlist_percent_and_global_defaults(): void
    {
        $legacy = PluginReleaseGate::caller(null, null);
        $known = PluginReleaseGate::caller('Bearer canary-key', 'https://canary.test');
        $hash = $known['license_hash'];

        $this->assertFalse(PluginReleaseGate::eligible($this->version('1.7.10', ['mode' => 'hold']), $known));
        $this->assertFalse(PluginReleaseGate::eligible($this->version('1.7.10', ['mode' => 'paused']), $known));
        $this->assertFalse(PluginReleaseGate::eligible($this->version('1.7.10', ['mode' => 'revoked']), $known));
        $this->assertTrue(PluginReleaseGate::eligible($this->version('1.7.10', ['mode' => 'global']), $legacy));

        $allow = $this->version('1.7.10', ['mode' => 'allowlist', 'license_hashes' => [$hash]]);
        $this->assertTrue(PluginReleaseGate::eligible($allow, $known));
        $this->assertFalse(PluginReleaseGate::eligible($allow, $legacy));
        $this->assertFalse(PluginReleaseGate::eligible($allow, PluginReleaseGate::caller('Bearer someone-else', null)));

        $internal = $this->version('1.7.10', ['mode' => 'internal', 'license_hashes' => [$hash]]);
        $this->assertTrue(PluginReleaseGate::eligible($internal, $known));
        $this->assertFalse(PluginReleaseGate::eligible($internal, PluginReleaseGate::caller('Bearer someone-else', 'https://canary.test')));
    }

    public function test_percent_rollout_is_stable_for_the_same_license(): void
    {
        $caller = PluginReleaseGate::caller('Bearer percent-key', null);
        $release = $this->version('1.7.10', ['mode' => 'percent', 'percent' => 100]);
        $none = $this->version('1.7.10', ['mode' => 'percent', 'percent' => 0]);

        $this->assertTrue(PluginReleaseGate::eligible($release, $caller));
        $this->assertFalse(PluginReleaseGate::eligible($none, $caller));
        $this->assertFalse(PluginReleaseGate::eligible($release, PluginReleaseGate::caller(null, null)));

        $bucketed = $this->version('1.7.10', ['mode' => 'percent', 'percent' => 50]);
        $first = PluginReleaseGate::eligible($bucketed, $caller);
        $second = PluginReleaseGate::eligible($bucketed, $caller);
        $this->assertSame($first, $second);
    }

    public function test_selection_offers_the_highest_eligible_version_not_the_newest_upload(): void
    {
        $caller = PluginReleaseGate::caller('Bearer canary-key', null);
        $hash = $caller['license_hash'];
        $versions = [
            $this->version('1.7.9', null, 100),
            $this->version('1.7.10', ['mode' => 'allowlist', 'license_hashes' => ['not-this-site']], 300),
            $this->version('1.7.11', ['mode' => 'hold'], 400),
        ];

        $chosen = PluginReleaseGate::select($versions, $caller);
        $this->assertNotNull($chosen);
        $this->assertSame('1.7.9', $chosen['version']);

        $versions[] = $this->version('1.7.10', ['mode' => 'allowlist', 'license_hashes' => [$hash]], 200);
        $chosen = PluginReleaseGate::select($versions, $caller);
        $this->assertSame('1.7.10', $chosen['version']);
    }

    public function test_a_later_upload_of_an_older_version_does_not_replace_a_higher_one(): void
    {
        $caller = PluginReleaseGate::caller(null, null);
        $chosen = PluginReleaseGate::select([
            $this->version('1.7.10', ['mode' => 'global'], 10),
            $this->version('1.7.9', null, 999),
        ], $caller);

        $this->assertSame('1.7.10', $chosen['version']);
    }

    public function test_pause_and_revoke_stop_new_offers_without_pretending_to_downgrade(): void
    {
        $caller = PluginReleaseGate::caller('Bearer site-key', null);
        $chosen = PluginReleaseGate::select([
            $this->version('1.7.10', ['mode' => 'revoked'], 20),
            $this->version('1.7.9', ['mode' => 'global'], 10),
        ], $caller);

        $this->assertSame('1.7.9', $chosen['version']);
        $this->assertNotSame('1.7.10', $chosen['version']);
    }

    public function test_a_new_release_defaults_to_hold(): void
    {
        $settings = PluginReleaseGate::holdUnlessSpecified(['version' => '1.7.10']);

        $this->assertSame('hold', $settings['rollout']['mode']);
        $this->assertFalse(PluginReleaseGate::eligible([
            'version' => '1.7.10',
            'settings' => $settings,
        ], PluginReleaseGate::caller('Bearer site-key', null)));
    }

    public function test_an_explicit_rollout_mode_is_kept(): void
    {
        $settings = PluginReleaseGate::holdUnlessSpecified([
            'rollout' => ['mode' => 'global'],
        ]);

        $this->assertSame('global', $settings['rollout']['mode']);
    }

    public function test_an_unknown_mode_is_not_offered(): void
    {
        $this->assertFalse(PluginReleaseGate::eligible(
            $this->version('1.7.10', ['mode' => 'everyone']),
            PluginReleaseGate::caller('Bearer site-key', null)
        ));
    }

    public function test_the_audit_line_does_not_include_the_license(): void
    {
        $caller = PluginReleaseGate::caller('Bearer super-secret-license', null);
        $line = PluginReleaseGate::audit($this->version('1.7.10', ['mode' => 'hold']), $caller, false);
        $encoded = json_encode($line);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('super-secret-license', $encoded);
        $this->assertSame('hold', $line['mode']);
        $this->assertFalse($line['served']);
    }
}
