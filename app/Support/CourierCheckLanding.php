<?php

namespace App\Support;

use App\Services\LandingSettingsService;
use App\Services\SeoMetaService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class CourierCheckLanding
{
    public static function render(
        SeoMetaService $seo,
        LandingSettingsService $settings,
        bool $english,
    ): Response {
        $live = filter_var(config('courier_check.wporg_live'), FILTER_VALIDATE_BOOLEAN);
        $shots = self::screenshots($english);
        $overrides = [];

        if ($live) {
            $downloadUrl = 'https://wordpress.org/plugins/easylife-courier-check/';
            $overrides['plugin_download_url'] = $downloadUrl;
            $overrides['plugin_download_label'] = $english
                ? 'Free download — WordPress.org'
                : 'ফ্রি ডাউনলোড — WordPress.org';
            $overrides['software_application'] = [
                'name' => 'EasyLife Courier Check',
                'operatingSystem' => 'WordPress',
                'applicationCategory' => 'BusinessApplication',
                'downloadUrl' => $downloadUrl,
            ];
        }

        if ($shots !== []) {
            $overrides['schema_images'] = array_map(static fn (array $shot): array => [
                'url' => $shot['src'],
                'width' => $shot['width'],
                'height' => $shot['height'],
                'caption' => $shot['alt'],
            ], $shots);
        }

        $seoMeta = self::withFigures(
            $seo->forPage($english ? 'en_courier_check_plugin' : 'courier_check_plugin', $overrides),
            $shots,
        );
        $whatsapp = $settings->adminWhatsapp();
        $props = [
            'canLogin' => Route::has('merchant.login'),
            'seo' => $seoMeta,
            'whatsappUrl' => WhatsappLink::url($whatsapp),
            'whatsappContactUrl' => WhatsappLink::url(
                $whatsapp,
                $english
                    ? 'Hi, I want a WooEasyLife subscription.'
                    : config('landing.whatsapp_default_message'),
            ),
            'faqs' => $seoMeta['faqs'] ?? [],
            'wporgLive' => $live,
            'screenshots' => $shots,
        ];

        if ($live) {
            $props['downloadUrl'] = $overrides['plugin_download_url'];
        }

        return Inertia::render(
            $english ? 'Seo/EnCourierCheckPlugin' : 'Seo/CourierCheckPlugin',
            $props,
        )->withViewData(['seo' => $seoMeta]);
    }

    /**
     * @return list<array{src: string, alt: string, width: int, height: int}>
     */
    public static function screenshots(bool $english): array
    {
        $slots = [
            [
                'file' => 'orders.webp',
                'alt' => $english
                    ? 'Courier delivery history in the WooCommerce order list'
                    : 'WooCommerce order list-এ courier delivery history',
            ],
            [
                'file' => 'details.webp',
                'alt' => 'Courier-wise fraud history popup',
            ],
        ];
        $shots = [];

        foreach ($slots as $slot) {
            $absolute = public_path('images/courier-check-plugin/'.$slot['file']);
            if (! is_file($absolute)) {
                continue;
            }
            $size = @getimagesize($absolute);
            if ($size === false || empty($size[0]) || empty($size[1])) {
                continue;
            }
            $shots[] = [
                'src' => '/images/courier-check-plugin/'.$slot['file'],
                'alt' => $slot['alt'],
                'width' => (int) $size[0],
                'height' => (int) $size[1],
            ];
        }

        return $shots;
    }

    /**
     * @param  array<string, mixed>  $seoMeta
     * @param  list<array{src: string, alt: string, width: int, height: int}>  $shots
     * @return array<string, mixed>
     */
    private static function withFigures(array $seoMeta, array $shots): array
    {
        if ($shots === [] || ! is_array($seoMeta['content_sections'] ?? null)) {
            return $seoMeta;
        }

        foreach ($seoMeta['content_sections'] as $index => $section) {
            $heading = (string) ($section['heading'] ?? '');
            if ($heading !== 'কীভাবে কাজ করে (৩ ধাপ)' && $heading !== 'How it works (3 steps)') {
                continue;
            }
            $seoMeta['content_sections'][$index]['figures'] = array_values(array_merge(
                is_array($section['figures'] ?? null) ? $section['figures'] : [],
                $shots,
            ));
            break;
        }

        return $seoMeta;
    }
}
