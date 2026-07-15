<?php

namespace App\Services\BlogAi;

use App\Models\BlogContentEvent;
use App\Models\BlogPost;
use App\Models\BlogPostAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * First-party blog engagement tracking (views / CTA).
 */
class BlogAnalyticsTracker
{
    public function track(Request $request, string $slug, string $eventType, array $extra = []): bool
    {
        if (! config('blog_ai.analytics.enabled', true)) {
            return false;
        }

        $slug = trim($slug);
        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return false;
        }

        if (! in_array($eventType, [
            BlogContentEvent::TYPE_VIEW,
            BlogContentEvent::TYPE_CTA_CLICK,
            BlogContentEvent::TYPE_SCROLL,
        ], true)) {
            return false;
        }

        $visitor = $this->resolveVisitorHash($request, $extra['visitor_id'] ?? null);
        $session = $this->sessionHash($request);

        if ($eventType === BlogContentEvent::TYPE_VIEW) {
            $dedupeKey = 'blog_analytics:view:'.$slug.':'.$visitor.':'.now()->toDateString();
            if (! Cache::add($dedupeKey, 1, now()->endOfDay())) {
                return false;
            }
        }

        if ($eventType === BlogContentEvent::TYPE_CTA_CLICK) {
            $label = Str::limit((string) ($extra['cta_label'] ?? 'cta'), 80, '');
            $dedupeKey = 'blog_analytics:cta:'.$slug.':'.$visitor.':'.md5($label).':'.now()->format('Y-m-d-H');
            if (! Cache::add($dedupeKey, 1, now()->addHour())) {
                return false;
            }
        }

        if ($eventType === BlogContentEvent::TYPE_SCROLL) {
            $dedupeKey = 'blog_analytics:scroll:'.$slug.':'.$visitor.':'.now()->toDateString();
            if (! Cache::add($dedupeKey, 1, now()->endOfDay())) {
                return false;
            }
        }

        if ($this->isVisitorOverQuota($visitor, $eventType)) {
            return false;
        }

        $post = BlogPost::query()->where('slug', $slug)->first(['id', 'title', 'focus_keyword', 'cluster', 'locale']);

        BlogContentEvent::query()->create([
            'slug' => $slug,
            'blog_post_id' => $post?->id,
            'event_type' => $eventType,
            'visitor_hash' => $visitor,
            'session_hash' => $session,
            'cta_label' => $extra['cta_label'] ?? null,
            'referrer_host' => $this->referrerHost($request),
            'scroll_pct' => isset($extra['scroll_pct']) ? (int) $extra['scroll_pct'] : null,
        ]);

        $this->bumpVisitorQuota($visitor, $eventType);

        // Lightweight live counters for CMS (full rollup refreshes daily).
        if (in_array($eventType, [
            BlogContentEvent::TYPE_VIEW,
            BlogContentEvent::TYPE_CTA_CLICK,
            BlogContentEvent::TYPE_SCROLL,
        ], true)) {
            $this->bumpLiveCounters($slug, $eventType, $post);
        }

        return true;
    }

    private function isVisitorOverQuota(string $visitor, string $eventType): bool
    {
        if ($eventType === BlogContentEvent::TYPE_VIEW) {
            $max = max(5, (int) config('blog_ai.analytics.max_views_per_visitor_day', 40));
            $key = 'blog_analytics:quota:view:'.$visitor.':'.now()->toDateString();

            return (int) Cache::get($key, 0) >= $max;
        }

        if ($eventType === BlogContentEvent::TYPE_CTA_CLICK) {
            $max = max(3, (int) config('blog_ai.analytics.max_cta_per_visitor_hour', 20));
            $key = 'blog_analytics:quota:cta:'.$visitor.':'.now()->format('Y-m-d-H');

            return (int) Cache::get($key, 0) >= $max;
        }

        return false;
    }

    private function bumpVisitorQuota(string $visitor, string $eventType): void
    {
        if ($eventType === BlogContentEvent::TYPE_VIEW) {
            $key = 'blog_analytics:quota:view:'.$visitor.':'.now()->toDateString();
            Cache::put($key, (int) Cache::get($key, 0) + 1, now()->endOfDay());
        }

        if ($eventType === BlogContentEvent::TYPE_CTA_CLICK) {
            $key = 'blog_analytics:quota:cta:'.$visitor.':'.now()->format('Y-m-d-H');
            Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addHour());
        }
    }

    private function bumpLiveCounters(string $slug, string $eventType, ?BlogPost $post): void
    {
        $row = BlogPostAnalytics::query()->firstOrNew(['slug' => $slug]);
        if (! $row->exists) {
            $row->fill([
                'blog_post_id' => $post?->id,
                'title' => $post?->title,
                'focus_keyword' => $post?->focus_keyword,
                'cluster' => $post?->cluster,
                'locale' => $post?->locale ?? 'bn',
            ]);
        }

        if ($eventType === BlogContentEvent::TYPE_VIEW) {
            $row->views_total = (int) $row->views_total + 1;
            $row->last_viewed_at = now();
        }

        if ($eventType === BlogContentEvent::TYPE_CTA_CLICK) {
            $row->cta_clicks_total = (int) $row->cta_clicks_total + 1;
        }

        if ($eventType === BlogContentEvent::TYPE_SCROLL) {
            $meta = is_array($row->meta_json) ? $row->meta_json : [];
            $meta['scroll_50_total'] = (int) ($meta['scroll_50_total'] ?? 0) + 1;
            $row->meta_json = $meta;
        }

        $row->save();
    }

    public function visitorHash(Request $request): string
    {
        return $this->resolveVisitorHash($request, null);
    }

    public function resolveVisitorHash(Request $request, mixed $clientId): string
    {
        $client = is_string($clientId) ? trim($clientId) : '';
        if ($client !== '' && preg_match('/^[a-f0-9]{16,64}$/i', $client)) {
            return strtolower($client);
        }

        $cookie = (string) $request->cookie('wel_blog_vid', '');
        if ($cookie !== '' && preg_match('/^[a-f0-9]{16,64}$/i', $cookie)) {
            return strtolower($cookie);
        }

        return hash('sha256', $request->ip().'|'.$request->userAgent());
    }

    public function sessionHash(Request $request): string
    {
        try {
            $sid = $request->session()->getId();
        } catch (\Throwable) {
            $sid = $request->ip().'|'.$request->userAgent().'|'.now()->format('Y-m-d-H');
        }

        return hash('sha256', $sid.'|blog');
    }

    private function referrerHost(Request $request): ?string
    {
        $ref = (string) $request->headers->get('referer', '');
        if ($ref === '') {
            return null;
        }

        $host = parse_url($ref, PHP_URL_HOST);

        return is_string($host) ? Str::limit($host, 120, '') : null;
    }
}
