<?php

use App\Services\Ads\OpenAiAdsConversions;

it('turns a bangladesh mobile and a taka price into openai ads units', function () {
    $ads = app(OpenAiAdsConversions::class);

    expect($ads->normalizePhone('01711111111'))->toBe('8801711111111')
        ->and($ads->normalizePhone('+880 1711-111111'))->toBe('8801711111111')
        ->and($ads->normalizeEmail('  Karim@Example.com '))->toBe('karim@example.com')
        ->and($ads->minorAmount(999))->toBe(99900)
        ->and($ads->splitName("O'Connor Ahmed"))->toBe(['oconnor', 'ahmed']);
});
