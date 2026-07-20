<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AdminSidebarNavOrder
{
    public const SETTING_KEY = 'admin.sidebar_nav_order';

    private const CACHE_KEY = 'admin.sidebar_nav_order.effective';

    /**
     * Canonical catalog titles — must stay in sync with LeftSidebar.vue `allSections`.
     *
     * @return array{
     *     sections: list<string>,
     *     items: array<string, list<string>>,
     *     children: array<string, list<string>>
     * }
     */
    public function catalog(): array
    {
        return [
            'sections' => [
                'Overview',
                'Merchants',
                'Platform',
                'Marketing',
                'Analytics',
                'System',
            ],
            'items' => [
                'Overview' => ['Dashboard'],
                'Merchants' => ['Merchants', 'Fraud Checker', 'Whitelisted Domains'],
                'Platform' => [
                    'Plugin Versions',
                    'Plans & Billing',
                    'Settings',
                    'Tutorials',
                    'Media Library',
                    'Blog Posts',
                    'Subscription Alerts',
                ],
                'Marketing' => ['Meta Pixel'],
                'Analytics' => ['Visitor Report', 'Use Analysis', 'Order Intelligence'],
                'System' => [
                    'Webhook Activities',
                    'Error Logs',
                    'Roles & Access',
                    'Database Backups',
                    'Database Migrations',
                    'System Maintenance',
                    'Developer API',
                ],
            ],
            'children' => [
                'Merchants' => ['All Merchants', 'Trashed Merchants'],
                'Fraud Checker' => ['Phone Check', 'Partner Credentials', 'Token & CURL'],
                'Plans & Billing' => [
                    'Pricing Plans',
                    'Landing Orders',
                    'Payment Requests',
                    'Customer Notices',
                ],
            ],
        ];
    }

    /**
     * Effective order (DB override merged with catalog defaults).
     *
     * @return array{
     *     sections: list<string>,
     *     items: array<string, list<string>>,
     *     children: array<string, list<string>>
     * }
     */
    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            return $this->mergeWithCatalog($this->readStored());
        });
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array{
     *     sections: list<string>,
     *     items: array<string, list<string>>,
     *     children: array<string, list<string>>
     * }
     */
    public function update(array $order): array
    {
        $current = $this->mergeWithCatalog($this->readStored());
        $incoming = $this->sanitize($order);

        $combined = [
            'sections' => $this->applyPartialOrder($current['sections'], $incoming['sections']),
            'items' => [],
            'children' => [],
        ];

        foreach ($current['items'] as $section => $previousItems) {
            $combined['items'][$section] = $this->applyPartialOrder(
                $previousItems,
                $incoming['items'][$section] ?? [],
            );
        }

        foreach ($current['children'] as $parent => $previousChildren) {
            $combined['children'][$parent] = $this->applyPartialOrder(
                $previousChildren,
                $incoming['children'][$parent] ?? [],
            );
        }

        $merged = $this->mergeWithCatalog($combined);

        $this->put($merged);
        Cache::forget(self::CACHE_KEY);

        return $merged;
    }

    /**
     * Reorder titles present in $incoming; keep titles only in $previous in their relative slots.
     *
     * @param  list<string>  $previous
     * @param  list<string>  $incoming
     * @return list<string>
     */
    public function applyPartialOrder(array $previous, array $incoming): array
    {
        if ($incoming === []) {
            return $previous;
        }

        $incomingSet = array_fill_keys($incoming, true);
        $queue = array_values($incoming);
        $queueIndex = 0;
        $out = [];

        foreach ($previous as $title) {
            if (isset($incomingSet[$title])) {
                if ($queueIndex < count($queue)) {
                    $out[] = $queue[$queueIndex];
                    $queueIndex++;
                }

                continue;
            }

            $out[] = $title;
        }

        while ($queueIndex < count($queue)) {
            $title = $queue[$queueIndex];
            $queueIndex++;

            if (! in_array($title, $out, true)) {
                $out[] = $title;
            }
        }

        return $out;
    }

    /**
     * @return array{
     *     sections?: list<string>,
     *     items?: array<string, list<string>>,
     *     children?: array<string, list<string>>
     * }|null
     */
    private function readStored(): ?array
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', self::SETTING_KEY)->first();

        if (! $row || ! is_array($row->value)) {
            return null;
        }

        return $row->value;
    }

    /**
     * @param  array{
     *     sections: list<string>,
     *     items: array<string, list<string>>,
     *     children: array<string, list<string>>
     * }  $order
     */
    private function put(array $order): void
    {
        if (! $this->tableReady()) {
            return;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $order],
        );
    }

    /**
     * Keep only known titles; preserve requested relative order.
     *
     * @param  array<string, mixed>  $order
     * @return array{
     *     sections: list<string>,
     *     items: array<string, list<string>>,
     *     children: array<string, list<string>>
     * }
     */
    public function sanitize(array $order): array
    {
        $catalog = $this->catalog();

        $sections = $this->orderedIntersection(
            $this->stringList($order['sections'] ?? null),
            $catalog['sections'],
        );

        $items = [];
        foreach ($catalog['items'] as $section => $allowed) {
            $requested = is_array($order['items'] ?? null)
                ? $this->stringList($order['items'][$section] ?? null)
                : [];
            $items[$section] = $this->orderedIntersection($requested, $allowed);
        }

        $children = [];
        foreach ($catalog['children'] as $parent => $allowed) {
            $requested = is_array($order['children'] ?? null)
                ? $this->stringList($order['children'][$parent] ?? null)
                : [];
            $children[$parent] = $this->orderedIntersection($requested, $allowed);
        }

        return [
            'sections' => $sections,
            'items' => $items,
            'children' => $children,
        ];
    }

    /**
     * Fill any missing catalog entries after the sanitized order.
     *
     * @param  array{
     *     sections?: list<string>,
     *     items?: array<string, list<string>>,
     *     children?: array<string, list<string>>
     * }|null  $stored
     * @return array{
     *     sections: list<string>,
     *     items: array<string, list<string>>,
     *     children: array<string, list<string>>
     * }
     */
    public function mergeWithCatalog(?array $stored): array
    {
        $catalog = $this->catalog();
        $sanitized = $stored ? $this->sanitize($stored) : [
            'sections' => [],
            'items' => [],
            'children' => [],
        ];

        return [
            'sections' => $this->appendMissing($sanitized['sections'], $catalog['sections']),
            'items' => collect($catalog['items'])
                ->map(fn (array $allowed, string $section) => $this->appendMissing(
                    $sanitized['items'][$section] ?? [],
                    $allowed,
                ))
                ->all(),
            'children' => collect($catalog['children'])
                ->map(fn (array $allowed, string $parent) => $this->appendMissing(
                    $sanitized['children'][$parent] ?? [],
                    $allowed,
                ))
                ->all(),
        ];
    }

    /**
     * @param  list<string>  $requested
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function orderedIntersection(array $requested, array $allowed): array
    {
        $allowedSet = array_fill_keys($allowed, true);
        $seen = [];
        $out = [];

        foreach ($requested as $title) {
            if (! isset($allowedSet[$title]) || isset($seen[$title])) {
                continue;
            }
            $seen[$title] = true;
            $out[] = $title;
        }

        return $out;
    }

    /**
     * @param  list<string>  $ordered
     * @param  list<string>  $all
     * @return list<string>
     */
    private function appendMissing(array $ordered, array $all): array
    {
        $seen = array_fill_keys($ordered, true);
        $out = $ordered;

        foreach ($all as $title) {
            if (! isset($seen[$title])) {
                $out[] = $title;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
