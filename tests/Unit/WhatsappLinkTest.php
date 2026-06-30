<?php

namespace Tests\Unit;

use App\Support\WhatsappLink;
use Tests\TestCase;

class WhatsappLinkTest extends TestCase
{
    public function test_builds_url_with_prefilled_message(): void
    {
        $url = WhatsappLink::url('8801790989591', 'সালাম, আমি WooEasyLife সম্পর্কে জানতে চাই।');

        $this->assertSame(
            'https://wa.me/8801790989591?text='.rawurlencode('সালাম, আমি WooEasyLife সম্পর্কে জানতে চাই।'),
            $url,
        );
    }

    public function test_returns_null_when_phone_missing(): void
    {
        $this->assertNull(WhatsappLink::url(null));
        $this->assertNull(WhatsappLink::url(''));
    }
}
