<?php

namespace Tests\Unit;

use App\Support\SeoAuthorityCalendar;
use Carbon\Carbon;
use Tests\TestCase;

class SeoAuthorityCalendarTest extends TestCase
{
    public function test_week1_thursday_is_free_tool_demo(): void
    {
        $r = SeoAuthorityCalendar::resolve(Carbon::parse('2026-07-30', 'Asia/Dhaka'));

        $this->assertTrue($r['active']);
        $this->assertSame(1, $r['week']);
        $this->assertSame('thu', $r['weekday']);
        $this->assertSame('/bd-fraud-checker', $r['day']['cta'] ?? null);
        $this->assertStringContainsString('ফ্রি টুল', (string) ($r['day']['theme'] ?? ''));
    }

    public function test_week2_monday_is_phone_confirm_faq(): void
    {
        $r = SeoAuthorityCalendar::resolve(Carbon::parse('2026-08-03', 'Asia/Dhaka'));

        $this->assertSame(2, $r['week']);
        $this->assertSame('mon', $r['weekday']);
        $this->assertSame('/faq/phone-confirm-delivery-guarantee-ki', $r['day']['cta'] ?? null);
    }

    public function test_after_lock_stays_steadfast_maintenance(): void
    {
        $r = SeoAuthorityCalendar::resolve(Carbon::parse('2026-08-24', 'Asia/Dhaka'));

        $this->assertTrue($r['active']);
        $this->assertTrue($r['past_lock']);
        $this->assertSame('/steadfast-fraud-check', $r['day']['cta'] ?? null);
    }
}
