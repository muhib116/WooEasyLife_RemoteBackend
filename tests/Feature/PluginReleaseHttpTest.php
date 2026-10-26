<?php

namespace Tests\Feature;

use App\Models\PluginsVersion;
use App\Models\User;
use App\Services\Plugin\PluginReleaseGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use WooEasyLife\Includes\Release\Gate;

/**
 * HTTP proof of the release gate. Uses API_Backend_testing only.
 * Aborts if the connection is the local hub database.
 */
class PluginReleaseHttpTest extends TestCase
{
    use RefreshDatabase;

    private const UPLOAD_KEY = 'step8-upload-key';

    private const LICENSE_A = 'step8-license-site-a';

    private const LICENSE_B = 'step8-license-site-b';

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.mysql.database');
        if ($database !== 'API_Backend_testing') {
            $this->fail('Refusing to run rollout HTTP tests against '.$database);
        }

        Config::set('services.plugin_upload.api_key', self::UPLOAD_KEY);
        require_once dirname(__DIR__, 4).'/wp/wordpress/wp-content/plugins/woo-easy-life/includes/Release/Gate.php';
    }

    public function test_hold_allowlist_pause_revoke_and_global_over_http(): void
    {
        $this->seedLegacy('1.7.9', 'WEL-STEP8-LEGACY');
        $held = $this->upload('1.7.10', 'WEL-STEP8-CANDIDATE');

        $nobody = $this->pluginClient('1.7.9', null);
        $this->assertSame('1.7.9', $nobody['version']);
        $this->assertFalse($nobody['upgrade']);

        $siteA = $this->pluginClient('1.7.9', self::LICENSE_A, ['channel' => 'global', 'force' => '1']);
        $siteB = $this->pluginClient('1.7.9', self::LICENSE_B, ['rollout' => 'allowlist']);
        $this->assertSame('1.7.9', $siteA['version']);
        $this->assertSame('1.7.9', $siteB['version']);
        $this->assertFalse($siteA['upgrade']);

        $hashA = hash('sha256', self::LICENSE_A);
        $this->setRollout($held, '1.7.10', ['mode' => 'allowlist', 'license_hashes' => [$hashA]]);

        $allowed = $this->pluginClient('1.7.9', self::LICENSE_A, ['channel' => 'global', 'force' => '1']);
        $other = $this->pluginClient('1.7.9', self::LICENSE_B, ['origin_spoof' => '1']);
        $invalid = $this->pluginClient('1.7.9', 'not-a-real-license');
        $this->assertSame('1.7.10', $allowed['version']);
        $this->assertTrue($allowed['upgrade']);
        $this->assertSame('1.7.9', $other['version']);
        $this->assertSame('1.7.9', $invalid['version']);
        $this->assertSame(
            $allowed['version'],
            $this->pluginClient('1.7.9', self::LICENSE_A)['version'],
            'a second metadata read stays on the same release'
        );

        $download = $this->download(self::LICENSE_A);
        $this->assertSame($allowed['sha256'], hash('sha256', $download));
        $this->assertStringStartsWith('WEL-STEP8-CANDIDATE', $download);
        $this->assertTrue(Gate::package_matches($this->writeTemp($download), (string) $allowed['sha256']));

        $otherDownload = $this->download(self::LICENSE_B);
        $this->assertStringStartsWith('WEL-STEP8-LEGACY', $otherDownload);
        $this->assertNotSame(hash('sha256', $download), hash('sha256', $otherDownload));

        $this->assertSame(15 * 60, Gate::cache_ttl(['rollout' => ['mode' => 'allowlist']], 6 * 3600, 15 * 60));

        $this->setRollout($held, '1.7.10', ['mode' => 'internal', 'license_hashes' => [$hashA]]);
        $this->assertSame('1.7.10', $this->pluginClient('1.7.9', self::LICENSE_A)['version']);
        $this->assertSame('1.7.9', $this->pluginClient('1.7.9', self::LICENSE_B)['version']);

        $this->setRollout($held, '1.7.10', ['mode' => 'percent', 'percent' => 100]);
        $this->assertSame('1.7.10', $this->pluginClient('1.7.9', self::LICENSE_A)['version']);
        $this->setRollout($held, '1.7.10', ['mode' => 'percent', 'percent' => 0]);
        $this->assertSame('1.7.9', $this->pluginClient('1.7.9', self::LICENSE_A)['version']);
        $this->setRollout($held, '1.7.10', ['mode' => 'percent', 'percent' => 50]);
        $first = $this->pluginClient('1.7.9', self::LICENSE_A)['version'];
        $second = $this->pluginClient('1.7.9', self::LICENSE_A)['version'];
        $this->assertSame($first, $second);

        $this->setRollout($held, '1.7.10', ['mode' => 'paused']);
        $this->assertSame('1.7.9', $this->pluginClient('1.7.9', self::LICENSE_A)['version']);
        $this->assertFalse(Gate::is_upgrade('1.7.10', '1.7.9'));

        $this->setRollout($held, '1.7.10', ['mode' => 'revoked']);
        $this->assertSame('1.7.9', $this->pluginClient('1.7.9', self::LICENSE_A)['version']);
        $this->assertSame('1.7.9', $this->pluginClient('1.7.9', null)['version']);

        $this->setRollout($held, '1.7.10', ['mode' => 'global']);
        $this->assertSame('1.7.10', $this->pluginClient('1.7.9', self::LICENSE_A)['version']);
        $this->assertSame('1.7.10', $this->pluginClient('1.7.9', null)['version']);
        $this->assertFalse(Gate::is_upgrade('1.7.10', '1.7.9'));
        $this->assertSame(6 * 3600, Gate::cache_ttl(['rollout' => ['mode' => 'global']], 6 * 3600, 15 * 60));
    }

    public function test_a_later_upload_of_an_older_version_does_not_replace_the_candidate(): void
    {
        $this->seedLegacy('1.7.9', 'WEL-STEP8-OLD');
        $candidate = $this->upload('1.7.10', 'WEL-STEP8-NEW');
        $this->setRollout($candidate, '1.7.10', ['mode' => 'global']);
        $this->travel(2)->minutes();
        $this->seedLegacy('1.7.8', 'WEL-STEP8-OLDER');

        $this->assertSame('1.7.10', $this->pluginClient('1.7.9', null)['version']);
    }

    private function pluginClient(string $installed, ?string $license, array $query = []): array
    {
        $headers = [
            'Accept' => 'application/json',
            'origin' => 'https://shop.test',
        ];
        if ($license !== null) {
            $headers['Authorization'] = 'Bearer '.$license;
        }

        $response = $this->withHeaders($headers)->getJson('/get-metadata?'.http_build_query($query));
        $response->assertOk();
        $version = (string) $response->json('version');

        return [
            'version' => $version,
            'upgrade' => Gate::is_upgrade($installed, $version),
            'sha256' => (string) $response->json('sha256'),
            'caller_ignored_query' => PluginReleaseGate::caller(
                $license === null ? null : 'Bearer '.$license,
                'https://shop.test',
                $query
            ),
        ];
    }

    private function download(?string $license): string
    {
        $headers = ['origin' => 'https://shop.test'];
        if ($license !== null) {
            $headers['Authorization'] = 'Bearer '.$license;
        }

        $response = $this->withHeaders($headers)->get('/download-plugins')->assertOk();

        return $response->streamedContent();
    }

    private function upload(string $version, string $marker): PluginsVersion
    {
        $source = storage_path('app/step8-'.$version.'.bin');
        if (! is_dir(dirname($source))) {
            mkdir(dirname($source), 0777, true);
        }
        file_put_contents($source, $marker);

        $this->withHeaders([
            'Authorization' => 'Bearer '.self::UPLOAD_KEY,
            'Accept' => 'application/json',
        ])->post('/api/admin/plugins/versions', [
            'version' => $version,
            'settings' => json_encode([
                'name' => 'WooEasyLife',
                'slug' => 'woo-easy-life',
                'version' => $version,
                'download_url' => 'https://api.wpsalehub.com/download-plugins',
            ]),
            'file' => new UploadedFile($source, 'plugin.zip', 'application/zip', null, true),
        ])->assertCreated();

        $row = PluginsVersion::query()->where('version', $version)->firstOrFail();
        $this->assertSame('hold', $row->settings['rollout']['mode'] ?? null);

        return $row;
    }

    private function seedLegacy(string $version, string $marker): void
    {
        $relative = 'app/private/wpsalehub-'.$version.'.zip';
        $absolute = storage_path($relative);
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0777, true);
        }
        file_put_contents($absolute, $marker);

        PluginsVersion::query()->updateOrCreate(
            ['version' => $version],
            [
                'path' => $relative,
                'download_count' => 0,
                'settings' => [
                    'name' => 'WooEasyLife',
                    'slug' => 'woo-easy-life',
                    'version' => $version,
                    'download_url' => 'https://api.wpsalehub.com/download-plugins',
                    'sha256' => hash('sha256', $marker),
                ],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $rollout
     */
    private function setRollout(PluginsVersion $row, string $version, array $rollout): void
    {
        $settings = $row->fresh()->settings;
        $settings['rollout'] = $rollout;
        $settings['version'] = $version;

        $admin = User::query()->firstOrCreate(
            ['email' => 'step8-plugin-admin@example.com'],
            [
                'name' => 'Step8 Admin',
                'phone' => '01700000000',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => true,
            ]
        );

        $this->actingAs($admin)
            ->post(route('plugins.updateVersion', $row->id), [
                'version' => $version,
                'settings' => json_encode($settings),
            ])
            ->assertRedirect(route('plugins.index'));
    }

    private function writeTemp(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wel-step8-');
        file_put_contents($path, $bytes);

        return $path;
    }
}
