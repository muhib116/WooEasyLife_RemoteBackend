<?php

use App\Services\Courier\SteadfastConsignmentService;
use App\Services\Courier\SteadfastPortalSessionClient;

/**
 * Portal HTML detection for In-Review consignments (delete scrape).
 *
 * The live SteadFast page embeds "404" in assets/scripts; a bare 404 match
 * previously false-flagged valid parcels as missing.
 */
it('recognizes a live In Review parcel details page and does not treat it as missing', function () {
    $html = <<<'HTML'
<div class="common-form parcel-details"><div class="update-section d-flex justify-content-end"><div class="d-flex gap-3"><button class="btn btn-extra-sm btn-d-w open-ticket">Open Support Ticket</button></div></div><div class="parcel-information mt-4"><div class="d-flex flex-column gap-2"><div class="parcel-short-info d-flex justify-content-between align-items-start w-100"><div class="d-flex flex-column gap-1"><p>July 25, 2026 04:08 AM</p><p>Id : <span> 275283097 </span></p><p>Invoice : <span>LOC9-340</span></p></div><div class="d-flex flex-column gap-2 justify-content-end align-items-end"><p>Approved at: No Yet </p><h6 class="mt-1">COD: ৳ 950</h6><label class="alert alert-sm alert-default-500">In Review</label><div class="pt-2"><div class="d-inline"><div class="py-2"><div class="d-flex justify-content-between"><button class="btn btn-default btn-sm"> X</button><button class="btn btn-danger btn-sm"> Confirm</button></div></div></div></div><p><span class="txt-black">In-Review </span></p></div></div></div></div>
<script>window.__chunk="/assets/js/app.404abc.js"; /* status 404 handler */</script>
HTML;

    $service = new SteadfastConsignmentService(app(SteadfastPortalSessionClient::class));
    $ref = new ReflectionClass($service);

    $hasDetails = $ref->getMethod('pageHasConsignmentDetails');
    $hasDetails->setAccessible(true);
    expect($hasDetails->invoke($service, $html, '275283097'))->toBeTrue();

    $allowsDelete = $ref->getMethod('pageAllowsDelete');
    $allowsDelete->setAccessible(true);
    expect($allowsDelete->invoke($service, $html, '275283097'))->toBeTrue();

    $missing = $ref->getMethod('looksLikeMissingConsignment');
    $missing->setAccessible(true);
    expect($missing->invoke($service, $html, '275283097'))->toBeFalse();
});

it('treats a real missing-page message as missing without requiring a 404 digit match', function () {
    $html = '<html><body><h1>Sorry, this page could not be found</h1><p>Consignment not found</p></body></html>';

    $service = new SteadfastConsignmentService(app(SteadfastPortalSessionClient::class));
    $ref = new ReflectionClass($service);

    $missing = $ref->getMethod('looksLikeMissingConsignment');
    $missing->setAccessible(true);
    expect($missing->invoke($service, $html, '275283097'))->toBeTrue();
});

it('uses the current Steadfast Confirm endpoint and payload first', function () {
    $service = new SteadfastConsignmentService(app(SteadfastPortalSessionClient::class));
    $ref = new ReflectionClass($service);

    $fallbacks = $ref->getMethod('fallbackDeleteCandidates');
    $fallbacks->setAccessible(true);
    $candidates = $fallbacks->invoke($service, '275283097', 'csrf-token');

    expect($candidates[0])->toBe([
        'path' => '/user/consignment/remove-parcel',
        'payload' => ['consignment_id' => '275283097'],
        'method' => 'POST',
    ]);
});

it('treats Confirm redirect to in-review as remove-parcel success', function () {
    $service = new SteadfastConsignmentService(app(SteadfastPortalSessionClient::class));
    $ref = new ReflectionClass($service);
    $method = $ref->getMethod('responseLooksLikeRemoveParcelSuccess');
    $method->setAccessible(true);

    $response = new Illuminate\Http\Client\Response(
        new GuzzleHttp\Psr7\Response(302, ['Location' => 'https://www.steadfast.com.bd/user/consignment/status/in-review'])
    );

    expect($method->invoke($service, $response))->toBeTrue();
});

it('treats Confirm redirect to login as auth loss, not success', function () {
    $service = new SteadfastConsignmentService(app(SteadfastPortalSessionClient::class));
    $client = app(SteadfastPortalSessionClient::class);
    $ref = new ReflectionClass($service);
    $method = $ref->getMethod('responseIndicatesAuthLoss');
    $method->setAccessible(true);

    $response = new Illuminate\Http\Client\Response(
        new GuzzleHttp\Psr7\Response(302, ['Location' => 'https://www.steadfast.com.bd/login'])
    );

    expect($method->invoke($service, $client, $response))->toBeTrue();
});