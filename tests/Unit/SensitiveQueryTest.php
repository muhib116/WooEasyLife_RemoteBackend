<?php

namespace Tests\Unit;

use App\Http\Middleware\SetReferrerPolicy;
use App\Support\SensitiveQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class SensitiveQueryTest extends TestCase
{
    public function test_strip_removes_phone_keys_and_keeps_other_params_and_the_hash(): void
    {
        $clean = SensitiveQuery::strip(
            'https://app.wpsalehub.com/bd-fraud-checker?phone=01700000000&utm_source=courierfraudbd&mobile=018&Number=019#fraud-check'
        );

        $this->assertSame(
            'https://app.wpsalehub.com/bd-fraud-checker?utm_source=courierfraudbd#fraud-check',
            $clean,
        );
    }

    public function test_strip_leaves_a_url_without_those_keys_unchanged(): void
    {
        $url = '/pricing?plan=starter';

        $this->assertSame($url, SensitiveQuery::strip($url));
    }

    public function test_inertia_json_page_url_drops_the_phone_and_keeps_utm(): void
    {
        $request = Request::create('/bd-fraud-checker?phone=01700000000&utm_source=courierfraudbd', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $response = new JsonResponse([
            'component' => 'Seo/BdFraudChecker',
            'props' => ['phone' => '01700000000'],
            'url' => '/bd-fraud-checker?phone=01700000000&utm_source=courierfraudbd',
            'version' => 'test',
        ]);

        $result = (new SetReferrerPolicy())->handle($request, fn () => $response);
        $data = $result->getData(true);

        $this->assertSame('/bd-fraud-checker?utm_source=courierfraudbd', $data['url']);
        $this->assertSame('01700000000', $data['props']['phone']);
        $this->assertSame('strict-origin-when-cross-origin', $result->headers->get('Referrer-Policy'));
    }

    public function test_non_inertia_json_keeps_its_url_field(): void
    {
        $request = Request::create('/api/example', 'GET');
        $response = new JsonResponse([
            'url' => 'https://courier.example/track?phone=01700000000',
        ]);

        $result = (new SetReferrerPolicy())->handle($request, fn () => $response);

        $this->assertSame(
            'https://courier.example/track?phone=01700000000',
            $result->getData(true)['url'],
        );
    }
}
