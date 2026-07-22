<?php

namespace App\Console\Commands;

use App\Models\SiteVisitorDailyStat;
use App\Models\SiteVisitorEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SiteVisitorsRollupCommand extends Command
{
    protected $signature = 'site-visitors:rollup
                            {--date= : Single date (Y-m-d) to rebuild}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}';

    protected $description = 'Rebuild site_visitor_daily_stats rollups from site_visitor_events';

    public function handle(): int
    {
        [$from, $to] = $this->resolveRange();

        $this->info(sprintf(
            'Rolling up site visitors from %s to %s…',
            $from->toDateString(),
            $to->toDateString(),
        ));

        $upserted = 0;
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $upserted += $this->rollupDay($day);
        }

        $this->info("Upserted {$upserted} daily path rows.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(): array
    {
        if ($this->option('date')) {
            $day = Carbon::parse((string) $this->option('date'))->startOfDay();

            return [$day->copy(), $day->copy()];
        }

        $from = $this->option('from')
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : now()->subDays(7)->startOfDay();
        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'))->startOfDay()
            : now()->startOfDay();

        if ($from->gt($to)) {
            return [$to->copy(), $from->copy()];
        }

        return [$from, $to];
    }

    private function rollupDay(Carbon $day): int
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $date = $day->toDateString();

        $rows = SiteVisitorEvent::query()
            ->select([
                'path',
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as pageviews"),
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN visitor_hash END) as unique_visitors"),
                DB::raw("COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN session_hash END) as sessions"),
                DB::raw("AVG(CASE WHEN event_type = 'heartbeat' THEN engaged_ms END) as avg_engaged_ms"),
                DB::raw("SUM(CASE WHEN event_type = 'scroll_depth' AND scroll_pct >= 50 THEN 1 ELSE 0 END) as scroll_50_count"),
                DB::raw("SUM(CASE WHEN event_type = 'cta_click' THEN 1 ELSE 0 END) as cta_clicks"),
            ])
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('path')
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            SiteVisitorDailyStat::query()->updateOrCreate(
                [
                    'date' => $date,
                    'path' => $row->path,
                ],
                [
                    'pageviews' => (int) $row->pageviews,
                    'unique_visitors' => (int) $row->unique_visitors,
                    'sessions' => (int) $row->sessions,
                    'avg_engaged_ms' => (int) round((float) ($row->avg_engaged_ms ?? 0)),
                    'scroll_50_count' => (int) $row->scroll_50_count,
                    'cta_clicks' => (int) $row->cta_clicks,
                ],
            );
            $count++;
        }

        return $count;
    }
}
