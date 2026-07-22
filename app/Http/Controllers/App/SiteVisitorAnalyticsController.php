<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SiteVisitorEvent;
use App\Services\Analytics\SiteVisitorTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteVisitorAnalyticsController extends Controller
{
    public function store(Request $request, SiteVisitorTracker $tracker): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'event' => ['required', 'string', 'in:'.implode(',', SiteVisitorEvent::EVENT_TYPES)],
            'visitor_id' => ['nullable', 'string', 'max:64'],
            'engaged_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'scroll_pct' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'action_name' => ['nullable', 'string', 'max:120'],
            'referrer' => ['nullable', 'string', 'max:1000'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
            'search_keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $ok = $tracker->track($request, $validated);

        return response()->json([
            'ok' => $ok,
            'event' => $validated['event'],
        ]);
    }
}
