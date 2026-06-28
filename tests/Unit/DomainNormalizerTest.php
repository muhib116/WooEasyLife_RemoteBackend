<?php

namespace Tests\Unit;

use App\Services\DomainNormalizer;
use Tests\TestCase;

class DomainNormalizerTest extends TestCase
{
    public function test_normalize_extracts_lowercase_hostname(): void
    {
        $normalizer = new DomainNormalizer();

        $this->assertSame('shop.example.com', $normalizer->normalize('https://Shop.Example.com/path'));
        $this->assertSame('shop.example.com', $normalizer->normalize('shop.example.com'));
        $this->assertSame('shop.example.com', $normalizer->normalize('HTTP://shop.example.com'));
        $this->assertNull($normalizer->normalize(''));
        $this->assertNull($normalizer->normalize(null));
    }

    public function test_matches_compares_normalized_hostnames(): void
    {
        $normalizer = new DomainNormalizer();

        $this->assertTrue($normalizer->matches('https://Shop.Example.com', 'shop.example.com'));
        $this->assertFalse($normalizer->matches('https://other.example.com', 'shop.example.com'));
        $this->assertFalse($normalizer->matches('', 'shop.example.com'));
    }
}
