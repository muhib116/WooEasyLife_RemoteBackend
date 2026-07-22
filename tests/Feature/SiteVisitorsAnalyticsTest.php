<?php

namespace Tests\Feature;

use App\Models\SiteVisitorDailyStat;
use App\Models\SiteVisitorEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteVisitorsAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'site_visitors.enabled' => true,
            'site_visitors.page_view_dedupe_seconds' => 30,
            'site_visitors.heartbeat_min_interval_seconds' => 15,
        ]);
    }

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'phone' => '01700000088',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    public function test_public_page_view_is_recorded(): void
    {
        $this->postJson(route('siteVisitors.event'), [
            'path' => '/bd-fraud-checker',
            'event' => 'page_view',
            'visitor_id' => str_repeat('ab', 16),
            'utm_source' => 'google',
            'utm_medium' => 'organic',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('site_visitor_events', [
            'path' => '/bd-fraud-checker',
            'event_type' => SiteVisitorEvent::TYPE_PAGE_VIEW,
            'source_channel' => 'organic',
            'utm_source' => 'google',
        ]);
    }

    public function test_unknown_paths_are_rejected_by_allowlist(): void
    {
        $this->postJson(route('siteVisitors.event'), [
            'path' => '/sessions',
            'event' => 'page_view',
            'visitor_id' => str_repeat('88', 16),
        ])->assertOk()->assertJsonPath('ok', false);

        $this->postJson(route('siteVisitors.event'), [
            'path' => '/invented-admin-path',
            'event' => 'page_view',
            'visitor_id' => str_repeat('88', 16),
        ])->assertOk()->assertJsonPath('ok', false);

        $this->assertSame(0, SiteVisitorEvent::query()->count());
    }

    public function test_admin_paths_are_rejected(): void
    {
        $this->postJson(route('siteVisitors.event'), [
            'path' => '/dashboard',
            'event' => 'page_view',
            'visitor_id' => str_repeat('cd', 16),
        ])->assertOk()->assertJsonPath('ok', false);

        $this->assertSame(0, SiteVisitorEvent::query()->count());
    }

    public function test_page_views_are_deduped_in_short_window_but_count_revisits(): void
    {
        $payload = [
            'path' => '/en',
            'event' => 'page_view',
            'visitor_id' => str_repeat('ef', 16),
        ];

        $this->postJson(route('siteVisitors.event'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        // Immediate double-fire ignored.
        $this->postJson(route('siteVisitors.event'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', false);

        $this->assertSame(1, SiteVisitorEvent::query()->where('event_type', 'page_view')->count());

        // After short window, a revisit counts as another pageview.
        Cache::flush();
        $this->postJson(route('siteVisitors.event'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(2, SiteVisitorEvent::query()->where('event_type', 'page_view')->count());
    }

    public function test_heartbeat_updates_max_engaged_ms_when_gated(): void
    {
        $vid = str_repeat('99', 16);

        $this->postJson(route('siteVisitors.event'), [
            'path' => '/bd-fraud-checker',
            'event' => 'heartbeat',
            'engaged_ms' => 5000,
            'visitor_id' => $vid,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(1, SiteVisitorEvent::query()->where('event_type', 'heartbeat')->count());
        $this->assertSame(5000, (int) SiteVisitorEvent::query()->value('engaged_ms'));

        // Gate still closed — should update max engaged_ms, not insert another row.
        $this->postJson(route('siteVisitors.event'), [
            'path' => '/bd-fraud-checker',
            'event' => 'heartbeat',
            'engaged_ms' => 18000,
            'visitor_id' => $vid,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(1, SiteVisitorEvent::query()->where('event_type', 'heartbeat')->count());
        $this->assertSame(18000, (int) SiteVisitorEvent::query()->value('engaged_ms'));

        // Lower value should not decrease.
        $this->postJson(route('siteVisitors.event'), [
            'path' => '/bd-fraud-checker',
            'event' => 'heartbeat',
            'engaged_ms' => 9000,
            'visitor_id' => $vid,
        ])->assertOk()->assertJsonPath('ok', false);

        $this->assertSame(18000, (int) SiteVisitorEvent::query()->value('engaged_ms'));
    }

    public function test_scroll_heartbeat_cta_and_tool_events(): void
    {
        $vid = str_repeat('11', 16);

        $this->postJson(route('siteVisitors.event'), [
            'path' => '/',
            'event' => 'page_view',
            'visitor_id' => $vid,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->postJson(route('siteVisitors.event'), [
            'path' => '/',
            'event' => 'scroll_depth',
            'scroll_pct' => 50,
            'visitor_id' => $vid,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->postJson(route('siteVisitors.event'), [
            'path' => '/',
            'event' => 'heartbeat',
            'engaged_ms' => 12000,
            'visitor_id' => $vid,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->postJson(route('siteVisitors.event'), [
            'path' => '/',
            'event' => 'cta_click',
            'cta_label' => 'Start free trial',
            'visitor_id' => $vid,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->postJson(route('siteVisitors.event'), [
            'path' => '/',
            'event' => 'tool_action',
            'action_name' => 'fraud_check_submit',
            'visitor_id' => $vid,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(5, SiteVisitorEvent::query()->count());
    }

    public function test_invalid_event_fails_validation(): void
    {
        $this->postJson(route('siteVisitors.event'), [
            'path' => '/',
            'event' => 'explode',
            'visitor_id' => str_repeat('22', 16),
        ])->assertStatus(422);
    }

    public function test_ingest_is_throttled(): void
    {
        // Keep under pageview quota / avoid dedupe by varying allowed paths.
        for ($i = 0; $i < 60; $i++) {
            $this->postJson(route('siteVisitors.event'), [
                'path' => '/blog/tool-'.$i,
                'event' => 'page_view',
                'visitor_id' => str_repeat('33', 16),
            ])->assertOk();
        }

        $this->postJson(route('siteVisitors.event'), [
            'path' => '/blog/tool-60',
            'event' => 'page_view',
            'visitor_id' => str_repeat('33', 16),
        ])->assertStatus(429);
    }

    public function test_admin_visitors_index_and_reports(): void
    {
        SiteVisitorEvent::query()->create([
            'path' => '/bd-fraud-checker',
            'event_type' => SiteVisitorEvent::TYPE_PAGE_VIEW,
            'visitor_hash' => str_repeat('aa', 16),
            'session_hash' => str_repeat('bb', 16),
            'source_channel' => 'organic',
            'utm_source' => 'google',
            'utm_medium' => 'organic',
            'device_type' => 'desktop',
        ]);
        SiteVisitorEvent::query()->create([
            'path' => '/bd-fraud-checker',
            'event_type' => SiteVisitorEvent::TYPE_CTA_CLICK,
            'visitor_hash' => str_repeat('aa', 16),
            'session_hash' => str_repeat('bb', 16),
            'source_channel' => 'organic',
            'cta_label' => 'Trial',
            'device_type' => 'desktop',
        ]);
        SiteVisitorEvent::query()->create([
            'path' => '/return-loss-calculator',
            'event_type' => SiteVisitorEvent::TYPE_PAGE_VIEW,
            'visitor_hash' => str_repeat('cc', 16),
            'session_hash' => str_repeat('dd', 16),
            'source_channel' => 'direct',
            'device_type' => 'mobile',
        ]);
        SiteVisitorEvent::query()->create([
            'path' => '/bd-fraud-checker',
            'event_type' => SiteVisitorEvent::TYPE_HEARTBEAT,
            'visitor_hash' => str_repeat('aa', 16),
            'session_hash' => str_repeat('bb', 16),
            'engaged_ms' => 8000,
            'source_channel' => 'organic',
            'device_type' => 'desktop',
        ]);

        // Seed rollup so by_path prefers daily_stats for pageviews/CTAs.
        SiteVisitorDailyStat::query()->create([
            'date' => now()->toDateString(),
            'path' => '/bd-fraud-checker',
            'pageviews' => 1,
            'unique_visitors' => 1,
            'sessions' => 1,
            'avg_engaged_ms' => 8000,
            'scroll_50_count' => 0,
            'cta_clicks' => 1,
        ]);
        SiteVisitorDailyStat::query()->create([
            'date' => now()->toDateString(),
            'path' => '/return-loss-calculator',
            'pageviews' => 1,
            'unique_visitors' => 1,
            'sessions' => 1,
            'avg_engaged_ms' => 0,
            'scroll_50_count' => 0,
            'cta_clicks' => 0,
        ]);

        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('siteVisitors.index'))
            ->assertOk();

        $byPath = $this->actingAs($admin)
            ->getJson(route('siteVisitors.report', [
                'type' => 'by_path',
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('overview.pageviews', 2)
            ->assertJsonPath('overview.visitors', 2)
            ->assertJsonPath('overview.cta_clicks', 1)
            ->assertJsonPath('meta.by_path_source', 'daily_stats')
            ->assertJsonStructure([
                'overview' => [
                    'visitors',
                    'pageviews',
                    'sessions',
                    'cta_rate',
                    'tool_actions',
                    'pages_tracked',
                ],
                'rows',
                'meta',
            ])
            ->assertJsonMissingPath('insights')
            ->json();

        $this->assertNotEmpty($byPath['rows']);

        $this->actingAs($admin)
            ->getJson(route('siteVisitors.insights', [
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonStructure([
                'insights' => [
                    'sources',
                    'devices',
                    'daily',
                    'referrers',
                    'campaigns',
                    'top_actions',
                    'top_paths',
                    'first_party_keywords',
                ],
            ])
            ->assertJsonMissingPath('insights.seo');

        $this->actingAs($admin)
            ->getJson(route('siteVisitors.seo'))
            ->assertOk()
            ->assertJsonStructure([
                'seo' => [
                    'configured',
                    'table_ready',
                    'top_keywords',
                    'opportunities',
                    'landing_pages',
                    'summary',
                ],
            ]);

        $this->actingAs($admin)
            ->getJson(route('siteVisitors.report', [
                'type' => 'by_source',
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->getJson(route('siteVisitors.report', [
                'type' => 'engagement',
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->getJson(route('siteVisitors.report', [
                'type' => 'actions',
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_search_keyword_is_captured_from_utm_term(): void
    {
        $this->postJson(route('siteVisitors.event'), [
            'path' => '/bd-fraud-checker',
            'event' => 'page_view',
            'visitor_id' => str_repeat('77', 16),
            'utm_term' => 'courier fraud checker bd',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('site_visitor_events', [
            'path' => '/bd-fraud-checker',
            'search_keyword' => 'courier fraud checker bd',
            'utm_term' => 'courier fraud checker bd',
        ]);
    }

    public function test_admin_report_includes_seo_panel_structure(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->getJson(route('siteVisitors.seo'))
            ->assertOk()
            ->assertJsonStructure([
                'seo' => [
                    'configured',
                    'table_ready',
                    'top_keywords',
                    'opportunities',
                    'landing_pages',
                    'summary',
                ],
            ]);

        $this->actingAs($admin)
            ->getJson(route('siteVisitors.insights', [
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonStructure([
                'insights' => [
                    'first_party_keywords',
                ],
            ]);
    }

    public function test_allowlist_includes_seo_canonical_paths(): void
    {
        $prefixes = app(\App\Services\Analytics\SiteVisitorPathAllowlist::class)->build();
        $this->assertContains('/blog', $prefixes);
        $this->assertContains('/en', $prefixes);

        $canonical = collect(config('seo.pages', []))
            ->pluck('canonical_path')
            ->filter()
            ->first();

        if (is_string($canonical) && $canonical !== '') {
            $normalized = rtrim($canonical, '/') ?: '/';
            if ($normalized !== '/') {
                $this->assertContains($normalized, $prefixes);
            }
        }

        $tracker = app(\App\Services\Analytics\SiteVisitorTracker::class);
        $this->assertTrue($tracker->isAllowedPath('/blog/some-post'));
        $this->assertFalse($tracker->isAllowedPath('/invented-admin-path'));
    }

    public function test_rollup_command_rebuilds_daily_stats(): void
    {
        SiteVisitorEvent::query()->create([
            'path' => '/en',
            'event_type' => SiteVisitorEvent::TYPE_PAGE_VIEW,
            'visitor_hash' => str_repeat('ee', 16),
            'session_hash' => str_repeat('ff', 16),
            'source_channel' => 'direct',
            'device_type' => 'desktop',
        ]);
        SiteVisitorEvent::query()->create([
            'path' => '/en',
            'event_type' => SiteVisitorEvent::TYPE_SCROLL,
            'visitor_hash' => str_repeat('ee', 16),
            'session_hash' => str_repeat('ff', 16),
            'scroll_pct' => 50,
            'source_channel' => 'direct',
            'device_type' => 'desktop',
        ]);

        $this->artisan('site-visitors:rollup', [
            '--date' => now()->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseHas('site_visitor_daily_stats', [
            'path' => '/en',
            'pageviews' => 1,
            'unique_visitors' => 1,
            'scroll_50_count' => 1,
        ]);

        $this->assertSame(1, SiteVisitorDailyStat::query()->count());
    }
}
