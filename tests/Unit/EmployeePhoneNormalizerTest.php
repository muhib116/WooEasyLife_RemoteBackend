<?php

namespace Tests\Unit;

use App\Services\Employee\EmployeePhoneNormalizer;
use PHPUnit\Framework\TestCase;

class EmployeePhoneNormalizerTest extends TestCase
{
    public function test_normalizes_local_bangladesh_numbers(): void
    {
        $this->assertSame('01711223344', EmployeePhoneNormalizer::normalize('01711223344'));
        $this->assertSame('01711223344', EmployeePhoneNormalizer::normalize('01 711-223 344'));
    }

    public function test_normalizes_international_bangladesh_numbers(): void
    {
        $this->assertSame('01711223344', EmployeePhoneNormalizer::normalize('+880 1711 223344'));
        $this->assertSame('01711223344', EmployeePhoneNormalizer::normalize('8801711223344'));
    }

    public function test_validates_password_ready_numbers(): void
    {
        $this->assertTrue(EmployeePhoneNormalizer::isValidForWpPassword('01711223344'));
        $this->assertFalse(EmployeePhoneNormalizer::isValidForWpPassword('12345'));
    }
}
