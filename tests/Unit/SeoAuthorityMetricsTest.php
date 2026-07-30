<?php

namespace Tests\Unit;

use App\Support\SeoAuthorityCalendar;
use App\Support\SeoAuthorityMetrics;
use Carbon\Carbon;
use Tests\TestCase;

class SeoAuthorityMetricsTest extends TestCase
{
    public function test_tracked_paths_include_pillar_and_tool(): void
    {
        $paths = SeoAuthorityMetrics::trackedPaths();

        $this->assertContains('/steadfast-fraud-check', $paths);
        $this->assertContains('/bd-fraud-checker', $paths);
        $this->assertContains('/return-loss-calculator', $paths);
    }

    public function test_sunday_checklist_is_non_empty(): void
    {
        $list = SeoAuthorityMetrics::sundayChecklist();

        $this->assertNotEmpty($list);
        $this->assertTrue(
            collect($list)->contains(fn (string $item) => str_contains($item, 'seo:weekly-report'))
        );
    }

    public function test_report_markdown_includes_step_9_heading(): void
    {
        $md = SeoAuthorityMetrics::reportMarkdown();

        $this->assertStringContainsString('Step 9', $md);
        $this->assertStringContainsString('/steadfast-fraud-check', $md);
        $this->assertStringContainsString('Do not expand to Pathao', $md);
    }

    public function test_sunday_resolve_merges_metrics_checklist(): void
    {
        $r = SeoAuthorityCalendar::resolve(Carbon::parse('2026-08-02', 'Asia/Dhaka')); // Sunday Week 1

        $this->assertSame('sun', $r['weekday']);
        $this->assertNotEmpty($r['metrics_checklist'] ?? []);
        $this->assertTrue(
            collect($r['day']['checklist'] ?? [])->contains(
                fn ($item) => str_contains((string) $item, 'seo:weekly-report')
            )
        );
    }
}
