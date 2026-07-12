<?php

namespace Tests\Unit;

use App\Services\BulkSmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BulkSmsServiceTest extends TestCase
{
    public function test_send_treats_response_code_202_as_success(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => '8809617619992',
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::response([
                'response_code' => 202,
                'message_id' => 123,
                'success_message' => 'SMS Submitted Successfully 1',
                'error_message' => '',
            ], 200),
        ]);

        $result = app(BulkSmsService::class)->send('01711111111', 'Test OTP 123456');

        $this->assertTrue($result['ok']);
        $this->assertSame(202, $result['response_code']);
    }

    public function test_send_treats_ip_whitelist_error_as_failure_even_on_http_200(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => '8809617619992',
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::response([
                'response_code' => 1032,
                'success_message' => '',
                'error_message' => 'Your ip not Whitelisted',
            ], 200),
        ]);

        $result = app(BulkSmsService::class)->send('01711111111', 'Test OTP 123456');

        $this->assertFalse($result['ok']);
        $this->assertSame(1032, $result['response_code']);
        $this->assertStringContainsString('Whitelisted', (string) $result['message']);
    }
}
