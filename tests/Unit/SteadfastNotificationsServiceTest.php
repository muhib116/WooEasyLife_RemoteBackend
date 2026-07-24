<?php

use App\Services\Courier\SteadfastNotificationsService;
use App\Services\Courier\SteadfastPortalSessionClient;

uses(Tests\TestCase::class);

function welInvokeNotificationsMethod(string $method, mixed ...$args): mixed
{
    $service = new SteadfastNotificationsService(
        Mockery::mock(SteadfastPortalSessionClient::class)
    );

    $ref = new ReflectionMethod(SteadfastNotificationsService::class, $method);
    $ref->setAccessible(true);

    return $ref->invoke($service, ...$args);
}

it('parses steadfast notification list html with load-more cursor', function () {
    $html = <<<'HTML'
<div class="common-form notification"><div class="notification_body d-flex flex-column gap-3" id="noti-list"><a href="https://www.steadfast.com.bd/user/consignment/273894578" class="single_notification d-flex gap-3 read_notification text-decoration-none"><div class="notify_icon d-flex justify-content-center align-items-center"><img src="/assets/images/icon/notification/read_notification.svg" alt="" class="w-auto h-auto"></div><div class="notify_text d-flex flex-column align-items-start gap-1"><p class="mb-0">Parcel #273894578 has been delivered.</p><span class="txt-primary" style="font-size: 12px;">42 minutes ago</span></div></a><a href="https://www.steadfast.com.bd/user/consignment/273440677" class="single_notification d-flex gap-3 unread_notification text-decoration-none"><div class="notify_icon d-flex justify-content-center align-items-center"><img src="/assets/images/icon/notification/unread_notification.svg" alt="" class="w-auto h-auto"></div><div class="notify_text d-flex flex-column align-items-start gap-1"><p class="mb-0">You received cancellation request of #273440677.</p><span class="txt-primary" style="font-size: 12px;">2 hours ago</span></div></a></div><div class="d-flex justify-content-center mt-4" id="load-more-wrap"><button class="btn btn-default btn-d-w" id="load-more-btn" data-next="https://www.steadfast.com.bd/user/notifications?cursor=eyJjcmVhdGVkX2F0IjoiMjAyNi0wNy0yNCAxMDo1MzoxMSIsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0"> Load More </button></div></div>
HTML;

    $parsed = welInvokeNotificationsMethod('parseNotificationsHtml', $html);

    expect($parsed['items'])->toHaveCount(2)
        ->and($parsed['items'][0]['consignment_id'])->toBe('273894578')
        ->and($parsed['items'][0]['message'])->toContain('delivered')
        ->and($parsed['items'][0]['relative_time'])->toBe('42 minutes ago')
        ->and($parsed['items'][0]['is_read'])->toBeTrue()
        ->and($parsed['items'][1]['consignment_id'])->toBe('273440677')
        ->and($parsed['items'][1]['is_read'])->toBeFalse()
        ->and($parsed['has_more'])->toBeTrue()
        ->and($parsed['next_cursor'])->toBe('eyJjcmVhdGVkX2F0IjoiMjAyNi0wNy0yNCAxMDo1MzoxMSIsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0')
        ->and($parsed['unread_count'])->toBe(1);
});
