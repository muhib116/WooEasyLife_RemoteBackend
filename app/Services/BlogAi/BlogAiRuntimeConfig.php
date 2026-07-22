<?php

namespace App\Services\BlogAi;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Admin overrides for Blog AI feature flags (platform_settings → config).
 */
class BlogAiRuntimeConfig
{
    public const PREFIX = 'blog_ai.settings.';

    /**
     * @var array<string, array{config: string, type: 'bool'|'string', label: string, help: string, group: 'defaults'|'configure'}>
     */
    public const FIELDS = [
        // Safe production defaults (ON). Admin can still override.
        'enabled' => [
            'config' => 'blog_ai.enabled',
            'type' => 'bool',
            'group' => 'defaults',
            'label' => 'Blog AI enabled',
            'help' => 'Default ON. Master switch for AI drafting and tools.',
        ],
        'smart_one_click' => [
            'config' => 'blog_ai.auto.smart_one_click',
            'type' => 'bool',
            'group' => 'defaults',
            'label' => 'Smart One-Click Post',
            'help' => 'Default ON. “Generate smart post” button.',
        ],
        'prefer_gsc' => [
            'config' => 'blog_ai.auto.prefer_gsc',
            'type' => 'bool',
            'group' => 'defaults',
            'label' => 'Prefer Search Console keywords',
            'help' => 'Default ON. Uses free real GSC demand when available.',
        ],
        'competitors_enabled' => [
            'config' => 'blog_ai.competitors.enabled',
            'type' => 'bool',
            'group' => 'defaults',
            'label' => 'Competitor analyzer',
            'help' => 'Default ON. Rival gap analysis for drafts.',
        ],
        'competitors_in_prompts' => [
            'config' => 'blog_ai.competitors.in_prompts',
            'type' => 'bool',
            'group' => 'defaults',
            'label' => 'Inject competitor gaps into drafts',
            'help' => 'Default ON. Pass open gaps into outline/draft.',
        ],
        'discovery_enabled' => [
            'config' => 'blog_ai.competitors.discovery.enabled',
            'type' => 'bool',
            'group' => 'defaults',
            'label' => 'Auto-find rivals',
            'help' => 'Default ON. Discover URLs when none pasted (DDG/Brave/Bing).',
        ],
        'discovery_auto_on_smart' => [
            'config' => 'blog_ai.competitors.discovery.auto_on_smart_post',
            'type' => 'bool',
            'group' => 'defaults',
            'label' => 'Auto-analyze rivals on Smart Post',
            'help' => 'Default ON. Auto-discover + analyze before Smart Post draft.',
        ],
        'landing_ref_fetch' => [
            'config' => 'blog_ai.landing_reference.fetch_live',
            'type' => 'bool',
            'group' => 'defaults',
            'label' => 'Fetch live landing page',
            'help' => 'Default ON. Fetch own landing URL as content reference.',
        ],
        'memory_enabled' => [
            'config' => 'blog_ai.memory.enabled',
            'type' => 'bool',
            'group' => 'defaults',
            'label' => 'Standing memory',
            'help' => 'Default ON. Prefer/avoid keywords & instructions.',
        ],
        'memory_in_prompts' => [
            'config' => 'blog_ai.memory.in_prompts',
            'type' => 'bool',
            'group' => 'defaults',
            'label' => 'Inject standing memory into drafts',
            'help' => 'Default ON. Include active memories in every draft.',
        ],

        // You configure these (no secret/URL defaults forced).
        'queue' => [
            'config' => 'blog_ai.queue',
            'type' => 'bool',
            'group' => 'configure',
            'label' => 'Use queue worker (required in production)',
            'help' => 'Default OFF for local. Turn ON in production and run queue:work --timeout=900.',
        ],
        'landing_public_base_url' => [
            'config' => 'blog_ai.landing_reference.public_base_url',
            'type' => 'string',
            'group' => 'configure',
            'label' => 'Public site base URL',
            'help' => 'Optional. e.g. https://wooeasylife.com — blank uses APP_URL.',
        ],
        'brave_api_key' => [
            'config' => 'blog_ai.competitors.discovery.api_key',
            'type' => 'string',
            'group' => 'configure',
            'label' => 'Brave Search API key',
            'help' => 'Optional. Better rival discovery.',
        ],
        'bing_api_key' => [
            'config' => 'blog_ai.competitors.discovery.bing_api_key',
            'type' => 'string',
            'group' => 'configure',
            'label' => 'Bing Search API key',
            'help' => 'Optional fallback when Brave is empty.',
        ],
    ];

    public function applyOverrides(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        foreach (self::FIELDS as $field => $meta) {
            $stored = $this->getStored($field);
            if ($stored === null) {
                continue;
            }
            Config::set($meta['config'], $meta['type'] === 'bool' ? $this->toBool($stored) : $stored);
        }
    }

    /**
     * @return array{
     *     settings: array<string, mixed>,
     *     sources: array<string, string>,
     *     how_to: list<array{title: string, body: string}>,
     *     ops_notes: list<string>
     * }
     */
    public function snapshot(): array
    {
        $this->applyOverrides();

        $settings = [];
        $sources = [];
        foreach (self::FIELDS as $field => $meta) {
            $stored = $this->getStored($field);
            $effective = config($meta['config']);
            if ($meta['type'] === 'bool') {
                $settings[$field] = (bool) $effective;
            } elseif (in_array($field, ['brave_api_key', 'bing_api_key'], true)) {
                $raw = is_string($effective) ? trim($effective) : '';
                $settings[$field] = '';
                $settings[$field.'_set'] = $raw !== '';
            } else {
                $settings[$field] = is_string($effective) ? $effective : (string) ($effective ?? '');
            }
            $sources[$field] = $stored !== null ? 'database' : 'env';
        }

        return [
            'settings' => $settings,
            'sources' => $sources,
            'fields' => collect(self::FIELDS)->map(fn (array $meta, string $key) => [
                'key' => $key,
                'type' => $meta['type'],
                'group' => $meta['group'] ?? 'configure',
                'label' => $meta['label'],
                'help' => $meta['help'],
                'secret' => in_array($key, ['brave_api_key', 'bing_api_key'], true),
            ])->values()->all(),
            'how_to' => $this->howToSteps(),
            'ops_notes' => [
                'Defaults section is already ON — change only if you need to.',
                'Configure yourself: Queue (prod), public URL, Brave/Bing keys.',
                'OpenAI API key & models → Landing Settings → AI tab.',
                'GSC connect + Blog learning insights → SEO & Learning.',
                'Production: enable Queue here, then run queue:work --timeout=900.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(array $input): array
    {
        if (! $this->tableReady()) {
            throw ValidationException::withMessages([
                'settings' => 'Platform settings table is not available. Run migrations first.',
            ]);
        }

        foreach (self::FIELDS as $field => $meta) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            if ($meta['type'] === 'bool') {
                $this->put($field, $this->toBool($input[$field]) ? '1' : '0');
                Config::set($meta['config'], $this->toBool($input[$field]));
                continue;
            }

            $value = trim((string) ($input[$field] ?? ''));

            // Secrets: blank means "keep existing override / env" unless clear_* is set.
            if (in_array($field, ['brave_api_key', 'bing_api_key'], true)) {
                if ($value === '') {
                    if (! empty($input['clear_'.$field])) {
                        $this->put($field, null);
                    }
                    continue;
                }
                $this->put($field, $value);
                Config::set($meta['config'], $value);
                continue;
            }

            if ($value === '') {
                $this->put($field, null);
                continue;
            }

            if ($field === 'landing_public_base_url' && ! preg_match('#^https?://#i', $value)) {
                throw ValidationException::withMessages([
                    'landing_public_base_url' => 'Public site base URL must start with http:// or https://',
                ]);
            }

            $this->put($field, $value);
            Config::set($meta['config'], $value);
        }

        $this->applyOverrides();

        return $this->snapshot();
    }

    public function resetToEnv(): array
    {
        if ($this->tableReady()) {
            foreach (array_keys(self::FIELDS) as $field) {
                $this->put($field, null);
            }
        }

        return $this->snapshot();
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    private function howToSteps(): array
    {
        return [
            [
                'title' => '1. Defaults are already set',
                'body' => 'Blog AI, Smart Post, Prefer GSC, Competitors, Discovery, Landing fetch, and Memory default ON. Only change those if you need to turn something off.',
            ],
            [
                'title' => '2. Configure yourself (Settings page)',
                'body' => 'Turn ON Queue for production. Optionally set public site URL and Brave/Bing keys. OpenAI key stays in Landing Settings → AI.',
            ],
            [
                'title' => '3. Connect free keyword demand',
                'body' => 'SEO & Learning → Connect GSC → Run Blog learning insights. Smart Post will prefer striking-distance / fix-CTR queries.',
            ],
            [
                'title' => '4. Topic Clusters + memory',
                'body' => 'Set cluster seeds + primary_path. Add standing memory instructions (Messenger-style Bangla) on Blog AI.',
            ],
            [
                'title' => '5. Generate',
                'body' => 'Blog AI → Generate smart post (or Draft on a GSC opportunity). Review, then publish.',
            ],
        ];
    }

    private function getStored(string $field): ?string
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', self::PREFIX.$field)->first();
        if (! $row || $row->value === null || $row->value === '') {
            return null;
        }

        return is_string($row->value) ? trim($row->value) : (string) $row->value;
    }

    private function put(string $field, ?string $value): void
    {
        if (! $this->tableReady()) {
            return;
        }

        $key = self::PREFIX.$field;
        if ($value === null || $value === '') {
            PlatformSetting::query()->where('key', $key)->delete();

            return;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
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
