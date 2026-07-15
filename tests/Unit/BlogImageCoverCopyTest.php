<?php

namespace Tests\Unit;

use App\Services\BlogAi\BlogImageAgent;
use ReflectionMethod;
use Tests\TestCase;

class BlogImageCoverCopyTest extends TestCase
{
    public function test_cover_copy_uses_latin_cluster_hooks(): void
    {
        /** @var BlogImageAgent $agent */
        $agent = $this->app->make(BlogImageAgent::class);
        $method = new ReflectionMethod(BlogImageAgent::class, 'coverCopy');
        $method->setAccessible(true);

        $copy = $method->invoke($agent, 'facebook_ads', 'ফেসবুক পিক্সেল পেমেন্ট ইভেন্ট কাজ করছে না?', 'ফেসবুক পিক্সেল');

        $this->assertSame(['Facebook Pixel', 'payment events', 'not firing?'], $copy['lines']);
        foreach ($copy['lines'] as $line) {
            $this->assertDoesNotMatchRegularExpression('/[\x{0980}-\x{09FF}]/u', $line);
        }
    }

    public function test_build_prompt_forbids_bengali_script(): void
    {
        config(['blog_ai.image.latin_cover_text_only' => true]);

        /** @var BlogImageAgent $agent */
        $agent = $this->app->make(BlogImageAgent::class);
        $method = new ReflectionMethod(BlogImageAgent::class, 'buildPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke(
            $agent,
            'ফেসবুক পিক্সেল',
            'pixel',
            [
                'outfit' => 'navy blazer',
                'posture' => 'smile',
                'setting' => 'dark office',
                'layout' => 'right person left text',
            ],
            ['Pixel Protection', 'Confirm Purchase'],
            [
                'lines' => ['Facebook Pixel', 'payment events', 'not firing?'],
                'sub' => 'Know the fix!',
            ],
            'Muhibbullah Ansary',
            'Developer of WooEasyLife',
            null,
        );

        $this->assertStringContainsString('Do NOT render any Bengali', $prompt);
        $this->assertStringContainsString('IDENTITY LOCK', $prompt);
        $this->assertStringContainsString('Facebook Pixel | payment events | not firing?', $prompt);
    }
}
