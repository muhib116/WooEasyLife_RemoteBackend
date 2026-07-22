<?php

use App\Services\Courier\SteadfastParcelNotesService;
use App\Services\Courier\SteadfastPortalSessionClient;

uses(Tests\TestCase::class);

function welInvokeParcelNotesMethod(string $method, mixed ...$args): mixed
{
    $service = new SteadfastParcelNotesService(
        Mockery::mock(SteadfastPortalSessionClient::class)
    );

    $ref = new ReflectionMethod(SteadfastParcelNotesService::class, $method);
    $ref->setAccessible(true);

    return $ref->invoke($service, ...$args);
}

it('parses steadfast tracking steps with split date and time paragraphs', function () {
    $html = <<<'HTML'
<div class="delivery-status"><div class="streamline"><div class="tracking-steps"><div class="step"><div class="date-time"><p>Jul 20, 2026</p><p>02:17 pm</p></div><div class="step-icon"><img src="/assets/images/progress/note.svg" alt=""></div><div class="tracking_content"><p class="txt-black">Rider Note: "এক পারসেল দুইটা আসছে একটা ডেলিভেরি করা হয়লো আর মাচেন্ট স্যারকে জানানো হয়েছে"</p><div class="txt-in date-time"><p>Jul 20, 2026</p><p>02:17 pm</p></div></div></div><div class="step"><div class="date-time"><p>Jul 20, 2026</p><p>02:52 am</p></div><div class="step-icon"><img src="/assets/images/progress/received.svg" alt=""></div><div class="tracking_content short-info-rider"><p class="txt-black"> Assigned to rider.</p><div class="rider-img"><img src="/assets/images/user.png" alt="" class="rider_img"><div class="rider-name d-flex flex-column"><p class="txt-black"> Rakib Hasan Rifat </p><p class="cell d-flex align-items-center gap-2"><img src="/assets/images/cell.png" alt="" class="w-auto h-auto"><span>01704000312</span></p></div></div><div class="txt-in date-time"><p>Jul 20, 2026</p><p>02:52 am</p></div></div></div><div class="step"><div class="date-time"><p>Jul 20, 2026</p><p>12:11 am</p></div><div class="step-icon"><img src="/assets/images/progress/note.svg" alt=""></div><div class="tracking_content"><p class="txt-black">Consignment has been received at DINAJPUR.</p><div class="txt-in date-time"><p>Jul 20, 2026</p><p>12:11 am</p></div></div></div><div class="step"><div class="date-time"><p>Jul 19, 2026</p><p>11:39 am</p></div><div class="step-icon"><img src="/assets/images/progress/note.svg" alt=""></div><div class="tracking_content"><p class="txt-black">Consignment sent to DINAJPUR. Dispatch ID: 15711318</p><div class="txt-in date-time"><p>Jul 19, 2026</p><p>11:39 am</p></div></div></div><div class="step"><div class="date-time"><p>Jul 19, 2026</p><p>08:24 am</p></div><div class="step-icon"><img src="/assets/images/progress/note.svg" alt=""></div><div class="tracking_content"><p class="txt-black">Consignment has been received at FULFILLMENT WAREHOUSE.</p><div class="txt-in date-time"><p>Jul 19, 2026</p><p>08:24 am</p></div></div></div><div class="step"><div class="date-time"><p>Jul 19, 2026</p><p>12:35 am</p></div><div class="step-icon"><img src="/assets/images/progress/note.svg" alt=""></div><div class="tracking_content"><p class="txt-black">Consignment sent to FULFILLMENT WAREHOUSE. Dispatch ID: 15701561</p><div class="txt-in date-time"><p>Jul 19, 2026</p><p>12:35 am</p></div></div></div><div class="step"><div class="date-time"><p>Jul 19, 2026</p><p>12:33 am</p></div><div class="step-icon"><img src="/assets/images/progress/note.svg" alt=""></div><div class="tracking_content"><p class="txt-black">Consignment status has been updated as Pending</p><div class="txt-in date-time"><p>Jul 19, 2026</p><p>12:33 am</p></div></div></div><div class="step"><div class="date-time"><p>Jul 18, 2026</p><p>06:25 pm</p></div><div class="step-icon"><img src="/assets/images/progress/note.svg" alt=""></div><div class="tracking_content"><p class="txt-black">Consignment created by Sender(API).</p><div class="txt-in date-time"><p>Jul 18, 2026</p><p>06:25 pm</p></div></div></div></div></div></div>
HTML;

    $notes = welInvokeParcelNotesMethod('parseTrackingHtml', $html);

    expect($notes)->toHaveCount(8)
        ->and($notes[0]['source'])->toBe('rider')
        ->and($notes[0]['at'])->toBe('2026-07-20 14:17:00')
        ->and($notes[0]['message'])->toContain('Rider Note:')
        ->and($notes[1]['source'])->toBe('assigned_rider')
        ->and($notes[1]['message'])->toContain('Assigned to rider')
        ->and($notes[1]['message'])->toContain('Rakib Hasan Rifat')
        ->and($notes[1]['message'])->toContain('01704000312')
        ->and($notes[1]['rider_name'])->toBe('Rakib Hasan Rifat')
        ->and($notes[1]['rider_phone'])->toBe('01704000312')
        ->and($notes[1]['at'])->toBe('2026-07-20 02:52:00')
        ->and($notes[2]['message'])->toContain('DINAJPUR')
        ->and($notes[6]['message'])->toContain('Pending')
        ->and($notes[7]['message'])->toContain('Sender(API)')
        ->and($notes[7]['at'])->toBe('2026-07-18 18:25:00');
});
it('maps trackings json from the user track endpoint shape', function () {
    $trackings = [
        [
            'id' => 1,
            'text' => "Rider Note: 'partial delivery note'",
            'created_at' => '2026-07-20T14:17:00+06:00',
        ],
        [
            'id' => 2,
            'text' => 'Consignment has been received at DINAJPUR.',
            'created_at' => '2026-07-20T00:11:00+06:00',
        ],
        [
            'id' => 3,
            'text' => 'Consignment sent to DINAJPUR. Dispatch ID: 15711318.',
            'created_at' => '2026-07-19T11:39:00+06:00',
        ],
    ];

    $notes = welInvokeParcelNotesMethod('mapTrackingsArray', $trackings);

    expect($notes)->toHaveCount(3)
        ->and($notes[0]['source'])->toBe('rider')
        ->and($notes[1]['source'])->toBe('status')
        ->and($notes[1]['at'])->toBe('2026-07-20 00:11:00');
});

it('builds single/update payload from edit-parcel vue consignment prop', function () {
    $html = <<<'HTML'
    <div id="app">
      <edit-parcel
        :consignment='{"id":272797984,"phone":"01700000000","name":"Test Customer","address":"Dinajpur","cod":950,"invoice":"290","area_id":219,"note":"old note","additional_data":{"alternative_phone":"01800000000","email":"a@b.c","item_description":"Health product"},"weight":0.5}'
        :address_id="42"
        :ser_type="1"
      ></edit-parcel>
    </div>
    HTML;

    $payload = welInvokeParcelNotesMethod('buildSingleUpdatePayload', $html, '272797984', 'ok');

    expect($payload)->toMatchArray([
        'consignment_id' => '272797984',
        'cus_phone' => '01700000000',
        'cus_name' => 'Test Customer',
        'cus_address' => 'Dinajpur',
        'note' => 'ok',
        'invoice' => '290',
        'cod_amount' => 950,
        'alt_phone' => '01800000000',
        'email' => 'a@b.c',
        'item_description' => 'Health product',
        'policestation_id' => 219,
        'pickup_address_id' => '42',
    ]);
});

it('overrides cod amount and address when provided', function () {
    $html = <<<'HTML'
    <edit-parcel :consignment='{"id":272797984,"phone":"01700000000","name":"Test Customer","address":"Dinajpur","cod":950,"invoice":"290","area_id":219,"note":"old note"}' :address_id="42"></edit-parcel>
    HTML;

    $payload = welInvokeParcelNotesMethod(
        'buildSingleUpdatePayload',
        $html,
        '272797984',
        'keep note',
        'New Address, Dhaka',
        1200
    );

    expect($payload)->toMatchArray([
        'note' => 'keep note',
        'cus_address' => 'New Address, Dhaka',
        'cod_amount' => 1200,
        'cus_phone' => '01700000000',
    ]);
});

it('keeps page note when note override is null', function () {
    $html = <<<'HTML'
    <edit-parcel :consignment='{"id":272797984,"phone":"01700000000","name":"Test Customer","address":"Dinajpur","cod":950,"note":"portal note"}' :address_id="42"></edit-parcel>
    HTML;

    $payload = welInvokeParcelNotesMethod(
        'buildSingleUpdatePayload',
        $html,
        '272797984',
        null,
        'Updated Address',
        800
    );

    expect($payload['note'])->toBe('portal note')
        ->and($payload['cus_address'])->toBe('Updated Address')
        ->and($payload['cod_amount'])->toBe(800);
});

it('rejects update payload when recipient phone is missing', function () {
    $html = <<<'HTML'
    <edit-parcel :consignment='{"id":1,"phone":"","name":"X","address":"Y","cod":1}'></edit-parcel>
    HTML;

    $payload = welInvokeParcelNotesMethod('buildSingleUpdatePayload', $html, '1', 'ok');

    expect($payload)->toBeNull();
});

it('extracts tracking code candidates from consignment html', function () {
    $html = '<p>Tracking Code : <span>SFABC12345</span></p><a href="/user/tracking/SFABC12345">view</a>';

    $codes = welInvokeParcelNotesMethod('candidateTrackCodes', $html, '272797984', 'EXPLICITCODE');

    expect($codes[0])->toBe('EXPLICITCODE')
        ->and($codes)->toContain('SFABC12345')
        ->and($codes)->toContain('272797984');
});
