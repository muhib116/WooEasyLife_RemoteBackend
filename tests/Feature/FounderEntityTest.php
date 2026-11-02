<?php

namespace Tests\Feature;

use App\Services\SeoMetaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FounderEntityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function founderProfiles(): array
    {
        return [
            'https://www.linkedin.com/in/dev-muhib',
            'https://www.facebook.com/muhib116',
            'https://www.instagram.com/muhibbullah611/',
            'https://www.youtube.com/channel/UCiEVvaV8O6VBKzU3d7ezKGg',
            'https://www.tiktok.com/@muhibbullah116',
            'https://medium.com/@dev.muhibbullah',
            'https://www.pinterest.com/devmuhibbullah/',
            'https://github.com/muhib116',
            'https://dev.to/muhib116',
            'https://profiles.wordpress.org/muhibbullah/',
            'https://webguidebd.site/muhibbullah-ansary/',
            'https://courierfraudbd.com/about/',
        ];
    }

    public function test_about_pages_are_the_founder_entity_home(): void
    {
        $profiles = $this->founderProfiles();
        $alternateNames = ['মুহিব্বুল্লাহ আনসারী', 'Muhib', 'মুহিব', 'Muhibbullah', 'Muhib Ansary'];

        foreach ([
            '/about' => 'মুহিব্বুল্লাহ আনসারী (Muhibbullah Ansary)',
            '/en/about' => 'Muhibbullah Ansary (মুহিব্বুল্লাহ আনসারী)',
        ] as $path => $h1) {
            $response = $this->get($path);
            $response->assertOk();
            $html = $response->getContent();

            $this->assertStringContainsString('<h1>'.$h1.'</h1>', $html);
            $this->assertStringContainsString('/images/seo/about/muhibbullah-ansary.jpg', $html);
            $this->assertStringContainsString('alt="Muhibbullah Ansary (মুহিব্বুল্লাহ আনসারী), Founder of WPSaleHub"', $html);
            $this->assertStringContainsString('width="1200"', $html);
            $this->assertStringContainsString('height="1200"', $html);
            $this->assertStringContainsString('loading="eager"', $html);
            $this->assertSame(1, substr_count($html, 'application/ld+json'));

            foreach ($profiles as $profile) {
                $this->assertStringContainsString($profile, $html);
                $this->assertStringContainsString('href="'.$profile.'" rel="me"', $html);
            }

            $this->assertStringContainsString('href="/"', $html);
            $this->assertStringContainsString('href="/courier-check-plugin"', $html);
            $this->assertStringContainsString('https://play.google.com/store/apps/details?id=com.wooeasylife.woo_easy_life', $html);
            $this->assertStringContainsString('https://courierfraudbd.com/', $html);
            $this->assertStringContainsString('https://webguidebd.site/', $html);

            preg_match('/<script id="seo-json-ld" type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
            $this->assertNotEmpty($match[1] ?? null);
            $json = json_decode($match[1], true);
            $this->assertIsArray($json);
            $this->assertArrayHasKey('@graph', $json);
            $this->assertArrayNotHasKey('@graph', $json['@graph']);

            $person = collect($json['@graph'])->first(fn ($node) => ($node['@type'] ?? null) === 'Person');
            $this->assertIsArray($person);
            $this->assertSame($alternateNames, $person['alternateName'] ?? null);
            $this->assertStringEndsWith('#person-muhibbullah-ansary', (string) ($person['@id'] ?? ''));
            $this->assertStringContainsString('/images/seo/about/muhibbullah-ansary.jpg', (string) ($person['image']['url'] ?? ''));
            $this->assertStringContainsString('/images/seo/about/muhibbullah-ansary.jpg', (string) ($person['image']['contentUrl'] ?? ''));
            $this->assertSame($profiles, $person['sameAs'] ?? null);
            $this->assertSame(
                ['@id' => rtrim((string) config('app.url'), '/').'/#organization'],
                $person['worksFor'] ?? null,
            );

            $page = collect($json['@graph'])->first(function ($node): bool {
                $types = $node['@type'] ?? null;
                $types = is_array($types) ? $types : [$types];

                return in_array('ProfilePage', $types, true);
            });
            $this->assertIsArray($page);
            $types = is_array($page['@type']) ? $page['@type'] : [$page['@type']];
            $this->assertContains('AboutPage', $types);
            $this->assertSame($person['@id'], $page['mainEntity']['@id'] ?? null);
            $this->assertArrayHasKey('email', $person);
            $this->assertArrayHasKey('telephone', $person);
        }
    }

    public function test_founder_portrait_files_are_jpeg(): void
    {
        foreach ([
            'images/seo/about/muhibbullah-ansary.jpg',
            'images/seo/about/founder-headshot.jpg',
        ] as $relative) {
            $path = public_path($relative);
            $this->assertFileExists($path);
            $this->assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->file($path));
            $this->assertSame("\xFF\xD8\xFF", file_get_contents($path, false, null, 0, 3));

            $binary = response()->file($path);
            $binary->prepare(request());
            $response = TestResponse::fromBaseResponse($binary);
            $response->assertOk();
            $this->assertStringStartsWith('image/jpeg', (string) $response->headers->get('content-type'));
        }
    }

    public function test_blog_and_faq_name_the_founder(): void
    {
        foreach ([
            '/blog/ki-vabe-fake-order-atkabo' => 'লিখেছেন: মুহিব্বুল্লাহ আনসারী',
            '/faq/courier-success-rate-kivabe-bujhbo' => 'লিখেছেন: মুহিব্বুল্লাহ আনসারী',
        ] as $path => $byline) {
            $response = $this->get($path);
            $response->assertOk();
            $html = $response->getContent();
            $this->assertStringContainsString('<a href="/about">'.$byline.'</a>', $html);
            $this->assertSame(1, substr_count($html, 'application/ld+json'));

            preg_match('/<script id="seo-json-ld" type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
            $json = json_decode($match[1] ?? '', true);
            $this->assertIsArray($json);
            $authored = collect($json['@graph'] ?? [])->first(function ($node): bool {
                $id = (string) ($node['@id'] ?? '');
                $isPage = str_ends_with($id, '#webpage') || str_ends_with($id, '#article');

                return $isPage && isset($node['author']['@id']);
            });
            $this->assertIsArray($authored);
            $this->assertStringEndsWith('#person-muhibbullah-ansary', (string) $authored['author']['@id']);
        }
    }

    public function test_person_contact_stays_on_the_about_page(): void
    {
        $home = app(SeoMetaService::class)->forPage('home');
        $person = collect($home['json_ld']['@graph'] ?? [])->first(fn ($node) => ($node['@type'] ?? null) === 'Person');
        $this->assertIsArray($person);
        $this->assertArrayNotHasKey('email', $person);
        $this->assertArrayNotHasKey('telephone', $person);
        $this->assertStringContainsString('/images/seo/about/muhibbullah-ansary.jpg', (string) ($person['image']['url'] ?? ''));
        $this->assertSame('ImageObject', $person['image']['@type'] ?? null);
    }
}
