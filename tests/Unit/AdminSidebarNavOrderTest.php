<?php

use App\Services\AdminSidebarNavOrder;

it('merges partial stored order with full catalog defaults', function () {
    $service = new AdminSidebarNavOrder;

    $merged = $service->mergeWithCatalog([
        'sections' => ['Platform', 'Overview'],
        'items' => [
            'Platform' => ['Blog Posts', 'Settings'],
        ],
        'children' => [
            'Merchants' => ['Trashed Merchants'],
        ],
    ]);

    expect($merged['sections'][0])->toBe('Platform')
        ->and($merged['sections'][1])->toBe('Overview')
        ->and($merged['sections'])->toContain('Merchants')
        ->and($merged['items']['Platform'][0])->toBe('Blog Posts')
        ->and($merged['items']['Platform'][1])->toBe('Settings')
        ->and($merged['items']['Platform'])->toContain('Plugin Versions')
        ->and($merged['children']['Merchants'][0])->toBe('Trashed Merchants')
        ->and($merged['children']['Merchants'])->toContain('All Merchants');
});

it('sanitizes unknown titles from an incoming payload', function () {
    $service = new AdminSidebarNavOrder;

    $sanitized = $service->sanitize([
        'sections' => ['Nope', 'Analytics'],
        'items' => [
            'Analytics' => ['Visitor Report', 'Ghost'],
        ],
        'children' => [
            'Fraud Checker' => ['Ghost', 'Phone Check'],
        ],
    ]);

    expect($sanitized['sections'])->toBe(['Analytics'])
        ->and($sanitized['items']['Analytics'])->toBe(['Visitor Report'])
        ->and($sanitized['children']['Fraud Checker'])->toBe(['Phone Check']);
});

it('reorders only the visible subset while keeping hidden titles in place', function () {
    $service = new AdminSidebarNavOrder;

    $result = $service->applyPartialOrder(
        ['Blog Posts', 'Plugin Versions', 'Settings', 'Tutorials'],
        ['Settings', 'Blog Posts'],
    );

    expect($result)->toBe(['Settings', 'Plugin Versions', 'Blog Posts', 'Tutorials']);
});

it('keeps previous order when incoming partial list is empty', function () {
    $service = new AdminSidebarNavOrder;

    $previous = ['Overview', 'Merchants', 'Platform'];

    expect($service->applyPartialOrder($previous, []))->toBe($previous);
});

it('exposes a complete catalog with unique section and item titles', function () {
    $catalog = (new AdminSidebarNavOrder)->catalog();

    expect($catalog['sections'])->toHaveCount(6)
        ->and($catalog['sections'])->toBe(array_values(array_unique($catalog['sections'])))
        ->and(array_keys($catalog['items']))->toEqualCanonicalizing($catalog['sections']);

    foreach ($catalog['items'] as $section => $titles) {
        expect($titles)->not->toBeEmpty()
            ->and($titles)->toBe(array_values(array_unique($titles)));
    }

    foreach ($catalog['children'] as $parent => $titles) {
        expect($titles)->not->toBeEmpty()
            ->and($titles)->toBe(array_values(array_unique($titles)));

        $parents = collect($catalog['items'])->flatten()->all();
        expect($parents)->toContain($parent);
    }
});
