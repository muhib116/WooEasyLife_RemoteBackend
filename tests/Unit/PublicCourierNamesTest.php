<?php

namespace Tests\Unit;

use App\Support\PublicCourierNames;
use Tests\TestCase;

class PublicCourierNamesTest extends TestCase
{
    public function test_history_list_follows_the_same_flags_as_the_checker(): void
    {
        config([
            'fraud_check.include_redx' => true,
            'fraud_check.include_carrybee' => true,
        ]);

        $this->assertSame(
            ['Pathao', 'Steadfast', 'RedX', 'Paperfly', 'Carrybee'],
            PublicCourierNames::historyNames()
        );
        $this->assertSame(
            'Pathao, Steadfast, RedX সহ ৫টি কুরিয়ার',
            PublicCourierNames::historyShort('bn')
        );
        $bdFraudTitle = PublicCourierNames::expand((string) config('seo.pages.bd_fraud_checker.title'));
        $this->assertSame(
            'ফ্রি Fraud Checker BD — Pathao, Steadfast সহ ৫টি কুরিয়ার',
            $bdFraudTitle
        );
        $this->assertLessThanOrEqual(60, mb_strlen($bdFraudTitle));
        $this->assertSame(
            'Pathao, Steadfast, RedX + 2 more',
            PublicCourierNames::historyShort('en')
        );
        $this->assertSame(
            ['Pathao', 'Steadfast', 'RedX', 'Paperfly'],
            PublicCourierNames::bookingNames()
        );
    }

    public function test_turning_a_history_flag_off_drops_that_name_from_marketing_copy(): void
    {
        config([
            'fraud_check.include_redx' => false,
            'fraud_check.include_carrybee' => true,
        ]);

        $names = PublicCourierNames::historyNames();

        $this->assertNotContains('RedX', $names);
        $this->assertContains('Paperfly', $names);
        $this->assertContains('Carrybee', $names);
        $this->assertStringNotContainsString('RedX', PublicCourierNames::historyShort('bn'));
        $this->assertStringNotContainsString('RedX', PublicCourierNames::historyShort('en'));
        $this->assertStringNotContainsString('RedX', PublicCourierNames::historyPhrase('en'));
        $this->assertSame(
            ['Pathao', 'Steadfast', 'RedX', 'Paperfly'],
            PublicCourierNames::bookingNames()
        );
    }

    public function test_carrybee_off_changes_the_short_count(): void
    {
        config([
            'fraud_check.include_redx' => true,
            'fraud_check.include_carrybee' => false,
        ]);

        $this->assertSame(
            'Pathao, Steadfast, RedX সহ ৪টি কুরিয়ার',
            PublicCourierNames::historyShort('bn')
        );
        $this->assertSame(
            'ফ্রি Fraud Checker BD — Pathao, Steadfast সহ ৪টি কুরিয়ার',
            PublicCourierNames::expand((string) config('seo.pages.bd_fraud_checker.title'))
        );
        $this->assertSame(
            'Pathao, Steadfast, RedX + 1 more',
            PublicCourierNames::historyShort('en')
        );
        $this->assertStringNotContainsString('Carrybee', PublicCourierNames::expand('{couriers.history.en}'));
    }
}
