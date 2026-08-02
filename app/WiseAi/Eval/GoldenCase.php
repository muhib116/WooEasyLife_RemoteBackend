<?php

namespace App\WiseAi\Eval;

/**
 * One situation golden (S0–S9). Expectations are decision fields after TurnRunner.
 *
 * @phpstan-type Expectation array{
 *     action?: string,
 *     intent?: string,
 *     gap?: bool,
 *     missing_context?: string|null,
 *     source?: string,
 *     pricing_menu?: bool,
 *     reply_contains?: list<string>,
 *     actions_any?: list<string>
 * }
 * @phpstan-type SeedRow array{
 *     type: string,
 *     title: string,
 *     answer: string,
 *     question?: string|null,
 *     keywords?: list<string>,
 *     external_id?: string|null,
 *     scope?: string,
 *     meta?: array<string, mixed>|null
 * }
 */
final class GoldenCase
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<SeedRow>  $seeds
     * @param  list<array{text: string, context?: array<string, mixed>}>  $prior
     * @param  Expectation  $expect
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $text,
        public readonly array $context = [],
        public readonly array $seeds = [],
        public readonly array $prior = [],
        public readonly array $expect = [],
        public readonly bool $skip = false,
        public readonly string $skipReason = '',
    ) {}
}
