<?php

use App\WiseAi\Training\TrainingPrompt;

it('exposes distinct prompts per training type', function () {
    $types = TrainingPrompt::types();
    expect($types)->toContain('merchant', 'platform', 'knowledge', 'language', 'experience');

    $all = TrainingPrompt::all();
    foreach ($types as $type) {
        expect($all[$type] ?? '')->toBeString()->not->toBeEmpty()
            ->and($all[$type])->toContain('wise-train-1.0');
    }

    expect(TrainingPrompt::for('platform'))->toContain('Platform (all keys)')
        ->and(TrainingPrompt::for('platform'))->toContain('Do NOT include Experience')
        ->and(TrainingPrompt::for('platform'))->toContain('scope=platform');

    expect(TrainingPrompt::for('merchant'))->toContain('one merchant store')
        ->and(TrainingPrompt::for('knowledge'))->toContain('Knowledge-only')
        ->and(TrainingPrompt::for('language'))->toContain('Language-only')
        ->and(TrainingPrompt::for('experience'))->toContain('Experience')
        ->and(TrainingPrompt::for('experience'))->toContain('merchant-key only');

    // Prompts must differ across types (not one mega-prompt reused).
    expect(TrainingPrompt::for('platform'))->not->toBe(TrainingPrompt::for('merchant'))
        ->and(TrainingPrompt::for('language'))->not->toBe(TrainingPrompt::for('knowledge'))
        ->and(TrainingPrompt::for('experience'))->not->toBe(TrainingPrompt::for('platform'));

    expect(TrainingPrompt::normalizeType('full'))->toBe('merchant')
        ->and(TrainingPrompt::normalizeType('PLATFORM'))->toBe('platform')
        ->and(TrainingPrompt::professional())->toBe(TrainingPrompt::for('merchant'));

    expect(TrainingPrompt::generatorSystem('platform'))->toContain('Never experience')
        ->and(TrainingPrompt::generatorSystem('language'))->toContain('Only language');

    expect(TrainingPrompt::requiresMerchantKey('knowledge'))->toBeTrue()
        ->and(TrainingPrompt::requiresMerchantKey('platform'))->toBeFalse()
        ->and(TrainingPrompt::allowedLanes('platform'))->toBe(['knowledge', 'language']);

    expect(TrainingPrompt::volumeFor('language')['target'])->toBe(25)
        ->and(TrainingPrompt::volumeFor('knowledge')['min'])->toBe(10)
        ->and(TrainingPrompt::recommendedTargetItems('merchant'))->toBe(30)
        ->and(TrainingPrompt::instructionsBn())->not->toBeEmpty()
        ->and(TrainingPrompt::for('language'))->toContain('Volume for PROPER training')
        ->and(TrainingPrompt::for('knowledge'))->toContain('TARGET ~20');

    $filtered = TrainingPrompt::filterPack([
        'version' => 'wise-train-1.0',
        'items' => [
            ['lane' => 'knowledge', 'scope' => 'merchant', 'title' => 'A', 'answer' => 'B'],
            ['lane' => 'experience', 'intent' => 'price', 'action' => 'clarify'],
            ['lane' => 'language', 'from' => 'plz', 'to' => 'please'],
        ],
    ], 'platform');

    expect($filtered['dropped'])->toBe(1)
        ->and($filtered['pack']['items'])->toHaveCount(2)
        ->and($filtered['pack']['items'][0]['scope'])->toBe('platform')
        ->and(collect($filtered['pack']['items'])->pluck('lane')->all())->toBe(['knowledge', 'language']);

    $knowledgeOnly = TrainingPrompt::filterPack([
        'items' => [
            ['lane' => 'knowledge', 'scope' => 'platform', 'title' => 'A', 'answer' => 'B'],
            ['lane' => 'language', 'from' => 'plz', 'to' => 'please'],
        ],
    ], 'knowledge');
    expect($knowledgeOnly['dropped'])->toBe(1)
        ->and($knowledgeOnly['pack']['items'])->toHaveCount(1)
        ->and($knowledgeOnly['pack']['items'][0]['scope'])->toBe('merchant');
});

it('keeps explicit generate target_items including experience strong=16', function () {
    // Regression: old code rewrote any literal 16 to the type TARGET (experience → 10).
    expect(TrainingPrompt::volumeFor('experience')['strong'])->toBe(16)
        ->and(TrainingPrompt::recommendedTargetItems('experience'))->toBe(10)
        ->and(TrainingPrompt::recommendedTargetItems('language'))->toBe(25);
});

it('builds starter packs at least TARGET volume per training type', function () {
    foreach (TrainingPrompt::types() as $type) {
        $pack = \App\WiseAi\Training\TrainingSchema::starterPack($type);
        $target = TrainingPrompt::recommendedTargetItems($type);
        $allowed = TrainingPrompt::allowedLanes($type);
        expect($pack['items'])->toBeArray()->toHaveCount($target);
        foreach ($pack['items'] as $item) {
            expect($allowed)->toContain(strtolower((string) ($item['lane'] ?? 'knowledge')));
        }
        $filtered = TrainingPrompt::filterPack($pack, $type);
        expect($filtered['dropped'])->toBe(0)
            ->and($filtered['pack']['items'])->toHaveCount($target);
    }
});
