<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Resolves the active SteadFast (or other) authority cluster lock day
 * from config/seo_authority_calendar.php for mentor daily plans.
 */
final class SeoAuthorityCalendar
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return (array) config('seo_authority_calendar', []);
    }

    public static function isActive(): bool
    {
        return (bool) (self::config()['active'] ?? false);
    }

    /**
     * @return array{
     *   active: bool,
     *   week: int|null,
     *   weekday: string|null,
     *   date: string,
     *   label: string|null,
     *   day: array<string, mixed>|null,
     *   pillar_path: string|null,
     *   free_tool_path: string|null,
     *   past_lock: bool
     * }
     */
    public static function resolve(?Carbon $date = null): array
    {
        $cfg = self::config();
        $date = ($date ?? Carbon::now('Asia/Dhaka'))->copy()->startOfDay();
        $dateStr = $date->toDateString();

        $empty = [
            'active' => false,
            'week' => null,
            'weekday' => null,
            'date' => $dateStr,
            'label' => null,
            'day' => null,
            'pillar_path' => $cfg['pillar_path'] ?? null,
            'free_tool_path' => $cfg['free_tool_path'] ?? null,
            'past_lock' => false,
        ];

        if (! ($cfg['active'] ?? false)) {
            return $empty;
        }

        $start = Carbon::parse((string) ($cfg['lock_start_date'] ?? $dateStr), 'Asia/Dhaka')->startOfDay();
        $weeks = max(1, (int) ($cfg['lock_weeks'] ?? 4));
        $end = $start->copy()->addWeeks($weeks)->subDay();

        if ($date->lt($start)) {
            return $empty;
        }

        $weekdayMap = [
            Carbon::MONDAY => 'mon',
            Carbon::TUESDAY => 'tue',
            Carbon::WEDNESDAY => 'wed',
            Carbon::THURSDAY => 'thu',
            Carbon::FRIDAY => 'fri',
            Carbon::SATURDAY => 'sat',
            Carbon::SUNDAY => 'sun',
        ];
        $weekday = $weekdayMap[$date->dayOfWeek] ?? 'mon';

        if ($date->gt($end)) {
            $maintenanceDay = [
                'theme' => 'SteadFast maintenance (lock weeks done)',
                'angle' => 'Deepen pillar/FAQs; do not expand to Pathao yet',
                'cta' => $cfg['pillar_path'] ?? '/steadfast-fraud-check',
                'asset' => 'maintenance + Sunday GSC',
                'checklist' => [
                    'Keep CTAs on pillar + /bd-fraud-checker',
                    'Deepen thin FAQs; fix links/schema only on other pillars',
                    'Step 10 expand only after explicit win',
                ],
                'short_hook' => null,
            ];
            if ($weekday === 'sun') {
                $maintenanceDay = self::withSundayMetrics($maintenanceDay);
            }

            return array_merge($empty, [
                'active' => true,
                'past_lock' => true,
                'weekday' => $weekday,
                'label' => 'Past lock — stay on SteadFast until Step 10 win',
                'day' => $maintenanceDay,
                'metrics_checklist' => $weekday === 'sun' ? SeoAuthorityMetrics::sundayChecklist() : null,
            ]);
        }

        $weekIndex = (int) $start->diffInWeeks($date) + 1;
        $weekIndex = min($weeks, max(1, $weekIndex));
        $week = $cfg['weeks'][$weekIndex] ?? null;
        $day = is_array($week) ? ($week['days'][$weekday] ?? null) : null;
        if (is_array($day) && $weekday === 'sun') {
            $day = self::withSundayMetrics($day);
        }

        return [
            'active' => true,
            'week' => $weekIndex,
            'weekday' => $weekday,
            'date' => $dateStr,
            'label' => is_array($week) ? ($week['label'] ?? null) : null,
            'day' => is_array($day) ? $day : null,
            'pillar_path' => $cfg['pillar_path'] ?? null,
            'free_tool_path' => $cfg['free_tool_path'] ?? null,
            'past_lock' => false,
            'metrics_checklist' => $weekday === 'sun' ? SeoAuthorityMetrics::sundayChecklist() : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $day
     * @return array<string, mixed>
     */
    private static function withSundayMetrics(array $day): array
    {
        $step9 = SeoAuthorityMetrics::sundayChecklist();
        $existing = array_values(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            $day['checklist'] ?? []
        )));
        $day['checklist'] = array_values(array_unique(array_merge(
            ['Step 9: php artisan seo:weekly-report'],
            $existing,
            $step9
        )));
        $day['asset'] = trim((string) (($day['asset'] ?? '').' + Step 9 metrics'));

        return $day;
    }
}
