<?php

namespace App\Services\LandingAi;

/**
 * Turns the field list sent by the WEL widget into a strict OpenAI JSON schema,
 * and cleans the model answer back into widget settings.
 *
 * Spec shape:
 *  fields: [{key, label, note?, max?, multiline?, placeholder?}]
 *  lists:  [{key, label, note?, min?, max?, fields: [{key, label?, type?: string|integer, max?, min?, options?}]}]
 */
class LandingAiSchema
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    /**
     * @param  array<string, mixed>  $raw
     * @return array{fields: list<array<string, mixed>>, lists: list<array<string, mixed>>}
     */
    public function normalizeSpec(array $raw): array
    {
        $fields = [];
        $seen = [];
        $maxFields = (int) config('landing_ai.max_text_fields', 160);

        foreach (array_slice((array) ($raw['fields'] ?? []), 0, $maxFields) as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            if (! preg_match(self::KEY_PATTERN, $key) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $placeholder = (string) ($field['placeholder'] ?? '');

            $fields[] = [
                'key' => $key,
                'label' => $this->short($field['label'] ?? $key, 80),
                'note' => $this->short($field['note'] ?? '', 240),
                'max' => $this->clampInt($field['max'] ?? 160, 8, 1200),
                'multiline' => (bool) ($field['multiline'] ?? false),
                'placeholder' => $placeholder === '{n}' ? '{n}' : null,
            ];
        }

        $lists = [];
        $maxLists = (int) config('landing_ai.max_list_fields', 20);

        foreach (array_slice((array) ($raw['lists'] ?? []), 0, $maxLists) as $list) {
            if (! is_array($list)) {
                continue;
            }
            $key = (string) ($list['key'] ?? '');
            if (! preg_match(self::KEY_PATTERN, $key) || isset($seen[$key])) {
                continue;
            }

            $subFields = [];
            $subSeen = [];
            foreach (array_slice((array) ($list['fields'] ?? []), 0, 6) as $sub) {
                if (! is_array($sub)) {
                    continue;
                }
                $subKey = (string) ($sub['key'] ?? '');
                if (! preg_match(self::KEY_PATTERN, $subKey) || isset($subSeen[$subKey])) {
                    continue;
                }
                $subSeen[$subKey] = true;
                $type = ($sub['type'] ?? 'string') === 'integer' ? 'integer' : 'string';

                $entry = [
                    'key' => $subKey,
                    'label' => $this->short($sub['label'] ?? $subKey, 80),
                    'type' => $type,
                ];
                if ($type === 'integer') {
                    $entry['min'] = $this->clampInt($sub['min'] ?? 0, -1000000, 1000000);
                    $entry['max'] = max($entry['min'], $this->clampInt($sub['max'] ?? 1000000, -1000000, 1000000));
                } else {
                    $entry['max'] = $this->clampInt($sub['max'] ?? 160, 4, 800);
                    $options = array_values(array_unique(array_filter(
                        array_map(fn ($o) => $this->short($o, 40), (array) ($sub['options'] ?? [])),
                        fn ($o) => $o !== '',
                    )));
                    if ($options !== []) {
                        $entry['options'] = array_slice($options, 0, 20);
                    }
                }
                $subFields[] = $entry;
            }

            if ($subFields === []) {
                continue;
            }
            $seen[$key] = true;
            $min = $this->clampInt($list['min'] ?? 1, 0, 12);

            $lists[] = [
                'key' => $key,
                'label' => $this->short($list['label'] ?? $key, 80),
                'note' => $this->short($list['note'] ?? '', 240),
                'min' => $min,
                'max' => max(max(1, $min), $this->clampInt($list['max'] ?? 6, 1, 12)),
                'fields' => $subFields,
            ];
        }

        return ['fields' => $fields, 'lists' => $lists];
    }

    /**
     * @param  array{fields: list<array<string, mixed>>, lists: list<array<string, mixed>>}  $spec
     * @return array<string, mixed>
     */
    public function jsonSchema(array $spec): array
    {
        $properties = [];

        if ($spec['fields'] !== []) {
            $texts = [];
            foreach ($spec['fields'] as $field) {
                $texts[$field['key']] = [
                    'type' => 'string',
                    'description' => $this->fieldDescription($field),
                ];
            }
            $properties['texts'] = $this->object($texts, 'Page text fields. An empty string keeps the merchant\'s current text.');
        }

        if ($spec['lists'] !== []) {
            $lists = [];
            foreach ($spec['lists'] as $list) {
                $row = [];
                foreach ($list['fields'] as $sub) {
                    $row[$sub['key']] = $this->subFieldSchema($sub);
                }
                $count = $list['min'] === $list['max']
                    ? 'Exactly '.$list['max'].' rows.'
                    : 'Between '.max(1, $list['min']).' and '.$list['max'].' rows.';
                $lists[$list['key']] = [
                    'type' => 'array',
                    'description' => trim($list['label'].'. '.$list['note'].' '.$count),
                    'items' => $this->object($row),
                ];
            }
            $properties['lists'] = $this->object($lists, 'Repeating rows. An empty array keeps the merchant\'s current rows.');
        }

        $properties['notes'] = [
            'type' => 'string',
            'description' => 'One to three short English sentences for the merchant: which inputs you used, any reference page you could not use, and what to double-check before publishing. Never customer copy.',
        ];

        return $this->object($properties);
    }

    /**
     * @param  array{fields: list<array<string, mixed>>, lists: list<array<string, mixed>>}  $spec
     * @return array{settings: array<string, mixed>, notes: string, warnings: list<string>}
     */
    /**
     * @param  array{allowed_price_literals?: list<string>}  $guardrails
     */
    public function sanitize(array $spec, mixed $answer, array $guardrails = []): array
    {
        $answer = is_array($answer) ? $answer : [];
        $texts = is_array($answer['texts'] ?? null) ? $answer['texts'] : [];
        $lists = is_array($answer['lists'] ?? null) ? $answer['lists'] : [];
        $settings = [];
        $warnings = [];
        $allowedPrices = array_values(array_filter((array) ($guardrails['allowed_price_literals'] ?? []), fn ($v) => is_string($v) && $v !== ''));

        foreach ($spec['fields'] as $field) {
            $value = $texts[$field['key']] ?? null;
            if (! is_string($value)) {
                continue;
            }
            $value = $this->cleanText($value, $field['multiline'], $field['max']);
            if ($value === '') {
                continue;
            }
            if ($field['placeholder'] === '{n}' && ! str_contains($value, '{n}')) {
                $warnings[] = $field['label'].' was skipped because the live count {n} was missing.';

                continue;
            }
            if ($allowedPrices !== [] && $this->mentionsUnknownPrice($value, $allowedPrices)) {
                $warnings[] = $field['label'].' was skipped because it mentioned a price not in product facts.';

                continue;
            }
            $settings[$field['key']] = $value;
        }

        foreach ($spec['lists'] as $list) {
            $rows = $lists[$list['key']] ?? null;
            if (! is_array($rows)) {
                continue;
            }

            $clean = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $out = [];
                $hasText = false;
                foreach ($list['fields'] as $sub) {
                    $value = $row[$sub['key']] ?? null;
                    if ($sub['type'] === 'integer') {
                        $number = is_numeric($value) ? (int) round((float) $value) : $sub['max'];
                        $out[$sub['key']] = max($sub['min'], min($sub['max'], $number));

                        continue;
                    }
                    $text = is_string($value) ? $this->cleanText($value, false, $sub['max']) : '';
                    if ($text !== '' && $allowedPrices !== [] && $this->mentionsUnknownPrice($text, $allowedPrices)) {
                        $text = '';
                    }
                    if (isset($sub['options']) && ! in_array($text, $sub['options'], true)) {
                        $text = $sub['options'][count($clean) % count($sub['options'])];
                    } elseif ($text !== '') {
                        $hasText = true;
                    }
                    $out[$sub['key']] = $text;
                }
                if ($hasText) {
                    $clean[] = $out;
                }
                if (count($clean) >= $list['max']) {
                    break;
                }
            }

            if ($clean === []) {
                continue;
            }
            if (count($clean) < $list['min']) {
                $warnings[] = $list['label'].': only '.count($clean).' rows were written.';
            }
            $settings[$list['key']] = $clean;
        }

        $notes = is_string($answer['notes'] ?? null)
            ? $this->cleanText($answer['notes'], false, 600)
            : '';

        return ['settings' => $settings, 'notes' => $notes, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function fieldDescription(array $field): string
    {
        $parts = [$field['label'].'.'];
        if ($field['note'] !== '') {
            $parts[] = rtrim($field['note'], '.').'.';
        }
        if ($field['placeholder'] === '{n}') {
            $parts[] = 'Must contain the {n} placeholder exactly once.';
        }
        $parts[] = 'At most '.$field['max'].' characters'.($field['multiline'] ? '; line breaks allowed.' : '; one line.');

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $sub
     * @return array<string, mixed>
     */
    private function subFieldSchema(array $sub): array
    {
        if ($sub['type'] === 'integer') {
            return [
                'type' => 'integer',
                'description' => $sub['label'].'. A whole number from '.$sub['min'].' to '.$sub['max'].'.',
            ];
        }

        $schema = [
            'type' => 'string',
            'description' => $sub['label'].'. At most '.$sub['max'].' characters; one line.',
        ];
        if (isset($sub['options'])) {
            $schema['enum'] = $sub['options'];
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function object(array $properties, ?string $description = null): array
    {
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($properties),
            'properties' => $properties === [] ? new \stdClass : $properties,
        ];
        if ($description !== null) {
            $schema['description'] = $description;
        }

        return $schema;
    }

    private function cleanText(string $value, bool $multiline, int $max): string
    {
        $value = strip_tags($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $value) ?? $value;

        if ($multiline) {
            $value = preg_replace('/[^\S\n]+/u', ' ', $value) ?? $value;
            $value = preg_replace('/ *\n */u', "\n", $value) ?? $value;
            $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value;
        } else {
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        }

        $value = trim($value);
        if (mb_strlen($value) > $max) {
            $cut = mb_substr($value, 0, $max);
            $space = mb_strrpos($cut, ' ');
            $cut = $space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut;
            $value = preg_replace('/[\s,;:\-—।]+$/u', '', $cut) ?? $cut;
        }

        return $value;
    }

    private function short(mixed $value, int $max): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return mb_substr($value, 0, $max);
    }

    private function clampInt(mixed $value, int $min, int $max): int
    {
        $number = is_numeric($value) ? (int) $value : $min;

        return max($min, min($max, $number));
    }

    /**
     * @param  list<string>  $allowedLiterals
     */
    private function mentionsUnknownPrice(string $text, array $allowedLiterals): bool
    {
        if (! preg_match('/(?:৳|টাকা|tk|price)/ui', $text)) {
            return false;
        }
        if (! preg_match_all('/(\d[\d,]*(?:\.\d+)?)/u', $text, $matches)) {
            return false;
        }
        $allowed = [];
        foreach ($allowedLiterals as $literal) {
            if (preg_match_all('/(\d[\d,]*(?:\.\d+)?)/u', $literal, $parts)) {
                foreach ($parts[1] as $num) {
                    $allowed[str_replace(',', '', $num)] = true;
                }
            }
        }
        if ($allowed === []) {
            return false;
        }
        foreach ($matches[1] as $num) {
            $norm = str_replace(',', '', $num);
            if ($norm !== '' && ! isset($allowed[$norm])) {
                return true;
            }
        }

        return false;
    }
}
