<?php

use App\WiseAi\Eval\EvalRunner;
use App\WiseAi\Eval\SituationGoldens;

it('passes situation goldens S0–S9 including shortlist S5', function () {
    $report = app(EvalRunner::class)->run();

    expect($report['version'])->toBe(SituationGoldens::VERSION)
        ->and($report['failed'])->toBe(0)
        ->and($report['skipped'])->toBe(0)
        ->and($report['passed'])->toBe(10);

    $byId = collect($report['results'])->keyBy('id');
    expect($byId->get('S5')['status'] ?? null)->toBe('passed');
});
